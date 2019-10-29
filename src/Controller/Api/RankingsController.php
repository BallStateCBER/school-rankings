<?php
namespace App\Controller\Api;

use App\Controller\AppController;
use App\Model\Entity\Ranking;
use App\Model\Table\CountiesTable;
use App\Model\Table\FormulasTable;
use App\Model\Table\GradesTable;
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
 * @property GradesTable $gradeLevelsTable
 * @property MetricsTable $metricsTable
 * @property QueuedJobsTable $jobsTable
 * @property RankingsTable $rankingsTable
 * @property SchoolTypesTable $schoolTypesTable
 */
class RankingsController extends AppController
{
    private $countiesTable;
    private $formulasTable;
    private $gradeLevelsTable;
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
        $this->gradeLevelsTable = TableRegistry::getTableLocator()->get('Grades');
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
            $ranking->school_types = $this->getSchoolTypes();
            $ranking->grades = $this->getGradeLevels();
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
            ->first();

        $ranking->rankStatistics();
        $ranking->formatPercentageValues();

        // Separate out and sort no-data results
        $allResults = $ranking['results_schools'] ? $ranking['results_schools'] : $ranking['results_districts'];
        $resultsWithoutData = [];
        $resultsWithData = [];
        foreach ($allResults as $i => $result) {
            if ($result['data_completeness'] === 'empty') {
                $context = isset($result['school']) ? 'school' : 'school_district';
                // Combine name and ID in case any two subjects (somehow) have identical names
                $key = $result[$context]['name'] . $result[$context]['id'];
                $resultsWithoutData[$key] = $result;
            } else {
                $resultsWithData[] = $result;
            }
        }
        ksort($resultsWithoutData);
        $resultsWithoutData = array_values($resultsWithoutData);

        // Group results by rank
        $groupedResults = [];
        foreach ($resultsWithData as $result) {
            $groupedResults[$result['rank']][] = $result;
        }

        // Alphabetize results in each rank
        foreach ($groupedResults as $rank => $resultsInRank) {
            $sortedResults = [];
            foreach ($resultsInRank as $resultInRank) {
                $context = isset($resultInRank['school']) ? 'school' : 'school_district';
                // Combine name and ID in case any two subjects (somehow) have identical names
                $key = $resultInRank[$context]['name'] . $resultInRank[$context]['id'];
                $sortedResults[$key] = $resultInRank;
            }
            ksort($sortedResults);
            $groupedResults[$rank] = array_values($sortedResults);
        }

        // Convert into numerically-indexed array so it can be passed to a React component
        $indexedResults = [];
        foreach ($groupedResults as $rank => $resultsInRank) {
            $indexedResults[] = [
                'rank' => $rank,
                'subjects' => $resultsInRank
            ];
        }

        $this->set([
            '_serialize' => ['noDataResults', 'results'],
            'results' => $indexedResults,
            'noDataResults' => $resultsWithoutData
        ]);
    }

    /**
     * Returns all schoolType entities corresponding to the IDs found in request data
     *
     * @return array
     */
    private function getSchoolTypes()
    {
        $schoolTypeIds = $this->request->getData('schoolTypes');
        if (!$schoolTypeIds) {
            throw new BadRequestException('Please specify at least one type of school');
        }

        return $this->schoolTypesTable->find()
            ->select()
            ->where(function (QueryExpression $exp) use ($schoolTypeIds) {
                return $exp->in('id', $schoolTypeIds);
            })
            ->toArray();
    }

    /**
     * Returns all grade entities corresponding to the grade IDs found in request data
     *
     * @return array
     */
    private function getGradeLevels()
    {
        $gradeLevelIds = $this->request->getData('gradeLevels');

        if (!$gradeLevelIds) {
            return [];
        }

        return $this->gradeLevelsTable->find()
            ->select()
            ->where(function (QueryExpression $exp) use ($gradeLevelIds) {
                return $exp->in('id', $gradeLevelIds);
            })
            ->toArray();
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
                ])
                ->contain([
                    'Grades' => function (Query $q) {
                        return $q
                            ->select(['id', 'name'])
                            ->orderAsc('Grades.id');
                    },
                    'SchoolTypes' => function (Query $q) {
                        return $q->select(['id', 'name']);
                    }
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
