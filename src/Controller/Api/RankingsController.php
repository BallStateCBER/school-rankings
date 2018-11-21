<?php
namespace App\Controller\Api;

use App\Controller\AppController;
use App\Model\Entity\Ranking;
use App\Model\Entity\Statistic;
use App\Model\Table\CountiesTable;
use App\Model\Table\FormulasTable;
use App\Model\Table\MetricsTable;
use App\Model\Table\RankingsTable;
use App\Model\Table\SchoolTypesTable;
use Cake\Database\Expression\QueryExpression;
use Cake\Http\Exception\BadRequestException;
use Cake\Log\Log;
use Cake\ORM\Query;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Queue\Model\Entity\QueuedJob;
use Queue\Model\Table\QueuedJobsTable;

/**
 * Class RankingsController
 * @package App\Controller\Api
 * @property CountiesTable $countiesTable
 * @property FormulasTable $formulasTable
 * @property MetricsTable $metricsTable
 * @property QueuedJobsTable $jobsTable
 * @property RankingsTable $rankingsTable
 * @property SchoolTypesTable $schoolTypesTable
 */
class RankingsController extends AppController
{
    private $countiesTable;
    private $formulasTable;
    private $jobsTable;
    private $metricsTable;
    private $rankingsTable;
    private $schoolTypesTable;

    /**
     * Initialization method
     *
     * @return void
     * @throws \Exception
     */
    public function initialize()
    {
        parent::initialize();
        $this->countiesTable = TableRegistry::getTableLocator()->get('Counties');
        $this->formulasTable = TableRegistry::getTableLocator()->get('Formulas');
        $this->jobsTable = TableRegistry::getTableLocator()->get('Queue.QueuedJobs');
        $this->metricsTable = TableRegistry::getTableLocator()->get('Metrics');
        $this->rankingsTable = TableRegistry::getTableLocator()->get('Rankings');
        $this->schoolTypesTable = TableRegistry::getTableLocator()->get('SchoolTypes');
        $this->Auth->allow();
    }

    /**
     * Adds a ranking record to be processed by a background task
     *
     * @return void
     * @throws \Exception
     * @throws BadRequestException
     */
    public function add()
    {
        $formulaId = $this->request->getData('formulaId');
        $formula = $this->formulasTable->get($formulaId);
        $context = $formula->context;

        // Create new ranking
        $ranking = $this->rankingsTable->newEntity([
            'user_id' => null,
            'formula_id' => $formulaId,
            'for_school_districts' => $context == 'district',
            'hash' => RankingsTable::generateHash()
        ]);

        // Add associations
        $countyIds = [$this->request->getData('countyId')];
        $ranking->counties = $this->countiesTable->find()
            ->where([
                function (QueryExpression $exp) use ($countyIds) {
                    return $exp->in('id', $countyIds);
                }
            ])
            ->toArray();
        if ($context == 'school') {
            $schoolTypeIds = $this->getSchoolTypeIds();
            $ranking->school_types = $this->schoolTypesTable
                ->find()
                ->where([
                    function (QueryExpression $exp) use ($schoolTypeIds) {
                        return $exp->in('id', $schoolTypeIds);
                    }
                ])
                ->toArray();
        }

        // Save ranking
        $this->rankingsTable->save($ranking);
        if ($ranking->id) {
            $rankingId = $ranking->id;
            $job = $this->createRankingJob($rankingId);
            $jobId = $job ? $job->id : null;
        } else {
            $this->logRankingError($ranking);
            $rankingId = null;
            $jobId = null;
        }

        $this->set([
            '_serialize' => [
                'jobId',
                'rankingId',
                'success'
            ],
            'jobId' => $jobId,
            'rankingId' => $rankingId,
            'success' => (bool)$rankingId && (bool)$jobId
        ]);
    }

    /**
     * Logs error in creating ranking
     *
     * @param Ranking $ranking Ranking entity
     * @return void
     */
    private function logRankingError($ranking)
    {
        $errors = $ranking->getErrors();
        $passesRules = $this->rankingsTable->checkRules($ranking, 'create');
        if ($errors || !$passesRules) {
            $errorMsg = 'There was an error creating that ranking.';
            if ($errors) {
                foreach (Hash::flatten($errors) as $field => $errorMsg) {
                    $errorMsg .= "\n - $errorMsg ($field)";
                }
            }
            if (!$passesRules) {
                $errorMsg .= "\n - Did not pass application rules";
            }
            Log::write('error', $errorMsg);
        }
    }

    /**
     * Creates a queued job for generating ranking output for the provided ranking record
     *
     * @param int $rankingId ID of unprocessed ranking record
     * @throws \Exception
     * @return bool|QueuedJob
     */
    private function createRankingJob($rankingId)
    {
        return $this->jobsTable->createJob(
            'Rank',
            ['rankingId' => $rankingId],
            ['reference' => $rankingId]
        );
    }

    /**
     * Endpoint for fetching progress and status for the job specified in ?jobId query string
     *
     * @return void
     */
    public function status()
    {
        $jobId = $this->request->getQuery('jobId');
        $job = $this->jobsTable->get($jobId);
        $this->set([
            '_serialize' => [
                'progress',
                'status'
            ],
            'progress' => $job->progress,
            'status' => $job->status
        ]);
    }

