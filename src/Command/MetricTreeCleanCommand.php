<?php
namespace App\Command;

use App\Model\Context\Context;
use App\Model\Table\MetricsTable;
use App\Model\Table\SpreadsheetColumnsMetricsTable;
use App\Model\Table\StatisticsTable;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\ORM\Exception\PersistenceFailedException;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;

/**
 * Class MetricTreeCleanCommand
 * @package App\Command
 * @property array $removableMetricIds
 * @property ProgressHelper $progress
 * @property SpreadsheetColumnsMetricsTable $spreadsheetColsTable
 * @property StatisticsTable $statsTable
 */
class MetricTreeCleanCommand extends Command
{
    private $progress;
    private $removableMetricIds;
    private $spreadsheetColsTable;
    private $statsTable;

    /**
     * A flag used to prevent metrics from being deleted if they're associated with spreadshet columns via the
     * spreadsheet_columns_metrics table
     *
     * @var bool
     */
    private $protectSpreadsheetMetrics;

    /**
     * Removes metrics that have no statistics or children with statistics
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return void
     * @throws \Exception
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $io->out(wordwrap(
            'This script will find metrics that can be safely removed from the metric tree because ' .
            'they have no associated statistics or children with associated statistics.'
        ));
        $io->out();

        $io->info(wordwrap(
            'Deleting metrics associated with imported spreadsheet columns may result in those metrics being ' .
            'recreated or errors being thrown when the file is re-imported'
        ));
        $msg = 'Avoid deleting metrics associated with imported spreadsheet columns? (recommended)';
        $this->protectSpreadsheetMetrics = $io->askChoice($msg, ['y', 'n'], 'y') == 'y';

        /** @var MetricsTable $metricsTable */
        $metricsTable = TableRegistry::getTableLocator()->get('Metrics');
        $this->statsTable = TableRegistry::getTableLocator()->get('Statistics');
        $this->spreadsheetColsTable = TableRegistry::getTableLocator()->get('SpreadsheetColumnsMetrics');
        $this->removableMetricIds = [];
        $totalRemovableCount = 0;

        foreach (Context::getContexts() as $context) {
            $io->out("Analyzing $context metric tree...");

            $metricTotalCount = $metricsTable->find()
                ->where(['context' => $context])
                ->count();
            $this->progress = $io->helper('Progress');
            $this->progress->init([
                'total' => $metricTotalCount,
                'width' => 40,
            ]);
            $this->progress->draw();

            $metrics = $metricsTable->find('threaded')
                ->select(['id', 'parent_id'])
                ->where(['context' => $context])
                ->enableHydration(false)
                ->toArray();

            $results = $this->analyzeMetrics($metrics);
            $this->removableMetricIds[$context] = $results['removableMetrics'];
            $totalRemovableCount += count($results['removableMetrics']);

            $io->overwrite(' - Done');
        }

        if (!$totalRemovableCount) {
            $io->out('Metric trees clean. No metrics to remove.');

            return;
        }

        $io->out();
        $io->out(sprintf(
            '%s %s can be removed',
            $totalRemovableCount,
            __n('metric', 'metrics', $totalRemovableCount)
        ));

        $showDetails = $io->askChoice('Show details?', ['y', 'n'], 'n');
        if ($showDetails == 'y') {
            $this->showDetails($io);
        }

        $delete = $io->askChoice('Delete metrics?', ['y', 'n'], 'n');
        if ($delete == 'n') {
            return;
        }

        $io->out();
        $io->out('Deleting metrics...');
        $this->progress = $io->helper('Progress');
        $this->progress->init([
            'total' => $totalRemovableCount,
            'width' => 40,
        ]);
        $this->progress->draw();
        foreach ($this->removableMetricIds as $context => $metricIds) {
            foreach ($metricIds as $metricId) {
                try {
                    $metric = $metricsTable->get($metricId);
                    $metricsTable->deleteOrFail($metric);
                } catch (RecordNotFoundException $e) {
                    $io->error('Cannot delete metric #' . $metricId . '. Metric not found.');
                    $this->abort();
                } catch (PersistenceFailedException $e) {
                    $io->error('Cannot delete metric #' . $metricId . '. Delete operation failed. Details:');
                    $io->nl();
                    print_r($e->getMessage());
                    $io->nl();
                    print_r($metric->getErrors());
                    $this->abort();
                }
                $this->progress->increment(1);
                $this->progress->draw();
            }
        }
        $io->overwrite('- Done');
    }

    /**
     * Returns an array with keys removableMetrics and hasUnremovable
     *
     * @param array $metrics Threaded array of metrics and their children
     * @return array
     */
    private function analyzeMetrics($metrics)
    {
        $hasUnremovable = false;
        $removableMetrics = [];
        foreach ($metrics as $metric) {
            $children = $metric['children'];
            $hasUnremovableChildren = false;

            if ($children) {
                $result = $this->analyzeMetrics($children);
                $removableMetrics = array_merge($removableMetrics, $result['removableMetrics']);
                $hasUnremovableChildren = $result['hasUnremovable'];
            }

            // Save metric if it has statistics or unremovable children
            if ($hasUnremovableChildren || $this->statsTable->exists(['metric_id' => $metric['id']])) {
                $hasUnremovable = true;

            // Save metric if it's a protected metric associated with a spreadsheet column
            } elseif ($this->isProtectedSpreadsheetMetric($metric['id'])) {
                $hasUnremovable = true;

            // Metric can be removed
            } else {
                $removableMetrics[] = $metric['id'];
            }

            $this->progress->increment(1);
            $this->progress->draw();
        }

        return [
            'removableMetrics' => $removableMetrics,
            'hasUnremovable' => $hasUnremovable
        ];
    }

    /**
     * Returns TRUE if this is a metric associated with a spreadsheet column and should not be deleted
     *
     * @param int $metricId Metric ID
     * @return bool
     */
    private function isProtectedSpreadsheetMetric($metricId)
    {
        if (!$this->protectSpreadsheetMetrics) {
            return false;
        }

        return $this->spreadsheetColsTable->exists(['metric_id' => $metricId]);
    }

    /**
     * Shows what metrics will be deleted
     *
     * @param ConsoleIo $io Console IO object
     * @return void
     */
    private function showDetails($io)
    {
        /** @var MetricsTable $metricsTable */
        $metricsTable = TableRegistry::getTableLocator()->get('Metrics');

        foreach ($this->removableMetricIds as $context => $metricIds) {
            $io->out();
            $io->info(ucwords($context) . ' metrics:');
            if (!$metricIds) {
                $io->out(' - None');
                continue;
            }
            foreach ($metricIds as $metricId) {
                foreach ($metricsTable->getMetricTreePath($metricId) as $i => $metricInPath) {
                    $io->out(sprintf(
                        '%s - %s (#%s)',
                        str_repeat('  ', $i),
                        str_replace("\n", ' - ', $metricInPath['name']),
                        $metricInPath['id']
                    ));
                }
            }
        }
    }
}
