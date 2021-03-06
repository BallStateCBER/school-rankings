<?php
namespace App\Command;

use App\Model\Entity\Metric;
use App\Model\Table\MetricsTable;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;
use Exception;

/**
 * Class MetricParentMergeCommand
 * @package App\Command
 * @property ConsoleIo $io
 * @property int $parentMetricIdToRetain
 * @property int[] $parentMetricIdToDelete
 * @property Metric[] $parentMetrics
 * @property MetricsTable $metricsTable
 * @property string $context
 * @property array $mergeOperations
 */
class MetricParentMergeCommand extends CommonCommand
{
    private $context;
    private $metricsTable;
    private $parentMetricIdToDelete;
    private $parentMetricIdToRetain;
    private $parentMetrics;
    private $mergeOperations;

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
     * @return ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser)
    {
        $parser->addArguments([
            'parentMetricIdToDelete' => [
                'help' => 'The ID of a metric that has one level of children that need to be merged and deleted',
                'required' => true,
            ],
            'parentMetricIdToRetain' => [
                'help' => 'The ID of a metric that has one level of children to merge the first group of metrics into',
                'required' => true,
            ],
        ]);

        return $parser;
    }

    /**
     * Attempts to merge the specified metric groups, with the first group being removed
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return int|null|void
     * @throws Exception
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        parent::execute($args, $io);
        $this->parentMetricIdToDelete = $args->getArgument('parentMetricIdToDelete');
        $this->parentMetricIdToRetain = $args->getArgument('parentMetricIdToRetain');
        if ($this->parentMetricIdToDelete == $this->parentMetricIdToRetain) {
            $this->io->error('Metric IDs are identical');

            return;
        }
        $this->metricsTable = TableRegistry::getTableLocator()->get('Metrics');

        $this->getContext();
        $this->verifyMetrics();
        $this->getMergeOperations();
        $this->runMergeOperations();
    }

    /**
     * Checks that the specified metrics exist
     *
     * @return void
     * @throws Exception
     */
    private function verifyMetrics()
    {
        $this->io->out('Verifying metrics...');

        $this->parentMetrics = [
            'delete' => $this->metricsTable
                ->find()
                ->where(['id' => $this->parentMetricIdToDelete])
                ->contain(['ChildMetrics'])
                ->firstOrFail(),
            'retain' => $this->metricsTable
                ->find()
                ->where(['id' => $this->parentMetricIdToRetain])
                ->contain(['ChildMetrics'])
                ->firstOrFail(),
        ];
        foreach ($this->parentMetrics as $action => $parentMetric) {
            if (!$parentMetric->child_metrics) {
                $this->io->out();
                $this->io->error(sprintf(
                    'Metric #%s cannot have its children merged (it doesn\'t have any children)',
                    $parentMetric->id
                ));
                $this->abort();
            }
            foreach ($parentMetric->child_metrics as $childMetric) {
                if ($this->metricsTable->childCount($childMetric)) {
                    $this->io->out();
                    $this->io->error(sprintf(
                        'Child metric #%s cannot be merged while it has children',
                        $childMetric->id
                    ));
                    $this->abort();
                }
            }
        }
        $this->io->out(' - Done');
    }

    /**
     * Normalizes whitespace and capitalization for a metric name
     *
     * Used to prevent differences in whitespace and capitalization from interfering with the matching of names
     *
     * @param string $metricName Metric name
     * @return string
     */
    private function normalizeMetricName($metricName)
    {
        $metricName = strtolower($metricName);
        $metricName = str_replace("\n", ' ', $metricName);
        while (strpos($metricName, '  ') !== false) {
            $metricName = str_replace('  ', ' ', $metricName);
        }
        $metricName = trim($metricName);

        return $metricName;
    }

    /**
     * Collects information about what merge operations will take place
     *
     * @return void
     */
    private function getMergeOperations()
    {
        /* Note: If application rules have been applied correctly, no two metrics with the same parent should
         * have the same name. Otherwise, using metric names as array keys could cause one metric to overwrite
         * another. */
        $this->io->out('Collecting merge operations...');
        $this->mergeOperations = [];

        foreach ($this->parentMetrics['delete']->child_metrics as $childMetric) {
            $normalizedName = $this->normalizeMetricName($childMetric->name);
            $this->mergeOperations[$normalizedName] = [
                'delete' => $childMetric->id,
            ];
        }
        foreach ($this->parentMetrics['retain']->child_metrics as $childMetric) {
            $originalName = $childMetric->name;
            $normalizedName = $this->normalizeMetricName($originalName);

            // No match found
            if (!isset($this->mergeOperations[$normalizedName]['delete'])) {
                continue;
            }

            $this->mergeOperations[$normalizedName]['retain'] = $childMetric->id;
            $this->io->success(sprintf(
                ' - Metric #%s will be merged into #%s (%s)',
                $this->mergeOperations[$normalizedName]['delete'],
                $this->mergeOperations[$normalizedName]['retain'],
                $originalName
            ));
        }

        foreach ($this->mergeOperations as $normalizedName => $metricIds) {
            if (!isset($metricIds['retain'])) {
                $this->io->warning(sprintf(
                    ' - Metric #%s will not be merged (no match found for %s)',
                    $this->mergeOperations[$normalizedName]['delete'],
                    $normalizedName
                ));
                unset($this->mergeOperations[$normalizedName]);
            }
        }

        $this->io->out(' - Done');
    }

    /**
     * Populates $this->context and sets the scope for MetricsTable tree operations
     *
     * @return void
     * @throws Exception
     */
    private function getContext()
    {
        /** @var Metric $metric */
        $metric = $this->metricsTable
            ->find()
            ->select(['context'])
            ->where(['id' => $this->parentMetricIdToDelete])
            ->first();
        $this->context = $metric->context;
        $this->metricsTable->setScope($this->context);
    }

    /**
     * Executes metric-merge for each pair of metric IDs
     *
     * @return void
     * @throws Exception
     */
    private function runMergeOperations()
    {
        if (!$this->mergeOperations) {
            $this->io->out('No applicable merge operations found.');

            return;
        }

        foreach ($this->mergeOperations as $metricName => $operation) {
            $this->io->out();
            $this->io->info(sprintf(
                'Running bin\cake metric-merge %s %s',
                $operation['delete'],
                $operation['retain']
            ));
            $arguments = new Arguments(
                [$operation['delete'], $operation['retain']],
                [],
                ['metricIdsToDelete', 'metricIdToRetain']
            );
            $command = new MetricMergeCommand();
            $command->initialize();
            $command->execute($arguments, $this->io);
        }
    }
}
