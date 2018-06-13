<?php
namespace App\Command;

use App\Model\Context\Context;
use App\Model\Entity\Criterion;
use App\Model\Entity\Metric;
use App\Model\Entity\Statistic;
use App\Model\Table\CriteriaTable;
use App\Model\Table\MetricsTable;
use App\Model\Table\StatisticsTable;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Console\Exception\StopException;
use Cake\Database\Expression\QueryExpression;
use Cake\Database\Query;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\ORM\ResultSet;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;

/**
 * Class MetricMergeCommand
 * @package App\Command
 * @property array $criteriaToDelete
 * @property array $criteriaToUpdate
 * @property array $sortedCriteria
 * @property array $sortedStats
 * @property array $statsToDelete
 * @property array $statsToUpdate
 * @property ConsoleIo $io
 * @property CriteriaTable $criteriaTable
 * @property int[] $metricIds
 * @property Metric[] $metrics
 * @property MetricsTable $metricsTable
 * @property ResultSet $criteriaToMerge
 * @property ResultSet $statsToMerge
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
    private $metricIds;
    private $metrics;
    private $metricsTable;
    private $sortedCriteria;
    private $sortedStats;
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
            'metricIdA' => [
                'help' => 'First metric ID (will be removed)',
                'required' => true
            ],
            'metricIdB' => [
                'help' => 'Second metric ID (will be retained)',
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
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->metricsTable = TableRegistry::getTableLocator()->get('Metrics');
        $this->statisticsTable = TableRegistry::getTableLocator()->get('Statistics');
        $this->criteriaTable = TableRegistry::getTableLocator()->get('Criteria');
        $this->io = $io;
        $this->metricIds = [
            $args->getArgument('metricIdA'),
            $args->getArgument('metricIdB')
        ];

        try {
            $this->verifyMetrics();

            $this->collectStatistics();
            if (!$this->statsToMerge->isEmpty()) {
                $this->checkForStatConflicts();
                $this->io->out();
                $this->prepareStats();
            }

            $this->collectCriteria();
            if (!$this->criteriaToMerge->isEmpty()) {
                $this->checkForCriteriaConflicts();
                $this->io->out();
                $this->prepareCriteria();
            }

            $this->io->out(
                "\nMetric #" . $this->metricIds[0] . ' will be deleted'
            );
            $continue = $this->io->askChoice('Continue?', ['y', 'n'], 'n');
            if ($continue !== 'y') {
                return;
            }

            if (!$this->statsToMerge->isEmpty()) {
                $this->io->out();
                $this->mergeStats();
            }

            if (!$this->criteriaToMerge->isEmpty()) {
                $this->io->out();
                $this->mergeCriteria();
            }

            $this->io->out();
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
     */
    private function verifyMetrics()
    {
        $this->io->out('Verifying metrics...', 0);
        $this->metrics = [];
        foreach ($this->metricIds as $metricId) {
            try {
                $metric = $this->metricsTable->get($metricId);
                if ($this->context && $metric->context != $this->context) {
                    $this->io->out();
                    $this->io->error("Cannot merge a $this->context metric with a $metric->context metric");
                    $this->abort();
                }
                $this->metrics[] = $metric;
                $this->context = $metric->context;
            } catch (RecordNotFoundException $e) {
                $this->io->out();
                $this->io->error(ucwords($this->context) . ' metric #' . $metricId . ' not found');
                $this->abort();
            }
        }

        $hasChildren = $this->metricsTable->childCount($this->metrics[0], true) > 0;
        if ($hasChildren) {
            $this->io->out();
            $this->io->error(
                ucwords($this->context) . ' metric #' . $this->metricIds[0] .
                ' cannot be merged while it has child-metrics'
            );
            $this->abort();
        }

        $this->io->overwrite('Metrics found');
        foreach ($this->metrics as $metric) {
            try {
                $this->metricsTable->setScope($this->context);
            } catch (\Exception $e) {
                $this->io->error('Invalid context: ' . $this->context);
                $this->abort();
            }
            $path = $this->metricsTable->getMetricTreePath($metric->id);
            $pathString = implode(' > ', Hash::extract($path, '{n}.name'));
            $this->io->out(' - Metric #' . $metric->id . ': ' . $pathString);
        }
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
        $this->statsToMerge = $this->statisticsTable->find()
            ->select([
                'id',
                $locationField,
                'year',
                'value'
            ])
            ->where(['metric_id' => $this->metricIds[0]])
            ->all();
        if ($this->statsToMerge->isEmpty()) {
            $this->io->overwrite('No statistics associated with metric #' . $this->metricIds[0]);

            return;
        }

        $count = count($this->statsToMerge);
        $this->io->overwrite(
            $count . __n(' statistic', ' statistics', $count) .
            ' found for metric #' . $this->metricIds[0]
        );
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
                    'metric_id' => $this->metricIds[1]
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
                    ' - %s redundant %s will be deleted from metric #%s',
                    $evCount,
                    __n('stat', 'stats', $evCount),
                    $this->metricIds[0]
                ));
            }
            if ($ivCount) {
                $this->io->out(sprintf(
                    ' - %s %s with different values for each metric will be deleted from metric #%s',
                    $ivCount,
                    __n('stat', 'stats', $ivCount),
                    $this->metricIds[0]
                ));
            }
        }
        $ncCount = count($this->sortedStats['noConflict']);
        if ($ncCount) {
            $this->io->out(sprintf(
                ' - %s %s will be moved from metric #%s to metric #%s',
                $ncCount,
                __n('stat', 'stats', $ncCount),
                $this->metricIds[0],
                $this->metricIds[1]
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
                $stat = $this->statisticsTable->patchEntity($stat, ['metric_id' => $this->metricIds[1]]);

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
        $this->criteriaToMerge = $this->criteriaTable->find()
            ->select(['id', 'formula_id'])
            ->where(['metric_id' => $this->metricIds[0]])
            ->matching('Formulas', function (Query $q) use ($context) {
                return $q->where(['Formulas.context' => $context]);
            })
            ->all();
        if ($this->criteriaToMerge->isEmpty()) {
            $this->io->overwrite('No criteria associated with metric #' . $this->metricIds[0]);

            return;
        }

        $count = count($this->criteriaToMerge);
        $this->io->overwrite(
            $count . __n(' criterion', ' criteria', $count) .
            ' found for metric #' . $this->metricIds[0]
        );
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
                    'metric_id' => $this->metricIds[1]
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
                ' - %s %s will be moved from metric #%s to metric #%s',
                $noConflictCount,
                __n('criterion', 'criteria', $noConflictCount),
                $this->metricIds[0],
                $this->metricIds[1]
            ));
        }
        if ($conflictCount) {
            $this->io->out(sprintf(
                ' - %s redundant %s using metric #%s will be deleted',
                $conflictCount,
                __n('criterion', 'criteria', $conflictCount),
                $this->metricIds[0]
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
                $criterion = $this->criteriaTable->patchEntity($criterion, ['metric_id' => $this->metricIds[1]]);

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
     */
    private function deleteMetric()
    {
        if ($this->metricsTable->delete($this->metrics[0])) {
            $this->io->out('Metric #' . $this->metricIds[0] . ' deleted', 2);

            return;
        }

        $this->io->error('Error deleting metric #' . $this->metricIds[0]);
        $this->abort();
    }
}
