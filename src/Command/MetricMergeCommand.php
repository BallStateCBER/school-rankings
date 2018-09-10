<?php
namespace App\Command;

use App\Model\Context\Context;
use App\Model\Entity\Criterion;
use App\Model\Entity\Metric;
use App\Model\Entity\SpreadsheetColumnsMetric;
use App\Model\Entity\Statistic;
use App\Model\Table\CriteriaTable;
use App\Model\Table\MetricsTable;
use App\Model\Table\SpreadsheetColumnsMetricsTable;
use App\Model\Table\StatisticsTable;
use Cake\Cache\Cache;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Console\Exception\StopException;
use Cake\Database\Expression\QueryExpression;
use Cake\Database\Query;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;

/**
 * Class MetricMergeCommand
 * @package App\Command
 * @property array $criteriaToDelete
 * @property array $criteriaToMerge
 * @property array $criteriaToUpdate
 * @property array $sortedCriteria
 * @property array $sortedStats
 * @property array $statsToDelete
 * @property array $statsToMerge
 * @property array $statsToUpdate
 * @property bool $abort
 * @property ConsoleIo $io
 * @property CriteriaTable $criteriaTable
 * @property int $metricIdToRetain
 * @property int[] $metricIdsToDelete
 * @property Metric $metricToDelete
 * @property Metric[] $metricToRetain
 * @property MetricsTable $metricsTable
 * @property SpreadsheetColumnsMetric[] $spreadsheetColumnsToUpdate
 * @property SpreadsheetColumnsMetricsTable $spreadsheetColumnsTable
 * @property StatisticsTable $statisticsTable
 * @property string $context
 */
class MetricMergeCommand extends CommonCommand
{
    private $abort = false;
    private $context;
    private $criteriaTable;
    private $criteriaToDelete;
    private $criteriaToMerge;
    private $criteriaToUpdate;
    private $metricIdsToDelete;
    private $metricIdToRetain;
    private $metricsTable;
    private $metricsToDelete;
    private $metricToRetain;
    private $sortedCriteria;
    private $sortedStats;
    private $spreadsheetColumnsTable;
    private $spreadsheetColumnsToUpdate;
    private $statisticsTable;
    private $statsToDelete;
    private $statsToMerge;
    private $statsToUpdate;

