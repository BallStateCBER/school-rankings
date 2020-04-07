<?php
namespace App\Shell\Task;

use App\Model\Context\Context;
use App\Model\Entity\County;
use App\Model\Entity\Criterion;
use App\Model\Entity\Ranking;
use App\Model\Entity\School;
use App\Model\Entity\SchoolDistrict;
use App\Model\Entity\Statistic;
use App\Model\StatSearcher;
use App\Model\Table\RankingsTable;
use App\Model\Table\StatisticsTable;
use ArrayAccess;
use Cake\Console\Shell;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Datasource\Connection;
use Cake\ElasticSearch\Index as ElasticsearchIndex;
use Cake\ElasticSearch\IndexRegistry;
use Cake\ORM\Query;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Elastica\Client;
use Elastica\Index as ElasticaIndex;
use Exception;
use Queue\Model\Table\QueuedJobsTable;

/**
 * Class RankTask
 * @package App\Shell\Task
 * @property array $rankedSubjects
 * @property bool $allowMultipleYearsPerMetric If TRUE, stats for an older year will be used if a subject has no stats
 *      for the most recent year. If FALSE, all stats for all subjects in a given metric will be from the same year.
 * @property County[] $locations
 * @property Criterion[] $criteria
 * @property float $progressUpdatePercent
 * @property float $progressUpdateTime
 * @property float[] $progressRange
 * @property int[] $metricYears
 * @property int|float $progressUpdateInterval
 * @property ProgressHelper $progressHelper
 * @property QueuedJobsTable $jobsTable
 * @property Ranking $ranking
 * @property RankingsTable $rankingsTable
 * @property School[]|SchoolDistrict[] $subjects
 * @property StatisticsTable $statsTable
 * @property string $context
 */
class RankTask extends Shell
{
    private $allowMultipleYearsPerMetric = false;
    private $context;
    private $criteria;
    private $jobId;
    private $jobsTable;
    private $metricYears = [];
    private $progressHelper;
    private $progressRange = [0, 1];
    private $progressUpdateInterval = 1; // seconds
    private $progressUpdatePercent;
    private $progressUpdateTime;
    private $rankedSubjects = [];
    private $ranking;
    private $rankingsTable;
    private $statsTable;
    private $subjects = [];

    /**
     * Elasticsearch index for statistics
     * @var ElasticsearchIndex
     */
    private $statsEsIndex;

    /**
     * RankTask initialize method
     *
     * @return void
     */
    public function initialize()
    {
        $this->rankingsTable = TableRegistry::getTableLocator()->get('Rankings');
        $this->statsTable = TableRegistry::getTableLocator()->get('Statistics');
        $this->progressHelper = $this->getIo()->helper('Progress');
        $this->jobsTable = TableRegistry::getTableLocator()->get('Queue.QueuedJobs');

        /**
         * @var Connection|Client $connection
         * @var ElasticaIndex $statisticsIndexRegistry
         * @var ElasticsearchIndex $statisticsIndex
         */
        $statsEsIndex = IndexRegistry::get('Statistics');
        $connection = ConnectionManager::get('elastic');
        $statisticsIndexRegistry = $connection->getIndex($statsEsIndex->getName());
        if ($statisticsIndexRegistry->exists()) {
            $this->statsEsIndex = $statsEsIndex;
        }
    }

    /**
     * Processes an unprocessed ranking
     *
     * @param int $rankingId ID of ranking record
     * @return void
     * @throws Exception
     */
    public function process($rankingId)
    {
        $this->setProgressRange(0, 0.05);
        $this->loadRankingRecord($rankingId);
        $msg = $this->isUsingElasticsearch() ? 'Using Elasticsearch statistics index' : 'Using MySQL statistics table';
        $this->getIo()->out($msg);
        $this->loadSubjects();

        $this->setProgressRange(0.05, 0.9);
        $this->loadStats();

        $this->setProgressRange(0.9, 0.95);
        $this->scoreSubjects();

        $this->setProgressRange(0.95, 0.96);
        $this->markDataCompleteness();

        $this->setProgressRange(0.96, 0.99);
        $this->rankSubjects();

        $this->setProgressRange(0.99, 1);
        $this->saveResults();

        $this->updateJobProgress(1, true);
        $this->updateJobStatus('Done');
        $this->outputResults();
    }

