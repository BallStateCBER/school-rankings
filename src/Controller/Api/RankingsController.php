<?php
namespace App\Controller\Api;

use App\Controller\AppController;
use App\Model\Entity\Ranking;
use App\Model\Entity\RankingResultsSchool;
use App\Model\Entity\RankingResultsSchoolDistrict;
use App\Model\Table\RankingsTable;
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
 * @property RankingsTable $rankingsTable
 */
class RankingsController extends AppController
{
    private $rankingsTable;

    /**
     * Initialization method
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();
        $this->rankingsTable = TableRegistry::getTableLocator()->get('Rankings');
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
        $context = $this->request->getData('context');

        $ranking = $this->rankingsTable->newEntity([
            'user_id' => null,
            'formula_id' => $this->request->getData('formulaId'),
            'school_type_id' => null,
            'for_school_districts' => $context == 'district',
            'hash' => RankingsTable::generateHash()
        ]);

        $countyIds = [$this->request->getData('countyId')];
        $ranking->counties = TableRegistry::getTableLocator()
            ->get('Counties')
            ->find()
            ->where([
                function (QueryExpression $exp) use ($countyIds) {
                    return $exp->in('id', $countyIds);
                }
            ])
            ->toArray();

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
        /** @var QueuedJobsTable $jobsTable */
        $jobsTable = TableRegistry::getTableLocator()->get('Queue.QueuedJobs');

        return $jobsTable->createJob(
            'Rank',
            [
                'rankingId' => $rankingId
            ],
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
        $jobsTable = TableRegistry::getTableLocator()->get('Queue.QueuedJobs');
        $job = $jobsTable->get($jobId);
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

        /** @var Ranking $ranking */
        $ranking = $this->rankingsTable->find()
            ->where(['Rankings.id' => $rankingId])
            ->select([
                'id'
            ])
            ->contain([
                'Formulas' => function (Query $q) {
                    return $q
                        ->select(['id'])
                        ->contain([
                            'Criteria' => function (Query $q) {
                                return $q
                                    ->select(['id', 'formula_id'])
                                    ->contain([
                                        'Metrics' => function (Query $q) {
                                            return $q
                                                ->select(['id', 'name']);
                                        }
                                    ]);
                            }
                        ]);
                },
                'ResultsSchools' => function (Query $q) use ($containStatistics) {
                    return $q->contain([
                        'Schools' => function (Query $q) {
                            return $q
                                ->select([
                                    'id',
                                    'name',
                                    'address',
                                    'url',
                                    'phone'
                                ]);
                        },
                        'Statistics' => $containStatistics
                    ]);
                },
                'ResultsDistricts' => function (Query $q) use ($containStatistics) {
                    return $q->contain([
                        'SchoolDistricts' => function (Query $q) {
                            return $q
                                ->select([
                                    'id',
                                    'name',
                                    'url',
                                    'phone'
                                ]);
                        },
                        'Statistics' => $containStatistics
                    ]);
                }
            ])
            ->enableHydration(false)
            ->first();

        // Group results by rank
        $groupedResults = [];
        $results = isset($ranking['results_schools']) ? $ranking['results_schools'] : $ranking['results_districts'];
        foreach ($results as $result) {
            /** @var RankingResultsSchool|RankingResultsSchoolDistrict $result */
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
}