    /**
     * Fetches the results of a ranking
     *
     * @param int $rankingId ID of ranking record
     * @throws \Exception
     * @return void
     */
    public function get($rankingId)
    {
        $containQueries = $this->getContainQueries();
        $ranking = $this->rankingsTable->find()
            ->where(['Rankings.id' => $rankingId])
            ->select(['id'])
            ->contain([
                'Formulas' => $containQueries['formulas'],
                'ResultsSchools' => $containQueries['resultsSchools'],
                'ResultsDistricts' => $containQueries['resultsDistricts']
            ])
            ->enableHydration(false)
            ->first();

        $ranking = $this->formatPercentageValues($ranking);

        // Group results by rank
        $groupedResults = [];
        $results = $ranking['results_schools'] ? $ranking['results_schools'] : $ranking['results_districts'];
        foreach ($results as $result) {
            $groupedResults[$result['rank']][] = $result;
        }

        // Alphabetize results in each rank
        foreach ($groupedResults as $rank => $resultsInRank) {
            $sortedResults = [];
            foreach ($resultsInRank as $resultInRank) {
                $context = isset($resultInRank['school']) ? 'school' : 'district';
                // Combine name and ID in case any two subjects (somehow) have identical names
                $key = $resultInRank[$context]['name'] . $resultInRank[$context]['id'];
                $sortedResults[$key] = $resultInRank;
            }
            ksort($sortedResults);
            $groupedResults[$rank] = array_values($sortedResults);
        }

        // Convert into numerically-indexed array so it can be passed to a React component
        $retval = [];
        foreach ($groupedResults as $rank => $resultsInRank) {
            $retval[] = [
                'rank' => $rank,
                'subjects' => $resultsInRank
            ];
        }

        $this->set([
            '_serialize' => ['results'],
            'results' => $retval
        ]);
    }

    /**
     * Ensures that all statistics for percentage-style metrics are formatted correctly
     *
     * This makes up for the fact that Indiana Department of Education data formats some percentage stats as
     * floats (e.g. 0.41) and some as strings (e.g. "41%")
     *
     * @param array $ranking Ranking results
     * @return array
     */
    private function formatPercentageValues($ranking)
    {
        $metricIsPercent = [];
        $resultsFields = ['results_districts', 'results_schools'];
        foreach ($resultsFields as $results) {
            foreach ($ranking[$results] as &$subject) {
                foreach ($subject['statistics'] as &$statistic) {
                    $metricId = $statistic['metric_id'];
                    if (!isset($metricIsPercent[$metricId])) {
                        $metricIsPercent[$metricId] = $this->metricsTable->isPercentMetric($metricId);
                    }
                    if (!$metricIsPercent[$metricId]) {
                        continue;
                    }
                    if (Statistic::isPercentValue($statistic['value'])) {
                        continue;
                    }
                    $statistic['value'] = Statistic::convertValueToPercent($statistic['value']);
                }
            }
        }

        return $ranking;
    }

    /**
     * Returns all schoolType IDs corresponding to the names found in request data
     *
     * Or returns a blank array if the current context is not 'school'
     *
     * @return array
     */
    private function getSchoolTypeIds()
    {
        $schoolTypes = $this->request->getData('schoolTypes');
        if (!$schoolTypes) {
            throw new BadRequestException('Please specify at least one type of school');
        }

        $results = $this->schoolTypesTable->find()
            ->select(['id'])
            ->where(function (QueryExpression $exp) use ($schoolTypes) {
                return $exp->in('name', $schoolTypes);
            })
            ->enableHydration(false)
            ->toArray();

        return Hash::extract($results, '{n}.id');
    }

    /**
     * Returns an array of contain queries for use in RankingsController::get()
     *
     * @return array
     */
    private function getContainQueries()
    {
        $containStatistics = function (Query $q) {
            return $q->select([
                'id',
                'year',
                'value',
                'metric_id',
                'school_id',
                'school_district_id'
            ]);
        };
        $containCriteria = function (Query $q) {
            return $q
                ->select(['id', 'formula_id'])
                ->contain([
                    'Metrics' => function (Query $q) {
                        return $q->select(['id', 'name']);
                    }
                ]);
        };
        $containSchools = function (Query $q) {
            return $q
                ->select([
                    'id',
                    'name',
                    'address',
                    'url',
                    'phone'
                ]);
        };
        $containDistricts = function (Query $q) {
            return $q
                ->select([
                    'id',
                    'name',
                    'url',
                    'phone'
                ]);
        };
        $containFormulas = function (Query $q) use ($containCriteria) {
            return $q
                ->select(['id'])
                ->contain([
                    'Criteria' => $containCriteria
                ]);
        };
        $containResultsSchools = function (Query $q) use ($containSchools, $containStatistics) {
            return $q->contain([
                'Schools' => $containSchools,
                'Statistics' => $containStatistics
            ]);
        };
        $containResultsDistricts = function (Query $q) use ($containDistricts, $containStatistics) {
            return $q->contain([
                'SchoolDistricts' => $containDistricts,
                'Statistics' => $containStatistics
            ]);
        };

        return [
            'formulas' => $containFormulas,
            'resultsSchools' => $containResultsSchools,
            'resultsDistricts' => $containResultsDistricts
        ];
    }
}