    /**
     * Returns either the schools or districts that are associated with the specified locations
     *
     * @return void
     * @throws Exception
     */
    private function loadSubjects()
    {
        $this->updateJobStatus("Finding {$this->context}s", true);

        $subjectTable = Context::getTable($this->context);
        $locations = $this->getLocations();
        $this->createProgressBar(count($locations));

        $step = 1;
        $schoolTypeIds = Hash::extract($this->ranking->school_types, '{n}.id');
        $gradeLevelIds = Hash::extract($this->ranking->grades, '{n}.id');
        foreach ($locations as $location) {
            // Base query
            $locationTableName = $this->getLocationTableName($location);
            $query = $subjectTable->find()
                ->matching($locationTableName, function (Query $q) use ($locationTableName, $location) {
                    return $q->where(["$locationTableName.id" => $location->id]);
                })
                ->where(['closed' => false]);

            // Limit school types
            if ($this->context == Context::SCHOOL_CONTEXT) {
                $query->where(function (QueryExpression $exp) use ($schoolTypeIds) {
                    return $exp->in('school_type_id', $schoolTypeIds);
                });
                if ($gradeLevelIds) {
                    $query->matching('Grades', function (Query $q) use ($gradeLevelIds) {
                        return $q->where(function (QueryExpression $exp) use ($gradeLevelIds) {
                            return $exp->in('Grades.id', $gradeLevelIds);
                        });
                    });
                }
            }

            $subjects = $query->all();

            // Use school/district IDs as keys to avoid duplicates
            foreach ($subjects as $result) {
                $result->score = 0;
                $this->subjects[$result->id] = $result;
            }

            $this->incrementProgressBar();
            $overallProgress = $this->getOverallProgress($step, count($locations));
            $this->updateJobProgress($overallProgress);
            $step++;
        }

        $this->getIo()->overwrite(sprintf(
            ' - %s %s found',
            count($this->subjects),
            __n($this->context, "{$this->context}s", count($this->subjects))
        ));
    }

    /**
     * Classifies subjects as having full data, partial data, or no data
     *
     * @return void
     */
    private function markDataCompleteness()
    {
        $this->updateJobStatus("Analyzing {$this->context}s for data availability", true);
        $criteria = $this->ranking->formula->criteria;
        $metricCount = count($criteria);

        $step = 1;
        foreach ($this->subjects as $subject) {
            $subjectStatCount = count($subject->statistics);
            $dataCompleteness = ($subjectStatCount == 0) ? 'empty' : (
                ($subjectStatCount == $metricCount) ? 'full' : 'partial'
            );
            $subject->setDataCompleteness($dataCompleteness);
            $overallProgress = $this->getOverallProgress($step, count($this->subjects));
            $this->updateJobProgress($overallProgress);
            $step++;
        }

        $this->getIo()->overwrite(' - Done');
    }

    /**
     * Returns array of subjects ordered by their rank, according to the current formula
     *
     * @return void
     */
    private function rankSubjects()
    {
        $this->updateJobStatus("Ranking {$this->context}s", true);

        // Sort by score, creating an array of all subjects with each score
        $sortedSubjects = [];
        foreach ($this->subjects as $subject) {
            /* The float score is multiplied by 1,000 and cast to int so that subjects with unequal scores that are very
             * close to each other will have equal keys, and consequently will share the same rank. For example, two
             * districts with scores of 28.1231 and 28.1233 will both have the same sorting key: 28,123. */
            $key = (int)($subject->score * 1000);
            $sortedSubjects[$key][] = $subject;
        }
        krsort($sortedSubjects);

        // Arrange subjects into ranks, with same-rank subjects in random order
        $rank = 1;
        foreach ($sortedSubjects as $k => $subjectsInRank) {
            shuffle($subjectsInRank);
            $this->rankedSubjects[$rank] = $subjectsInRank;
            $rank++;
        }

        // This method runs pretty quickly, so we'll skip incremental progress updates
        $overallProgress = $this->getOverallProgress(1, 1);
        $this->updateJobProgress($overallProgress);

        $this->getIo()->out(' - Done');
    }

