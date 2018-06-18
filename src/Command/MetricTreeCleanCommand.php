<?php
namespace App\Command;

use App\Model\Context\Context;
use App\Model\Table\MetricsTable;
use App\Model\Table\StatisticsTable;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;

/**
 * Class MetricTreeCleanCommand
 * @package App\Command
 * @property int[] $removableMetricIds
 * @property ProgressHelper $progress
 * @property StatisticsTable $statsTable
 */
class MetricTreeCleanCommand extends Command
{
    private $progress;
    private $removableMetricIds;
    private $statsTable;

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

        /** @var MetricsTable $metricsTable */
        $metricsTable = TableRegistry::getTableLocator()->get('Metrics');
        $this->statsTable = TableRegistry::getTableLocator()->get('Statistics');
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
            foreach ($this->removableMetricIds as $context => $metricIds) {
                $io->out();
                $io->info(ucwords($context) . ' metrics:');
                foreach ($metricIds as $metricId) {
                    foreach ($metricsTable->getMetricTreePath($metricId) as $i => $metricInPath) {
                        $io->out(sprintf(
                            '%s - %s (#%s)',
                            str_repeat('  ', $i),
                            $metricInPath['name'],
                            $metricInPath['id']
                        ));
                    }
                }
            }
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
                $metric = $metricsTable->get($metricId);
                $metricsTable->delete($metric);
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

            if ($hasUnremovableChildren || $this->statsTable->exists(['metric_id' => $metric['id']])) {
                $hasUnremovable = true;
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
}