    /**
     * Initializes the command
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * Display help for this console.
     *
     * @param ConsoleOptionParser $parser Console options parser object
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser)
    {
        $parser->addArguments([
            'metricIdsToDelete' => [
                'help' => 'One or more metric IDs or ranges (e.g. "1,3-5,7-10") ' .
                    'to merge into the second argument and delete',
                'required' => true
            ],
            'metricIdToRetain' => [
                'help' => 'A metric ID to merge the first metric(s) into and retain',
                'required' => true
            ],
        ]);

        return $parser;
    }

    /**
     * Attempts to merge the specified metrics, with the first being removed
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return int|null|void
     * @throws \Aura\Intl\Exception
     * @throws \Exception
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        parent::execute($args, $io);
        $this->metricsTable = TableRegistry::getTableLocator()->get('Metrics');
        $this->statisticsTable = TableRegistry::getTableLocator()->get('Statistics');
        $this->criteriaTable = TableRegistry::getTableLocator()->get('Criteria');
        $this->spreadsheetColumnsTable = TableRegistry::getTableLocator()->get('SpreadsheetColumnsMetrics');
        $this->metricIdsToDelete = Utility::parseMultipleIdString($args->getArgument('metricIdsToDelete'));
        $this->metricIdToRetain = $args->getArgument('metricIdToRetain');

        try {
            $this->verifyMetrics();
            if ($this->abort) {
                return;
            }

            $this->collectStatistics();
            if ($this->statsToMerge) {
                $this->checkForStatConflicts();
                $this->io->nl();
                $this->prepareStats();
            }

            $this->collectCriteria();
            if ($this->criteriaToMerge) {
                $this->checkForCriteriaConflicts();
                $this->io->nl();
                $this->prepareCriteria();
            }

            $this->collectSpreadsheetColumns();
            if ($this->spreadsheetColumnsToUpdate) {
                $this->prepareSpreadsheetColumns();
            }

            $this->io->out(
                sprintf(
                    "\n%s #%s will be deleted",
                    __n('Metric', 'Metrics', count($this->metricIdsToDelete)),
                    implode(', ', $this->metricIdsToDelete)
                )
            );
            $continue = $this->io->askChoice('Continue?', ['y', 'n'], 'n');
            if ($continue !== 'y') {
                return;
            }

            if ($this->statsToMerge) {
                $this->io->nl();
                $this->mergeStats();
            }

            if ($this->criteriaToMerge) {
                $this->io->nl();
                $this->mergeCriteria();
            }

            if ($this->spreadsheetColumnsToUpdate) {
                $this->io->nl();
                $this->updateSpreadsheetColumns();
            }

            $this->io->nl();
            $this->deleteMetric();
            $this->clearCache();
            $this->fixTree();

            $this->io->success('Merge successful');
        } catch (StopException $e) {
            return;
        }
    }

    /**
     * Checks that the specified metrics exist
     *
     * @return void
     * @throws \Exception
     */
    private function verifyMetrics()
    {
        $this->io->out('Verifying metrics...');
        $this->metricsToDelete = [];
        $metricNames = [];

        try {
            $metricId = null;
            foreach ($this->metricIdsToDelete as $metricId) {
                $metric = $this->metricsTable->get($metricId);
                if (!$this->context) {
                    $this->context = $metric->context;
                }
                $metricNames[] = $metric->name;

                // Check to see if we can merge these metrics
                if ($this->context && $metric->context != $this->context) {
                    $this->io->out();
                    $this->io->error("Metrics in first argument have mixed school/district contexts");
                    $this->abort();
                }
                $hasChildren = $this->metricsTable->childCount($metric, true) > 0;
                if ($hasChildren) {
                    $this->io->out();
                    $this->io->error(
                        ucwords($this->context) . ' metric #' . $metric->id .
                        ' cannot be merged while it has child-metrics'
                    );
                    $this->abort();
                }

                $this->metricsToDelete[] = $metric;
            }

            $metric = $this->metricsTable->get($this->metricIdToRetain);
            $metricNames[] = $metric->name;

            // Make sure the metric being merged into is valid
            if ($metric->context != $this->context) {
                $this->io->out();
                $this->io->error("Cannot merge $this->context metric(s) into $metric->context metric");
                $this->abort();
            }

            $this->metricToRetain = $metric;
        } catch (RecordNotFoundException $e) {
            $this->io->out();
            $this->io->error('Metric #' . $metricId . ' not found');
            $this->abort();
        }

        $this->io->overwrite('Metrics found');
        $this->metricsTable->setScope($this->context);
        $displayPath = function ($metric) {
            $path = $this->metricsTable->getMetricTreePath($metric->id);
            $pathString = implode(' > ', Hash::extract($path, '{n}.name'));
            $this->io->out(' - Metric #' . $metric->id . ': ' . $pathString);
        };
        foreach ($this->metricsToDelete as $metric) {
            $displayPath($metric);
        }
        $this->io->out('To be merged into:');
        $displayPath($this->metricToRetain);

        // Display note about all of these metric names matching or not
        $metricNames = array_unique($metricNames);
        if (count($metricNames) == 1) {
            $this->io->success('Metric names match');
        } else {
            $this->io->warning('Metric names do not match');
            if (!$this->getConfirmation('Continue?')) {
                $this->abort = true;
            }
        }

        $this->io->out();
    }

    /**
     * Retrieves the first metric's associated statistics
     *
     * @return void
     */
    private function collectStatistics()
    {
        $start = time();
        $this->io->out('Collecting statistics...');
        $locationField = Context::getLocationField($this->context);
        $this->statsToMerge = [];

        $this->makeProgressBar(count($this->metricIdsToDelete));
        $messages = [];
        foreach ($this->metricIdsToDelete as $metricId) {
            $stats = $this->statisticsTable->find()
                ->select([
                    'id',
                    $locationField,
                    'year',
                    'value',
                    'metric_id'
                ])
                ->where(['metric_id' => $metricId])
                ->toArray();
            $this->progress->increment(1)->draw();
            if (!$stats) {
                //$this->io->overwrite('No statistics associated with metric #' . $metricId);

                continue;
            }

            $count = count($stats);
            $messages[] = sprintf(
                '%s %s found for metric #%s',
                $count,
                __n('statistic', 'statistics', $count),
                $metricId
            );

            $this->statsToMerge = array_merge($this->statsToMerge, $stats);
        }
        $this->io->overwrite(sprintf(
            ' - Done %s',
            $this->getDuration($start)
        ));
        foreach ($messages as $message) {
            $this->io->out(' - ' . $message);
        }
    }