    /**
     * Returns a single array with all of the associated location-type entities
     *
     * @return array
     */
    private function getLocations()
    {
        $locations = [];
        $locations = array_merge($locations, $this->ranking->cities);
        $locations = array_merge($locations, $this->ranking->counties);
        $locations = array_merge($locations, $this->ranking->ranges);
        $locations = array_merge($locations, $this->ranking->school_districts);
        $locations = array_merge($locations, $this->ranking->states);

        return $locations;
    }

    /**
     * Gets e.g. the string 'Counties' from a County-type object
     *
     * @param County $location Any location-type entity
     * @return string
     */
    private function getLocationTableName($location)
    {
        $namespacedClassName = get_class($location);
        $pos = strrpos($namespacedClassName, '\\');
        $className = substr($namespacedClassName, $pos + 1);

        return Inflector::pluralize($className);
    }

    /**
     * Adds relevant statistics to each school/district record
     *
     * @return void
     */
    private function loadStats()
    {
        $this->loadYears();

        $this->updateJobStatus('Analyzing statistical data', true);
        $subjectCount = count($this->subjects);
        if (!$subjectCount) {
            $this->getIo()->out(' - Done');

            return;
        }

        $stepsCount = count($this->subjects) + 1;
        $this->createProgressBar($stepsCount);
        $metricIds = $this->getMetricIds();
        $step = 1;

        $this->incrementProgressBar();

        // Get stats
        foreach ($this->subjects as &$subject) {
            $subject->statistics = [];
            foreach ($metricIds as $metricId) {
                $stat = $this->getStat($metricId, $subject->id);
                if ($stat) {
                    $subject->statistics[] = $stat;
                }
            }

            $this->incrementProgressBar();
            $overallProgress = $this->getOverallProgress($step, $stepsCount);
            $this->updateJobProgress($overallProgress);
            $step++;
        }
        $this->getIo()->overwrite(' - Done');
    }

    /**
     * Sets the 'score' property for each school/district
     *
     * @return void
     */
    private function scoreSubjects()
    {
        $this->updateJobStatus("Scoring {$this->context}s", true);
        $outputMsgs = [];

        $subjectCount = count($this->subjects);
        if ($subjectCount) {
            $criteria = $this->ranking->formula->criteria;
            $this->createProgressBar(count($this->subjects) * count($criteria));

            foreach ($criteria as $criterion) {
                $metricId = $criterion->metric_id;
                $weight = $criterion->weight;
                list($minValue, $maxValue) = $this->getValueRange($metricId);
                if (!isset($minValue)) {
                    $this->incrementProgressBar($subjectCount);
                    continue;
                }
                $step = 1;
                foreach ($this->subjects as &$subject) {
                    /** @var School|SchoolDistrict $subject */
                    foreach ($subject->statistics as $statistic) {
                        if ($statistic->metric_id != $metricId) {
                            continue;
                        }
                        $value = $statistic->numeric_value;
                        $metricScore = ($value / $maxValue) * $weight;
                        $subject->score += $metricScore;
                        $outputMsgs[] = "Metric $metricId score for $subject->name: $metricScore";
                    }

                    $this->incrementProgressBar();
                    $overallProgress = $this->getOverallProgress($step, count($this->subjects));
                    $this->updateJobProgress($overallProgress);
                    $step++;
                }
            }
        }

        $this->getIo()->overwrite(' - Done');
        $this->getIo()->out('Results:');
        foreach ($outputMsgs as $outputMsg) {
            $this->getIo()->out(" - $outputMsg");
        }
    }

    /**
     * Returns the minimum and maximum values of the statistics collected for the specified metric
     *
     * @param int $metricId ID of metric record
     * @return array
     */
    private function getValueRange($metricId)
    {
        $allValues = [];
        foreach ($this->subjects as $subject) {
            foreach ($subject->statistics as $statistic) {
                if ($statistic->metric_id != $metricId) {
                    continue;
                }
                $allValues[] = $statistic->numeric_value;
            }
        }

        return $allValues ? [min($allValues), max($allValues)] : [null, null];
    }

    /**
     * Outputs the final results
     *
     * @return void
     */
    private function outputResults()
    {
        $this->getIo()->out();
        foreach ($this->rankedSubjects as $rank => $rankedSubjects) {
            $this->getIo()->out($rank);
            foreach ($rankedSubjects as $subject) {
                $this->getIo()->out(" - $subject->name ($subject->score)");
            }
        }
    }

