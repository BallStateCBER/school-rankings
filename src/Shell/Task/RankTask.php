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
use Cake\Console\ConsoleIo;
use Cake\Console\Shell;
use Cake\Database\Expression\QueryExpression;
use Cake\ORM\Locator\LocatorInterface;
use Cake\ORM\Query;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;

/**
 * Class RankTask
 * @package App\Shell\Task
 * @property RankingsTable $rankingsTable
 * @property Ranking $ranking
 * @property County[] $locations
 * @property Criterion[] $criteria
 * @property School[]|SchoolDistrict[] $subjects
 * @property StatisticsTable $statsTable
 * @property string $context
 * @property ProgressHelper $progress
 * @property array $groupedSubjects
 */
class RankTask extends Shell
{
    private $rankingsTable;
    private $ranking;
    private $subjects = [];
    private $groupedSubjects = [
        'fullData' => [],
        'partialData' => [],
        'noData' => []
    ];
    private $criteria;
    private $statsTable;
    private $context;
    private $progress;

    /**
     * RankTask constructor
     *
     * @param ConsoleIo|null $io IO object
     * @param LocatorInterface|null $locator LocatorInterface object
     */
    public function __construct(ConsoleIo $io = null, LocatorInterface $locator = null)
    {
        parent::__construct($io, $locator);
        $this->rankingsTable = TableRegistry::getTableLocator()->get('Rankings');
        $this->statsTable = TableRegistry::getTableLocator()->get('Statistics');
        $this->progress = $io->helper('Progress');
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

        $this->getSubjects();
        $this->getStats();
        $this->scoreSubjects();
        $this->groupSubjects();
        $this->getRanking();

        return true;
    }

    /**
     * Returns either the schools or districts that are associated with the specified locations
     *
     * @return void
     * @throws \Exception
     */
    private function getSubjects()
    {
        $this->getIo()->out("Finding {$this->context}s...");
        $subjectTable = Context::getTable($this->context);
        $locations = $this->getLocations();
        $this->progress->init([
            'total' => count($locations),
            'width' => 40,
        ]);
        $this->progress->draw();
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
            $this->progress->increment(1);
            $this->progress->draw();
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
        $this->getIo()->out("Grouping {$this->context}s by data availability...");
        $criteria = $this->ranking->formula->criteria;
        $metricCount = count($criteria);

        foreach ($this->subjects as $subject) {
            $subjectStatCount = count($subject->statistics);
            if ($subjectStatCount == $metricCount) {
                $this->groupedSubjects['fullData'][] = $subject;
                continue;
            }

            if ($subjectStatCount > 0) {
                $this->groupedSubjects['partialData'][] = $subject;
                continue;
            }

            $this->groupedSubjects['noData'][] = $subject;
        }
        foreach ($this->groupedSubjects as $group => $subjects) {
            $this->getIo()->out(sprintf(
                ' - %s: %s',
                Inflector::humanize(Inflector::underscore($group)),
                count($subjects)
            ));
        }
    }

    /**
     * Returns grouped array of subjects ordered by their rank, according to the current formula
     *
     * @return array
     */
    private function getRanking()
    {
        return [];
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
    private function getStats()
    {
        $this->getIo()->out('Collecting statistics...');
        $this->progress->init([
            'total' => count($this->subjects),
            'width' => 40,
        ]);
        $this->progress->draw();
        $criteria = $this->ranking->formula->criteria;
        $metricIds = Hash::extract($criteria, '{n}.metric_id');
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
            $this->progress->increment(1);
            $this->progress->draw();
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
        $this->getIo()->out("Scoring {$this->context}s...");
        $outputMsgs = [];
        $criteria = $this->ranking->formula->criteria;
        $this->progress->init([
            'total' => count($this->subjects) * count($criteria),
            'width' => 40,
        ]);
        $this->progress->draw();
        foreach ($criteria as $criterion) {
            $metricId = $criterion->metric_id;
            $weight = $criterion->weight;
            list($minValue, $maxValue) = $this->getValueRange($metricId);
            if (!isset($minValue)) {
                $this->progress->increment(count($this->subjects));
                $this->progress->draw();
                continue;
            }
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
                $this->progress->increment(1);
                $this->progress->draw();
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
}