    /**
     * Checks for collected statistics sharing locations and years with statistics associated with the second metric
     *
     * @return void
     */
    private function checkForStatConflicts()
    {
        $start = time();
        $locationField = Context::getLocationField($this->context);
        $this->io->out('Checking for stat conflicts...');
        $this->sortedStats = [
            'noConflict' => [],
            'equalValues' => [],
            'inequalValues' => []
        ];
        $this->makeProgressBar(count($this->statsToMerge));
        foreach ($this->statsToMerge as $stat) {
            /** @var Statistic $conflictStat */
            $conflictStat = $this->statisticsTable->find()
                ->select(['value'])
                ->where([
                    $locationField => $stat->$locationField,
                    'year' => $stat->year,
                    'metric_id' => $this->metricIdToRetain
                ])
                ->first();
            $this->progress->increment(1)->draw();
            if ($conflictStat) {
                $key = $conflictStat->value == $stat->value ? 'equalValues' : 'inequalValues';
                $this->sortedStats[$key][] = $stat->id;
                continue;
            }

            $this->sortedStats['noConflict'][] = $stat->id;
        }
        $evCount = count($this->sortedStats['equalValues']);
        $ivCount = count($this->sortedStats['inequalValues']);
        $totalConflicts = $evCount + $ivCount;
        $this->io->overwrite(sprintf(
            ' - Done %s',
            $this->getDuration($start)
        ));
        if ($totalConflicts) {
            $this->io->warning(sprintf(
                ' - %s %s found (statistics with matching years and locations for both of these metrics)',
                $totalConflicts,
                __n('conflict', 'conflicts', $totalConflicts)
            ));
        } else {
            $this->io->success(' - No conflicts found');
        }
        if ($totalConflicts) {
            if ($evCount) {
                $this->io->out(sprintf(
                    ' - %s redundant %s will be deleted',
                    $evCount,
                    __n('stat', 'stats', $evCount)
                ));
            }
            if ($ivCount) {
                $this->io->out(sprintf(
                    ' - %s %s with different values for each metric will be deleted',
                    $ivCount,
                    __n('stat', 'stats', $ivCount)
                ));
            }
        }
        $ncCount = count($this->sortedStats['noConflict']);
        if ($ncCount) {
            $this->io->out(sprintf(
                ' - %s %s will be moved to metric #%s',
                $ncCount,
                __n('stat', 'stats', $ncCount),
                $this->metricIdToRetain
            ));
        }
    }

    /**
     * Prepares update operations and checks that update and delete operations would be valid
     *
     * @return void
     */
    private function prepareStats()
    {
        $start = time();
        $this->io->out('Preparing stats...');

        $this->statsToUpdate = [];
        $this->statsToDelete = [];

        $this->makeProgressBar(count($this->statsToMerge));
        /** @var Statistic $stat */
        foreach ($this->statsToMerge as $stat) {
            $this->progress->increment(1)->draw();
            // Moving
            if (in_array($stat->id, $this->sortedStats['noConflict'])) {
                $stat = $this->statisticsTable->patchEntity($stat, ['metric_id' => $this->metricIdToRetain]);

                $errors = $stat->getErrors();
                $passesRules = $this->statisticsTable->checkRules($stat, 'update');
                if (empty($errors) && $passesRules) {
                    $this->statsToUpdate[] = $stat;
                    continue;
                }

                $msg = "\nCannot update statistic #$stat->id.";
                $msg .= $errors
                    ? "\nDetails:\n" . print_r($errors, true)
                    : ' No details available. (Check for application rule violation)';
                $this->io->error($msg);
                $this->abort();
            }

            // Deleting
            $passesRules = $this->statisticsTable->checkRules($stat, 'delete');
            if ($passesRules) {
                $this->statsToDelete[] = $stat;
                continue;
            }

            $this->io->error("\nCannot delete statistic #$stat->id.");
            $this->abort();
        }

        $this->io->overwrite(sprintf(
            ' - Done %s',
            $this->getDuration($start)
        ));
    }

