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
 * @property array $groupedSubjects
 * @property array $rankedSubjects
 * @property County[] $locations
 * @property Criterion[] $criteria
 * @property ProgressHelper $progress
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
    private $groupedSubjects = [
        'full data' => [],
        'partial data' => [],
        'no data' => []
    ];
    private $jobId;
    private $jobsTable;
    private $progress;
    private $rankedSubjects = [
        'full data' => [],
        'partial data' => [],
        'no data' => []
    ];
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
        $this->progress = $this->getIo()->helper('Progress');
        $this->jobsTable = TableRegistry::getTableLocator()->get('QueuedJobs');
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
        $this->loadRankingRecord($rankingId);
        $this->loadSubjects();
        $this->loadStats();
        $this->scoreSubjects();
        $this->groupSubjects();
        $this->rankSubjects();
        $this->updateJobProgress(100);
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
        $this->progress->init([
            'total' => count($locations),
            'width' => 40,
        ]);
        $this->progress->draw();

        foreach ($locations as $n => $location) {
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

            $this->progress->increment(1);
            $this->progress->draw();
            $overallProgress = $this->getOverallProgress($n, count($locations), 0, 20);
            $this->updateJobProgress($overallProgress);
        }

        $this->getIo()->overwrite(sprintf(
            ' - %s %s found',
            count($this->subjects),
            __n($this->context, "{$this->context}s", count($this->subjects))
        ));
    }

    /**
     * Groups subjects into full data, partial data, and no data categories
     *
     * @return void
     */
    private function groupSubjects()
    {
        $msg = "Grouping {$this->context}s by data availability";
        $this->getIo()->out("$msg...");
        $this->updateJobStatus($msg);
        $criteria = $this->ranking->formula->criteria;
        $metricCount = count($criteria);

        foreach ($this->subjects as $n => $subject) {
            $subjectStatCount = count($subject->statistics);
            if ($subjectStatCount == $metricCount) {
                $this->groupedSubjects['full data'][] = $subject;
                continue;
            }

            if ($subjectStatCount > 0) {
                $this->groupedSubjects['partial data'][] = $subject;
                continue;
            }

            $this->groupedSubjects['no data'][] = $subject;

            $overallProgress = $this->getOverallProgress($n, count($this->subjects), 60, 80);
            $this->updateJobProgress($overallProgress);
        }

        foreach ($this->groupedSubjects as $group => $subjects) {
            $this->getIo()->out(sprintf(
                ' - %s: %s',
                ucfirst($group),
                count($subjects)
            ));
        }
    }

    /**
     * Returns grouped array of subjects ordered by their rank, according to the current formula
     *
     * @return void
     */
    private function rankSubjects()
    {
        $msg = "Ranking {$this->context}s";
        $this->getIo()->out("$msg...");
        $this->updateJobStatus($msg);

        $step = 1;
        foreach ($this->groupedSubjects as $group => $subjects) {
            // Sort by score, creating an array of all schools/districts with each score
            $sortedSubjects = [];
            foreach ($subjects as $subject) {
                $sortedSubjects[$subject->score][] = $subject;
            }
            krsort($sortedSubjects);

            $rank = 1;
            foreach ($sortedSubjects as $score => $subjectsInRank) {
                shuffle($subjectsInRank);
                $this->rankedSubjects[$group][$rank] = $subjectsInRank;
                $rank++;
            }

            $overallProgress = $this->getOverallProgress($step, count($this->groupedSubjects), 80, 100);
            $this->updateJobProgress($overallProgress);
            $step++;
        }
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
        $this->progress->init([
            'total' => count($this->subjects),
            'width' => 40,
        ]);
        $this->progress->draw();
        $criteria = $this->ranking->formula->criteria;
        $metricIds = Hash::extract($criteria, '{n}.metric_id');
        foreach ($this->subjects as $n => &$subject) {
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

            $this->progress->increment(1);
            $this->progress->draw();
            $overallProgress = $this->getOverallProgress($n, count($this->subjects), 20, 40);
            $this->updateJobProgress($overallProgress);
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
        $this->progress->init([
            'total' => count($this->subjects) * count($criteria),
            'width' => 40,
        ]);
        $this->progress->draw();

        $outputMsgs = [];
        foreach ($criteria as $criterion) {
            $metricId = $criterion->metric_id;
            $weight = $criterion->weight;
            list($minValue, $maxValue) = $this->getValueRange($metricId);
            if (!isset($minValue)) {
                $this->progress->increment(count($this->subjects));
                $this->progress->draw();
                continue;
            }
            foreach ($this->subjects as $n => &$subject) {
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

                $this->progress->increment(1);
                $this->progress->draw();
                $overallProgress = $this->getOverallProgress($n, count($this->subjects), 40, 60);
                $this->updateJobProgress($overallProgress);
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
        foreach ($this->rankedSubjects as $group => $groupedSubjects) {
            $this->getIo()->out(ucfirst($group) . ':');
            foreach ($groupedSubjects as $rank => $rankedSubjects) {
                $this->getIo()->out($rank);
                foreach ($rankedSubjects as $subject) {
                    $this->getIo()->out(" - $subject->name ($subject->score)");
                }
            }
            $this->getIo()->out();
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
     * @param int $progress Progress percent, from 1 to 100
     * @return void
     */
    private function updateJobProgress($progress)
    {
        if (!$this->jobId) {
            return;
        }

        $this->jobsTable->updateProgress($this->jobId, $progress);
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
     * so getOverallProgress(30, 100, 0, 20) will output 30% of 20, or 6%
     *
     * @param int $step Current step number
     * @param int $totalSteps Total number of steps
     * @param int $rangeStart Beginning of the progress range for the current task
     * @param int $rangeEnd End of the progress range for the current task
     * @return int
     */
    private function getOverallProgress($step, $totalSteps, $rangeStart = 0, $rangeEnd = 100)
    {
        $percent = $step / $totalSteps;

        return round((($rangeEnd - $rangeStart) * $percent) + $rangeStart);
    }
}
