<?php
namespace App\Shell\Task;

use App\Model\Context\Context;
use App\Model\Entity\County;
use App\Model\Entity\Criterion;
use App\Model\Entity\Ranking;
use App\Model\Entity\School;
use App\Model\Entity\SchoolDistrict;
use App\Model\Table\RankingsTable;
use App\Model\Table\StatisticsTable;
use Cake\Console\Shell;
use Cake\Database\Expression\QueryExpression;
use Cake\ORM\Query;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Queue\Model\Table\QueuedJobsTable;

/**
 * Class RankTask
 * @package App\Shell\Task
 * @property array $rankedSubjects
 * @property County[] $locations
 * @property Criterion[] $criteria
 * @property float $progressUpdatePercent
 * @property float $progressUpdateTime
 * @property float[] $progressRange
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
    private $context;
    private $criteria;
    private $jobId;
    private $jobsTable;
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
    }

    /**
     * Processes an unprocessed ranking
     *
     * @param int $rankingId ID of ranking record
     * @return bool
     * @throws \Exception
     */
    public function process($rankingId)
    {
        $this->setProgressRange(0, 0.05);
        $this->loadRankingRecord($rankingId);
        $this->loadSubjects();

        $this->setProgressRange(0.05, 0.9);
        $this->loadStats();

        $this->setProgressRange(0.9, 0.95);
        $this->scoreSubjects();

        $this->setProgressRange(0.95, 1);
        $this->markDataCompleteness();
        $this->rankSubjects();

        $this->updateJobProgress(1, true);
        $this->updateJobStatus('Done');
        $this->outputResults();

        return true;
    }

    /**
     * Returns either the schools or districts that are associated with the specified locations
     *
     * @return void
     * @throws \Exception
     */
    private function loadSubjects()
    {
        $msg = "Finding {$this->context}s";
        $this->getIo()->out("$msg...");
        $this->updateJobStatus($msg);

        $subjectTable = Context::getTable($this->context);
        $locations = $this->getLocations();
        $this->progressHelper->init([
            'total' => count($locations),
            'width' => 40,
        ]);
        $this->progressHelper->draw();

        $step = 1;
        foreach ($locations as $location) {
            $locationTableName = $this->getLocationTableName($location);
            $subjects = $subjectTable->find()
                ->matching($locationTableName, function (Query $q) use ($locationTableName, $location) {
                    return $q->where(["$locationTableName.id" => $location->id]);
                })
                ->all();

            // Use school/district IDs as keys to avoid duplicates
            foreach ($subjects as $result) {
                $result->score = 0;
                $this->subjects[$result->id] = $result;
            }

            $this->progressHelper->increment(1);
            $this->progressHelper->draw();
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
        $msg = "Analyzing {$this->context}s for data availability";
        $this->getIo()->out("$msg...");
        $this->updateJobStatus($msg);
        $criteria = $this->ranking->formula->criteria;
        $metricCount = count($criteria);

        $step = 1;
        foreach ($this->subjects as $subject) {
            $subjectStatCount = count($subject->statistics);
            if ($subjectStatCount == $metricCount) {
                $subject->setDataCompleteness('full');
            } elseif ($subjectStatCount > 0) {
                $subject->setDataCompleteness('partial');
            } else {
                $subject->setDataCompleteness('empty');
            }

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
        $msg = "Ranking {$this->context}s";
        $this->getIo()->out("$msg...");
        $this->updateJobStatus($msg);

        // Sort by score, creating an array of all subjects with each score
        $sortedSubjects = [];
        foreach ($this->subjects as $subject) {
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
        $msg = 'Collecting statistics';
        $this->getIo()->out("$msg...");
        $this->updateJobStatus($msg);
        $this->progressHelper->init([
            'total' => count($this->subjects),
            'width' => 40,
        ]);
        $this->progressHelper->draw();
        $criteria = $this->ranking->formula->criteria;
        $metricIds = Hash::extract($criteria, '{n}.metric_id');
        $step = 1;
        foreach ($this->subjects as &$subject) {
            $query = $this->statsTable->find()
                ->select(['metric_id', 'value', 'year'])
                ->where([
                    function (QueryExpression $exp) use ($metricIds) {
                        return $exp->in('Statistics.metric_id', $metricIds);
                    },
                    Context::getLocationField($this->context) => $subject->id
                ])
                ->limit(1)
                ->orderDesc('year');
            $subject->statistics = $query->all();

            $this->progressHelper->increment(1);
            $this->progressHelper->draw();
            $overallProgress = $this->getOverallProgress($step, count($this->subjects));
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
        $msg = "Scoring {$this->context}s";
        $this->getIo()->out("$msg...");
        $this->updateJobStatus($msg);
        $criteria = $this->ranking->formula->criteria;
        $this->progressHelper->init([
            'total' => count($this->subjects) * count($criteria),
            'width' => 40,
        ]);
        $this->progressHelper->draw();

        $outputMsgs = [];
        foreach ($criteria as $criterion) {
            $metricId = $criterion->metric_id;
            $weight = $criterion->weight;
            list($minValue, $maxValue) = $this->getValueRange($metricId);
            if (!isset($minValue)) {
                $this->progressHelper->increment(count($this->subjects));
                $this->progressHelper->draw();
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

                $this->progressHelper->increment(1);
                $this->progressHelper->draw();
                $overallProgress = $this->getOverallProgress($step, count($this->subjects));
                $this->updateJobProgress($overallProgress);
                $step++;
            }
        }

        $this->getIo()->overwrite(' - Done');

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
                'States'
            ]
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
     * @return void
     */
    private function updateJobStatus($status)
    {
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
}