    /**
     * Collects criteria associated with formulas associated with the first metric
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function collectCriteria()
    {
        $start = time();
        $this->io->out("Collecting formula criteria...");
        $context = $this->context;
        $this->criteriaToMerge = [];

        $this->makeProgressBar(count($this->metricIdsToDelete));
        $messages = [];
        foreach ($this->metricIdsToDelete as $metricId) {
            $criteria = $this->criteriaTable->find()
                ->select(['id', 'formula_id'])
                ->where(['metric_id' => $metricId])
                ->matching('Formulas', function (Query $q) use ($context) {
                    return $q->where(['Formulas.context' => $context]);
                })
                ->toArray();
            $this->progress->increment(1)->draw();
            if (!$criteria) {
                $messages[] = 'No criteria associated with metric #' . $metricId;

                continue;
            }
            $count = count($this->criteriaToMerge);
            $messages[] = sprintf(
                '%s %s found for metric # %s',
                $count,
                __n('criterion', 'criteria', $count),
                $metricId
            );

            $this->criteriaToMerge = array_merge($this->criteriaToMerge, $criteria);
        }
        $this->io->overwrite(sprintf(
            ' - Done %s',
            $this->getDuration($start)
        ));
        foreach ($messages as $message) {
            $this->io->out(' - ' . $message);
        }
    }

    /**
     * Checks for formulas that include criteria associated with both metrics
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function checkForCriteriaConflicts()
    {
        $start = time();
        $this->io->out('Checking for criterion conflicts...');
        $this->sortedCriteria = [
            'noConflict' => [],
            'conflict' => []
        ];
        $this->makeProgressBar(count($this->criteriaToMerge));
        foreach ($this->criteriaToMerge as $criterion) {
            /** @var Criterion $criterion */
            $conflictCriterion = $this->criteriaTable->find()
                ->select(['id'])
                ->where([
                    function (QueryExpression $exp) use ($criterion) {
                        return $exp->notEq('Criteria.id', $criterion->id);
                    },
                    'formula_id' => $criterion->formula_id,
                    'metric_id' => $this->metricIdToRetain
                ])
                ->first();

            $this->progress->increment(1)->draw();

            if ($conflictCriterion) {
                $this->sortedCriteria['conflict'][] = $criterion->id;
                continue;
            }