    /**
     * Sets this class's 'ranking' and 'context' properties
     *
     * @param int $rankingId ID of record in rankings table
     * @return void
     */
    private function loadRankingRecord($rankingId)
    {
        $this->getIo()->out("Finding ranking #$rankingId...");
        $this->ranking = $this->rankingsTable->get($rankingId, [
            'contain' => [
                'Cities',
                'Counties',
                'Formulas',
                'Formulas.Criteria',
                'Grades',
                'Ranges',
                'SchoolDistricts',
                'SchoolTypes',
                'States',
            ],
        ]);
        $this->context = $this->ranking->formula->context;
        $this->getIo()->out(' - Ranking found');
    }

    /**
     * Setter for the jobId property
     *
     * @param int $jobId ID of a queued job record
     * @return void
     */
    public function setJobId($jobId)
    {
        $this->jobId = $jobId;
    }

    /**
     * Updates the current queued job's progress percent
     *
     * @param int $progressPercent Progress percent, from 0 to 1
     * @param bool $forceUpdate If true, update will not be skipped
     * @return void
     */
    private function updateJobProgress($progressPercent, $forceUpdate = false)
    {
        // Skip if no job
        if (!$this->jobId) {
            return;
        }

        // Skip if amount hasn't changed
        if (!$forceUpdate && $this->progressUpdatePercent == $progressPercent) {
            return;
        }

        // Skip if too soon
        $now = microtime(true);
        if (!$forceUpdate && ($now - $this->progressUpdateTime) < $this->progressUpdateInterval) {
            return;
        }

        $this->jobsTable->updateProgress($this->jobId, $progressPercent);
        $this->progressUpdateTime = $now;
        $this->progressUpdatePercent = $progressPercent;
    }

    /**
     * Updates the current queued job's status
     *
     * @param string $status Status message
     * @param bool $outputToConsole Set to TRUE to also output message to ConsoleIo with an appended ellipsis
     * @param string $append String that gets automatically appended to console output
     * @return void
     */
    private function updateJobStatus($status, $outputToConsole = false, $append = '...')
    {
        if ($outputToConsole) {
            $this->getIo()->out($status . $append);
        }

        if (!$this->jobId) {
            return;
        }

        $this->jobsTable->updateAll(
            ['status' => $status],
            ['id' => $this->jobId]
        );
    }

    /**
     * Returns a value representing what percent complete a given task is
     *
     * If $rangeStart and $rangeEnd are specified, the result will be in that range.
     * Example: We're on step 30 of 100 in a sub-task that will bring the overall task from 0% to 20% complete,
     * so getOverallProgress(30, 100, 0, .2) will output 30% of 20, or 6%, output as 0.06
     *
     * @param int $step Current step number
     * @param int $totalSteps Total number of steps
     * @return float
     */
    private function getOverallProgress($step, $totalSteps)
    {
        $rangeStart = $this->progressRange[0];
        $rangeEnd = $this->progressRange[1];
        $percent = $step / $totalSteps;

        return (($rangeEnd - $rangeStart) * $percent) + $rangeStart;
    }

    /**
     * Sets the range of overall progress made up by the current task's progress
     *
     * For example, if this is set to (0.2, 0.7), then as the current task progresses from 0% to 100% done, the overall
     * progress should be represented as progressing from 20% to 70% done
     *
     * @param float $start Start of the progress range
     * @param float $end End of the progress range
     * @return void
     */
    private function setProgressRange($start, $end)
    {
        $this->progressRange = [$start, $end];
    }

