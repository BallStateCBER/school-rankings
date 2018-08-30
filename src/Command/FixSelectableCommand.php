<?php
namespace App\Command;

use App\Model\Table\MetricsTable;
use App\Model\Table\StatisticsTable;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\I18n\Time;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;
use Cake\Utility\Hash;

/**
 * Class FixSelectableCommand
 * @package App\Command
 * @property array $metrics
 * @property array $updates
 * @property bool[] $hasStats
 * @property ConsoleIo $io
 * @property MetricsTable $metricsTable
 * @property ProgressHelper $progress
 * @property StatisticsTable $statsTable
 */
class FixSelectableCommand extends CommonCommand
{
    private $metrics;
    private $metricsTable;
    private $statsTable;
    private $hasStats = [];
    private $updates = [
        'selectable' => [],
        'unselectable' => [],
    ];

    /**
     * Fixes statistic values like "0.025" that should be stored as "2.5%"
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return void
     * @throws \Exception
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        parent::execute($args, $io);
        $this->metricsTable = TableRegistry::getTableLocator()->get('Metrics');
        $this->statsTable = TableRegistry::getTableLocator()->get('Statistics');

        $this->getMetrics();
        $this->getStats();
        $this->prepareUpdates();

        if (!$this->updates['selectable'] && !$this->updates['unselectable']) {
            $this->io->out('No metrics need to be updated');

            return;
        }

        $this->previewUpdates();
        $this->processUpdates();
    }

    /**
     * Collects metrics
     *
     * @return void
     * @throws \Exception
     */
    private function getMetrics()
    {
        $start = time();
        $this->io->out('Retrieving metrics...');
        $this->metrics = $this->metricsTable->find()
            ->select(['id', 'name', 'selectable', 'context'])
            ->enableHydration(false)
            ->toArray();
        $duration = Time::createFromTimestamp($start)->timeAgoInWords();
        $this->io->out(sprintf(
            ' - Done (took %s)',
            str_replace(' ago', '', $duration)
        ));
    }

    /**
     * Collects information on whether or not each metric has any associated statistics
     *
     * @return void
     */
    private function getStats()
    {
        $start = time();
        $this->io->out('Analyzing metrics...');
        $this->makeProgressBar(count($this->metrics));
        foreach ($this->metrics as $metric) {
            $this->hasStats[$metric['id']] = $this->statsTable->exists(['metric_id' => $metric['id']]);
            $this->progress->increment(1)->draw();
        }
        $duration = Time::createFromTimestamp($start)->timeAgoInWords();
        $this->io->overwrite(sprintf(
            ' - Done (took %s)',
            str_replace(' ago', '', $duration)
        ));
    }

    /**
     * Prepares database updates
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function prepareUpdates()
    {
        $start = time();
        $this->io->out('Getting updates...');
        $this->makeProgressBar(count($this->metrics));
        foreach ($this->metrics as $metric) {
            if ($metric['selectable'] && !$this->hasStats[$metric['id']]) {
                $this->updates['selectable'][] = $metric;
            }
            if (!$metric['selectable'] && $this->hasStats[$metric['id']]) {
                $this->updates['unselectable'][] = $metric;
            }
            $this->progress->increment(1)->draw();
        }
        $duration = Time::createFromTimestamp($start)->timeAgoInWords();
        $this->io->overwrite(sprintf(
            ' - Done (took %s)',
            str_replace(' ago', '', $duration)
        ));

        foreach ($this->updates as $mode => $metrics) {
            if (!$metrics) {
                continue;
            }
            $count = count($metrics);
            $this->io->out(sprintf(
                '%s %s would be switched to %s',
                number_format($count),
                __n('metric', 'metrics', $count),
                $mode
            ));
        }
    }

    /**
     * Displays a preview of what updates will take place
     *
     * @return void
     */
    private function previewUpdates()
    {
        if (!$this->getConfirmation('Preview updates?')) {
            return;
        }

        $start = time();
        $this->io->out('Preparing preview...');
        $this->makeProgressBar(
            count($this->updates['selectable']) + count($this->updates['unselectable'])
        );
        $tableData = [];
        foreach ($this->updates as $mode => $metrics) {
            foreach ($metrics as $metric) {
                // Display each metric as a path from its root ancestor, e.g. "Foo > Bar > Metric Name"
                $metricPath = $this->metricsTable
                    ->find('path', ['for' => $metric['id']])
                    ->select(['id', 'name'])
                    ->all();
                $metricPathNames = Hash::extract($metricPath['items'], '{n}.name');
                $combinedPathNames = implode(' > ', $metricPathNames);
                $tableData[] = [
                    str_replace("\n", ' - ', $combinedPathNames),
                    $mode
                ];
                $this->progress->increment(1)->draw();
                unset(
                    $combinedPathNames,
                    $metricPath,
                    $metricPathNames
                );
            }
        }
        $duration = Time::createFromTimestamp($start)->timeAgoInWords();
        $this->io->overwrite(sprintf(
            ' - Done (took %s)',
            str_replace(' ago', '', $duration)
        ));

        // Display table in pages of 50 elements
        $tableHeader = ['Metric', 'Will be set to'];
        $page = 0;
        $perPage = 50;
        $rowCount = count($tableData);
        while ($page * $perPage < $rowCount) {
            $pageData = array_slice(
                $tableData,
                $page * $perPage,
                $perPage
            );
            array_unshift($pageData, $tableHeader);
            $this->io->helper('Table')->output($pageData);
            if (!$this->getConfirmation('Next page?')) {
                break;
            }
            $page++;
        }
        unset(
            $duration,
            $page,
            $pageData,
            $perPage,
            $rowCount,
            $start,
            $tableData,
            $tableHeader
        );
    }

    /**
     * Updates the 'selectable' field for metrics in the database
     *
     * @return void
     */
    private function processUpdates()
    {
        if (!$this->getConfirmation('Run updates?')) {
            return;
        }

        foreach ($this->updates as $mode => $metrics) {
            foreach ($metrics as $metricData) {
                $metric = $this->metricsTable->get($metricData['id']);
                $this->metricsTable->patchEntity($metric, [
                    'selectable' => ($mode == 'selectable')
                ]);
                if (!$this->metricsTable->save($metric)) {
                    $this->io->error('Error updating metric #' . $metric->id . '. Details:');
                    print_r($metric->getErrors());
                    $this->abort();
                }
                unset($metric);
            }
        }
    }
}