            $this->sortedCriteria['noConflict'][] = $criterion->id;
        }
        $this->io->overwrite(sprintf(
            ' - Done %s',
            $this->getDuration($start)
        ));
        $conflictCount = count($this->sortedCriteria['conflict']);
        $noConflictCount = count($this->sortedCriteria['noConflict']);
        $this->io->out(sprintf(
            ' - %s %s found %s',
            $conflictCount ? $conflictCount : 'No',
            __n('conflict', 'conflicts', $conflictCount),
            $conflictCount ? '(formulas with both of these metrics)' : null
        ));

        if ($noConflictCount) {
            $this->io->out(sprintf(
                ' - %s %s will be moved to metric #%s',
                $noConflictCount,
                __n('criterion', 'criteria', $noConflictCount),
                $this->metricIdToRetain
            ));
        }
        if ($conflictCount) {
            $this->io->out(sprintf(
                ' - %s redundant %s will be deleted',
                $conflictCount,
                __n('criterion', 'criteria', $conflictCount)
            ));
        }
    }

    /**
     * Runs update and delete operations on statistics associated with the first metric
     *
     * @return void
     */
    private function mergeStats()
    {
        $start = time();
        $this->io->out('Merging stats...');
        $this->makeProgressBar(count($this->statsToUpdate) + count($this->statsToDelete));
        foreach ($this->statsToUpdate as $stat) {
            if (!$this->statisticsTable->save($stat)) {
                $this->io->error('Error updating statistic #' . $stat->id);
                $this->abort();
            }
            $this->progress->increment(1)->draw();
        }

        foreach ($this->statsToDelete as $stat) {
            if (!$this->statisticsTable->delete($stat)) {
                $this->io->error('Error deleting statistic #' . $stat->id);
                $this->abort();
            }
            $this->progress->increment(1)->draw();
        }

        $this->io->overwrite(sprintf(
            ' - Done %s',
            $this->getDuration($start)
        ));
    }

    /**
     * Prepares update operations and checks that update and delete operations would be valid
     *
     * @return void
     */
    private function prepareCriteria()
    {
        $start = time();
        $this->io->out('Preparing criteria...');

        $this->criteriaToUpdate = [];
        $this->criteriaToDelete = [];

        /** @var Criterion $criterion */
        $this->makeProgressBar(count($this->criteriaToMerge));
        foreach ($this->criteriaToMerge as $criterion) {
            $this->progress->increment(1)->draw();

            // Moving
            if (in_array($criterion->id, $this->sortedCriteria['noConflict'])) {
                $criterion = $this->criteriaTable->patchEntity($criterion, ['metric_id' => $this->metricIdToRetain]);

                $errors = $criterion->getErrors();
                $passesRules = $this->criteriaTable->checkRules($criterion, 'update');
                if (empty($errors) && $passesRules) {
                    $this->criteriaToUpdate[] = $criterion;
                    continue;
                }

                $msg = "\nCannot update criterion #$criterion->id.";
                $msg .= $errors
                    ? "\nDetails:\n" . print_r($errors, true)
                    : ' No details available. (Check for application rule violation)';
                $this->io->error($msg);
                $this->abort();
            }

            // Deleting
            $passesRules = $this->criteriaTable->checkRules($criterion, 'delete');
            if ($passesRules) {
                $this->criteriaToDelete[] = $criterion;
                continue;
            }

            $this->io->error("\nCannot delete criterion #$criterion->id.");
            $this->abort();
        }

        $this->io->overwrite(sprintf(
            ' - Done %s',
            $this->getDuration($start)
        ));
    }

    /**
     * Runs update and delete operations on criteria associated with the first metric
     *
     * @return void
     */
    private function mergeCriteria()
    {
        $start = time();
        $this->io->out('Merging criteria...');
        $this->makeProgressBar(count($this->criteriaToUpdate) + count($this->criteriaToDelete));
        foreach ($this->criteriaToUpdate as $criterion) {
            if (!$this->criteriaTable->save($criterion)) {
                $this->io->error('Error updating criterion #' . $criterion->id);
                $this->abort();
            }
            $this->progress->increment(1)->draw();
        }

        foreach ($this->criteriaToDelete as $criterion) {
            if (!$this->criteriaTable->delete($criterion)) {
                $this->io->error('Error deleting criterion #' . $criterion->id);
                $this->abort();
            }
            $this->progress->increment(1)->draw();
        }

        $this->io->overwrite(sprintf(
            ' - Done %s',
            $this->getDuration($start)
        ));
    }

    /**
     * Deletes the first of the two specified metrics
     *
     * @return void
     * @throws \Exception
     */
    private function deleteMetric()
    {
        $this->io->out('Deleting metrics...');
        $this->metricsTable->setScope($this->context);
        foreach ($this->metricsToDelete as $metric) {
            if ($this->metricsTable->delete($metric)) {
                $this->io->out(' - Deleted metric #' . $metric->id);

                continue;
            }

            $this->io->error('Error deleting metric #' . $metric->id);
            $this->abort();
        }
    }

    /**
     * Collects the records in SpreadsheetColumnsMetricsTable that need their metric_id fields updated
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function collectSpreadsheetColumns()
    {
        $this->io->out("Collecting import spreadsheet columns...");
        $this->spreadsheetColumnsToUpdate = $this->spreadsheetColumnsTable->find()
            ->where([
                function (QueryExpression $exp) {
                    return $exp->in('metric_id', $this->metricIdsToDelete);
                }
            ])
            ->toArray();

        if ($this->spreadsheetColumnsToUpdate) {
            $count = count($this->spreadsheetColumnsToUpdate);
            $this->io->overwrite(sprintf(
                " - %s associated spreadsheet %s found",
                $count,
                __n('column', 'columns', $count)
            ));
        } else {
            $this->io->overwrite("No associated spreadsheet columns found");
        }
    }

    /**
     * Prepares spreadsheet column records for updating and aborts on error
     *
     * @return void
     */
    private function prepareSpreadsheetColumns()
    {
        foreach ($this->spreadsheetColumnsToUpdate as &$column) {
            $column = $this->spreadsheetColumnsTable->patchEntity($column, ['metric_id' => $this->metricIdToRetain]);

            $passesRules = $this->spreadsheetColumnsTable->checkRules($column, 'update');
            if (empty($errors) && $passesRules) {
                continue;
            }

            $msg = "\nCannot update spreadsheet column #$column->id.";
            $msg .= $errors
                ? "\nDetails:\n" . print_r($errors, true)
                : ' No details available. (Check for application rule violation)';
            $this->io->error($msg);
            $this->abort();
        }
    }

    /**
     * Updates spreadsheet column records in the database
     *
     * @return void
     */
    private function updateSpreadsheetColumns()
    {
        $this->io->out('Merging spreadsheet columns...');
        foreach ($this->spreadsheetColumnsToUpdate as $column) {
            if (!$this->spreadsheetColumnsTable->save($column)) {
                $this->io->error('Error updating spreadsheet column #' . $column->id);
                $this->abort();
            }
            $this->io->out(' - Updated spreadsheet column  #' . $column->id);
        }
    }

    /**
     * Clears cached metric information
     *
     * @return void
     */
    private function clearCache()
    {
        $this->io->out('Clearing cache...');
        Cache::delete($this->context, 'metrics_api');
        Cache::delete($this->context . '-no-hidden', 'metrics_api');
        $this->io->out(' - Done');
    }

    /**
     * Runs fix-metric-tree command
     *
     * @throws \Exception
     * @return void
     */
    private function fixTree()
    {
        $arguments = new Arguments([], [], []);
        (new FixMetricTreeCommand())->execute($arguments, $this->io);
    }
}
