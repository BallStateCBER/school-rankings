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
use Cake\Console\Arguments;
use Cake\Console\Command;
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
class MetricMergeCommand extends Command
{
    private $context;
    private $criteriaTable;
    private $criteriaToDelete;
    private $criteriaToMerge;
    private $criteriaToUpdate;
    private $io;
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
        $this->metricsTable = TableRegistry::getTableLocator()->get('Metrics');
        $this->statisticsTable = TableRegistry::getTableLocator()->get('Statistics');
        $this->criteriaTable = TableRegistry::getTableLocator()->get('Criteria');
        $this->spreadsheetColumnsTable = TableRegistry::getTableLocator()->get('SpreadsheetColumnsMetrics');
        $this->io = $io;
        $this->metricIdsToDelete = Utility::parseMultipleIdString($args->getArgument('metricIdsToDelete'));
        $this->metricIdToRetain = $args->getArgument('metricIdToRetain');

        try {
            $this->verifyMetrics();

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
        $this->io->out('Verifying metrics...', 0);
        $this->metricsToDelete = [];

        try {
            $metricId = null;
            foreach ($this->metricIdsToDelete as $metricId) {
                $metric = $this->metricsTable->get($metricId);

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
                        ucwords($this->context) . ' metric #' . $metric .
                        ' cannot be merged while it has child-metrics'
                    );
                    $this->abort();
                }

                $this->metricsToDelete[] = $metric;
                $this->context = $metric->context;
            }

            $metric = $this->metricsTable->get($this->metricIdToRetain);

            // Make sure the metric being merged into is valid
            if ($metric->context != $this->context) {
                $this->io->out();
                $this->io->error("Cannot merge $this->context metric(s) into $metric->context metric");
                $this->abort();
            }

            $this->metricToRetain = $metric;
        } catch (RecordNotFoundException $e) {
            $this->io->out();
            $this->io->error(ucwords($this->context) . ' metric #' . $metricId . ' not found');
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
        $this->io->out();
    }

    /**
     * Retrieves the first metric's associated statistics
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function collectStatistics()
    {
        $this->io->out('Collecting statistics...', 0);
        $locationField = Context::getLocationField($this->context);
        $this->statsToMerge = [];

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
            if (!$stats) {
                $this->io->overwrite('No statistics associated with metric #' . $metricId);

                continue;
            }

            $count = count($stats);
            $this->io->overwrite(
                $count . __n(' statistic', ' statistics', $count) .
                ' found for metric #' . $metricId
            );

            $this->statsToMerge = array_merge($this->statsToMerge, $stats);
        }
    }

    /**
     * Checks for collected statistics sharing locations and years with statistics associated with the second metric
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function checkForStatConflicts()
    {
        $locationField = Context::getLocationField($this->context);
        $this->io->out('Checking for conflicts...', 0);
        $this->sortedStats = [
            'noConflict' => [],
            'equalValues' => [],
            'inequalValues' => []
        ];
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
            '%s %s found %s',
            $totalConflicts ? $totalConflicts : 'No',
            __n('conflict', 'conflicts', $totalConflicts),
            $totalConflicts ? '(statistics with matching years and locations for both of these metrics)' : null
        ));
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
        $this->io->out('Preparing stats...', 0);

        $this->statsToUpdate = [];
        $this->statsToDelete = [];

        /** @var Statistic $stat */
        foreach ($this->statsToMerge as $stat) {
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

        $this->io->overwrite('Stats prepared');
    }

    /**
     * Collects criteria associated with formulas associated with the first metric
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function collectCriteria()
    {
        $this->io->out("\nCollecting formula criteria...", 0);
        $context = $this->context;
        $this->criteriaToMerge = [];

        foreach ($this->metricIdsToDelete as $metricId) {
            $criteria = $this->criteriaTable->find()
                ->select(['id', 'formula_id'])
                ->where(['metric_id' => $metricId])
                ->matching('Formulas', function (Query $q) use ($context) {
                    return $q->where(['Formulas.context' => $context]);
                })
                ->toArray();
            if (!$criteria) {
                $this->io->overwrite('No criteria associated with metric #' . $metricId);

                continue;
            }
            $count = count($this->criteriaToMerge);
            $this->io->overwrite(
                $count . __n(' criterion', ' criteria', $count) .
                ' found for metric #' . $metricId
            );

            $this->criteriaToMerge = array_merge($this->criteriaToMerge, $criteria);
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
        $this->io->out('Checking for conflicts...', 0);
        $this->sortedCriteria = [
            'noConflict' => [],
            'conflict' => []
        ];
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

            if ($conflictCriterion) {
                $this->sortedCriteria['conflict'][] = $criterion->id;
                continue;
            }

            $this->sortedCriteria['noConflict'][] = $criterion->id;
        }
        $conflictCount = count($this->sortedCriteria['conflict']);
        $noConflictCount = count($this->sortedCriteria['noConflict']);
        $this->io->overwrite(sprintf(
            '%s %s found %s',
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
        $this->io->out('Merging stats...');
        foreach ($this->statsToUpdate as $stat) {
            if (!$this->statisticsTable->save($stat)) {
                $this->io->error('Error updating statistic #' . $stat->id);
                $this->abort();
            }
            $this->io->out(' - Updated stat #' . $stat->id);
        }

        foreach ($this->statsToDelete as $stat) {
            if (!$this->statisticsTable->delete($stat)) {
                $this->io->error('Error deleting statistic #' . $stat->id);
                $this->abort();
            }
            $this->io->out(' - Deleted stat #' . $stat->id);
        }
    }

    /**
     * Prepares update operations and checks that update and delete operations would be valid
     *
     * @return void
     */
    private function prepareCriteria()
    {
        $this->io->out('Preparing criteria...', 0);

        $this->criteriaToUpdate = [];
        $this->criteriaToDelete = [];

        /** @var Criterion $criterion */
        foreach ($this->criteriaToMerge as $criterion) {
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

        $this->io->overwrite('Criteria prepared');
    }

    /**
     * Runs update and delete operations on criteria associated with the first metric
     *
     * @return void
     */
    private function mergeCriteria()
    {
        $this->io->out('Merging criteria...');
        foreach ($this->criteriaToUpdate as $criterion) {
            if (!$this->criteriaTable->save($criterion)) {
                $this->io->error('Error updating criterion #' . $criterion->id);
                $this->abort();
            }
            $this->io->out(' - Updated criterion #' . $criterion->id);
        }

        foreach ($this->criteriaToDelete as $criterion) {
            if (!$this->criteriaTable->delete($criterion)) {
                $this->io->error('Error deleting criterion #' . $criterion->id);
                $this->abort();
            }
            $this->io->out(' - Deleted criterion #' . $criterion->id);
        }
    }

    /**
     * Deletes the first of the two specified metrics
     *
     * @return void
     * @throws \Exception
     */
    private function deleteMetric()
    {
        $this->metricsTable->setScope($this->context);
        foreach ($this->metricsToDelete as $metric) {
            if ($this->metricsTable->delete($metric)) {
                $this->io->out('Metric #' . $metric->id . ' deleted', 2);

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
        $this->io->out("\nCollecting import spreadsheet columns...", 0);
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
                "%s associated spreadsheet %s found",
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
        foreach ($this->spreadsheetColumnsToUpdate as $column) {
            if (!$this->spreadsheetColumnsTable->save($column)) {
                $this->io->error('Error updating spreadsheet column #' . $column->id);
                $this->abort();
            }
            $this->io->out(' - Updated spreadsheet column  #' . $column->id);
        }
    }
}