    /**
     * Saves the serialized ranks and subject IDs to the ranking record in the database
     *
     * @throws Exception
     * @return void
     */
    private function saveResults()
    {
        $this->getIo()->out('Saving results...');
        $this->updateJobStatus('Finalizing');

        // Add school/district info
        $results = [];
        $locationIdField = Context::getLocationField($this->context);
        foreach ($this->rankedSubjects as $rank => $subjectsInRank) {
            foreach ($subjectsInRank as $subject) {
                $statistics = $subject->statistics;
                $statIds = Hash::extract($statistics, '{n}.id');

                /** @var School|SchoolDistrict $subject */
                $results[] = [
                    'rank' => $rank,
                    $locationIdField => $subject->id,
                    'data_completeness' => $subject->getDataCompleteness(),
                    'statistics' => ['_ids' => $statIds],
                ];
            }
        }
        $resultsField = $this->context == Context::SCHOOL_CONTEXT ? 'results_schools' : 'results_districts';
        $resultsAssociation = $this->context == Context::SCHOOL_CONTEXT ? 'ResultsSchools' : 'ResultsDistricts';
        $this->rankingsTable->patchEntity(
            $this->ranking,
            [$resultsField => $results],
            ['associated' => ["$resultsAssociation.Statistics"]]
        );

        if ($this->rankingsTable->save($this->ranking)) {
            $overallProgress = $this->getOverallProgress(1, 1);
            $this->updateJobProgress($overallProgress);
            $this->getIo()->out(' - Done');

            return;
        }

        throw new Exception('Error saving ranking results');
    }

    /**
     * Retrieves a statistic entity
     *
     * @param int $metricId Metric ID
     * @param int $subjectId School ID or school district ID
     * @return Statistic|null
     */
    private function getStat($metricId, int $subjectId)
    {
        $query = $this->getStatsDatasource()
            ->find()
            ->select(['id', 'metric_id', 'value', 'year'])
            ->where([
                'metric_id' => $metricId,
                Context::getLocationField($this->context) => $subjectId,
            ]);
        if ($this->allowMultipleYearsPerMetric) {
            $query->order(['year' => 'DESC']);
        } else {
            $year = $this->metricYears[$metricId] ?? null;
            if ($year) {
                $query->where(['year' => $year]);
            }
        }

        /** @var Statistic $stat */
        $stat = $query->first();

        if ($stat && $this->isUsingElasticsearch()) {
            $stat = $this->statsTable->newEntity([
                'id' => $stat->id,
                'metric_id' => $stat->metric_id,
                'value' => $stat->value,
                'year' => $stat->year,
            ]);
        }

        return $stat;
    }

    /**
     * Increments the current progress bar by 1
     *
     * @param int $incrementAmount Specify if not 1
     * @return void
     */
    private function incrementProgressBar($incrementAmount = 1)
    {
        $this->progressHelper->increment($incrementAmount);
        $this->progressHelper->draw();
    }

    /**
     * Creates and draws a progress bar at 0%
     *
     * @param int $count Total number of steps
     * @return void
     */
    private function createProgressBar(int $count)
    {
        $this->progressHelper->init([
            'total' => $count,
            'width' => 40,
        ]);
        $this->progressHelper->draw();
    }

    /**
     * Returns the array of criteria for the current ranking task
     *
     * @return Criterion[]
     */
    private function getCriteria()
    {
        return $this->ranking->formula->criteria;
    }

    /**
     * Returns the array of metric IDs associated with the current ranking task
     *
     * @return array|ArrayAccess
     */
    private function getMetricIds()
    {
        return Hash::extract($this->getCriteria(), '{n}.metric_id');
    }

    /**
     * Returns the Elasticsearch stats index, or if it's not available, the MySQL stats table
     *
     * @return StatisticsTable|ElasticsearchIndex
     */
    private function getStatsDatasource()
    {
        return isset($this->statsEsIndex) ? $this->statsEsIndex : $this->statsTable;
    }

    /**
     * Populates the metricYears property with the most recent year associated with each metric, or aborts if stats are
     * not being constrained to a specific year per metric
     *
     * @return void
     */
    private function loadYears()
    {
        if ($this->allowMultipleYearsPerMetric) {
            return;
        }

        $subjectIds = Hash::extract($this->subjects, '{n}.id');
        $locationField = Context::getLocationField($this->context);

        $this->getIo()->out('Finding most recent years for each metric...');
        $statSearcher = new StatSearcher($this->getStatsDatasource());
        foreach ($this->getMetricIds() as $metricId) {
            $this->metricYears[$metricId] = $statSearcher->getMostRecentYear([
                'metric_id' => $metricId,
                "{$locationField}s" => $subjectIds,
            ]);
        }
        $this->getIo()->out(' - Done');
    }

    /**
     * Returns TRUE if the Elasticsearch statistics index is available
     *
     * @return bool
     */
    private function isUsingElasticsearch()
    {
        return isset($this->statsEsIndex);
    }
}
