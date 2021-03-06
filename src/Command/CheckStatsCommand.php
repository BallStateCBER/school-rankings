<?php
namespace App\Command;

use App\Model\Context\Context;
use App\Model\Entity\Metric;
use App\Model\Entity\Statistic;
use App\Model\Table\MetricsTable;
use App\Model\Table\StatisticsTable;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Database\Expression\QueryExpression;
use Cake\I18n\Time;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;
use Cake\Validation\Validator;

/**
 * Class CheckStatisticsCommand
 * @package App\Command
 * @property array $blankValues
 * @property bool $misformattedPercentStatsFound
 * @property ConsoleIo $io
 * @property int $pageCount
 * @property int $statsCount
 * @property int $statsPageSize
 * @property MetricsTable $metricsTable
 * @property StatisticsTable $statsTable
 * @property Validator $validator
 */
class CheckStatsCommand extends Command
{
    private $io;
    private $metricsTable;
    private $misformattedPercentStatsFound = false;
    private $pageCount;
    private $statsCount;
    private $statsPageSize = 100;
    private $statsTable;
    private $validator;

    /**
     * Initialization method
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();

        $this->metricsTable = TableRegistry::getTableLocator()->get('Metrics');
        $this->statsTable = TableRegistry::getTableLocator()->get('Statistics');
        $this->statsCount = $this->statsTable->find()->count();
        $this->pageCount = ceil($this->statsCount / $this->statsPageSize);
        $this->validator = $this->statsTable->getValidator('default');
    }

    /**
     * Processes location info file and updates the database
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return int|null|void
     * @throws \Exception
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->io = $io;
        $this->checkValidation();
        $this->checkOutOfBoundsPercentages();
        $this->checkSelectableWithoutStats();
        $this->checkUnselectableWithStats();
        $this->checkNoStatsInYear($this->statsTable->getMostRecentYear());
        $this->recoverMetricTrees();

        if ($this->misformattedPercentStatsFound) {
            $this->io->out();
            $this->io->out(
                'Misformatted percentage stats were found. ' .
                'This should be fixed, then check-stats should be re-run.'
            );
            if ($this->getConfirmation('Run fix-percent-values?')) {
                $command = new FixPercentValuesCommand();
                $command->initialize();
                $command->execute($args, $io);

                $this->io->out();
                if ($this->getConfirmation('Re-run check-stats?')) {
                    $this->execute($args, $io);
                }
            }
        }
    }

    /**
     * Creates a progress bar, draws it, and returns it
     *
     * @param int $total Total number of items to be processed
     * @return ProgressHelper
     */
    private function makeProgressBar($total)
    {
        /** @var ProgressHelper $progress */
        $progress = $this->io->helper('Progress');
        $progress->init([
            'total' => $total,
            'width' => 60,
        ]);
        $progress->draw();

        return $progress;
    }

    /**
     * Returns a page of statistics
     *
     * @param int $page Page number
     * @return \Cake\Datasource\ResultSetInterface|Statistic[]
     */
    private function getPaginatedStats($page)
    {
        return $this->statsTable->find()
            ->limit($this->statsPageSize)
            ->page($page)
            ->all();
    }

    /**
     * Displays a string in the format "Took X hours, X minutes, etc.
     *
     * @param int $start Timestamp of beginning of process
     * @return void
     */
    private function displayTimeElapsed(int $start)
    {
        $duration = Time::createFromTimestamp($start)->timeAgoInWords();
        $this->io->out(sprintf(
            'Took %s',
            str_replace(' ago', '', $duration)
        ));
    }

    /**
     * Displays a message and a prompt for a 'y' or 'n' response and returns TRUE if response is 'y'
     *
     * @param string $msg Message to display
     * @param string $default Default selection (leave blank for 'y')
     * @return bool
     */
    private function getConfirmation($msg, $default = 'y')
    {
        return $this->io->askChoice(
            $msg,
            ['y', 'n'],
            $default
        ) == 'y';
    }

    /**
     * Iterates through statistics and checks for validation or application rule violation
     *
     * @return void
     */
    private function checkValidation()
    {
        if (!$this->getConfirmation('Check for application rule / validation errors?')) {
            return;
        }

        $pauseOnError = $this->getConfirmation('Pause on error?');

        $start = time();
        $progress = $this->makeProgressBar($this->pageCount);
        $allErrors = [];
        for ($page = 1; $page <= $this->pageCount; $page++) {
            $stats = $this->getPaginatedStats($page);
            foreach ($stats as $stat) {
                $errors = $this->statsTable
                    ->getValidator('default')
                    ->errors($stat->toArray());
                $ruleViolation = !$this->statsTable->checkRules($stat, 'create');

                if ($errors || $ruleViolation) {
                    $allErrors[$stat->id] = [
                        'errors' => $errors,
                        'ruleViolation' => $ruleViolation,
                    ];
                    if ($pauseOnError) {
                        $this->io->error('Error with stat #' . $stat->id);
                        print_r($stat->getErrors());
                        $this->io->out('Stat values:');
                        print_r($stat->toArray());
                        $this->io->ask('Press enter to continue');
                    }
                }
                unset($stat);
            }
            unset($stats);

            $progress->increment(1)->draw();
        }

        $this->io->out();
        $this->displayTimeElapsed($start);
        if ($allErrors) {
            $this->io->error(sprintf(
                '%s %s with errors found',
                number_format(count($allErrors)),
                __n('stat', 'stat', count($allErrors))
            ));
            foreach ($allErrors as $statId => $info) {
                $this->io->out('Stat #' . $statId);
                if ($info['ruleViolation']) {
                    $this->io->out('Application rules failed');
                }
                if ($info['errors']) {
                    $this->io->out();
                }
                if (!$this->getConfirmation('Continue?')) {
                    break;
                }
            }
        }
    }

    /**
     * Checks for percentage-type metrics with values not in the range from 0 to 1
     *
     * @return void
     */
    private function checkOutOfBoundsPercentages()
    {
        if (!$this->getConfirmation('Check for out-of-bounds percent stats?')) {
            return;
        }

        $this->io->out('Finding percentage metrics...');
        $metrics = $this->metricsTable
            ->find('percents')
            ->select(['id', 'name'])
            ->enableHydration(false)
            ->toArray();

        if (!$metrics) {
            $this->io->out(' - None found');

            return;
        }

        $metricCount = count($metrics);
        $this->io->out(sprintf(
            ' - %s found',
            $metricCount
        ));
        $problematicMetrics = [];
        $this->io->out('Checking for out-of-bounds statistics...');
        $progress = $this->makeProgressBar($metricCount);

        foreach ($metrics as $metric) {
            // Check for misformatted stats and set a flag if found
            if (!$this->misformattedPercentStatsFound) {
                $count = $this->statsTable->find()
                    ->where(function (QueryExpression $exp) {
                        return $exp->notLike('value', '%\\%');
                    })
                    ->count();
                $this->misformattedPercentStatsFound = $count > 0;
            }

            // Find all properly formatted stats (e.g. 75% instead of 0.75)
            $stats = $this->statsTable->find()
                ->select(['id', 'value'])
                ->where([
                    'metric_id' => $metric['id'],
                    function (QueryExpression $exp) {
                        return $exp->like('value', '%\\%');
                    },
                ])
            ->enableHydration(false)
            ->toArray();

            if (!$stats) {
                $progress->increment(1)->draw();
                continue;
            }

            // Count out-of-bounds statistics (ignoring misformatted stats)
            $metric['count'] = 0;
            foreach ($stats as $stat) {
                $value = (float)substr($stat['value'], 0, -1);
                if (!is_numeric($value) || $value < 0 || $value > 100) {
                    $metric['count']++;
                }
            }
            if ($metric['count']) {
                $problematicMetrics[] = $metric;
            }
            $progress->increment(1)->draw();
        }

        if (!$problematicMetrics) {
            $this->io->overwrite(' - No out-of-bounds stats found');

            return;
        }

        // Display results
        $problematicMetricCount = count($problematicMetrics);
        $this->io->overwrite(sprintf(
            ' - %s %s found with out-of-bounds percentage statistics',
            $problematicMetricCount,
            __n('metric', 'metrics', $problematicMetricCount)
        ));
        foreach ($problematicMetrics as &$metric) {
            $metric = str_replace("\n", '\\n', $metric);
        }
        array_unshift($problematicMetrics, ['ID', 'Metric name', 'OOB stat count']);
        $this->io->helper('Table')->output($problematicMetrics);
    }

    /**
     * Checks for selectable metrics with no associated stats
     *
     * @return void
     */
    private function checkSelectableWithoutStats()
    {
        if (!$this->getConfirmation('Check selectable metrics with no statistics?')) {
            return;
        }

        $this->io->out('Finding selectable metrics with no associated statistics...');
        $selectableMetrics = $this->getSelectableMetrics();

        $progress = $this->makeProgressBar(count($selectableMetrics));
        $metricsWithoutStats = [];
        foreach ($selectableMetrics as $metric) {
            if (!$this->hasStats($metric['id'])) {
                $metricsWithoutStats[] = $metric;
            }
            $progress->increment(1)->draw();
        }

        if (!$metricsWithoutStats) {
            $this->io->overwrite(' - None found');

            return;
        }

        $this->io->overwrite(sprintf(
            ' - %s found',
            count($metricsWithoutStats)
        ));

        $this->listMetricResults($metricsWithoutStats);
    }

    /**
     * Checks for unselectable metrics with associated stats
     *
     * @return void
     */
    private function checkUnselectableWithStats()
    {
        if (!$this->getConfirmation('Check for unselectable metrics with associated statistics?')) {
            return;
        }

        $this->io->out('Finding unselectable metrics with associated statistics...');
        $unselectableMetrics = $this->getSelectableMetrics(false);

        $progress = $this->makeProgressBar(count($unselectableMetrics));
        $metricsWithStats = [];
        foreach ($unselectableMetrics as $metric) {
            if ($this->hasStats($metric['id'])) {
                $metricsWithStats[] = $metric;
            }
            $progress->increment(1)->draw();
        }

        if (!$metricsWithStats) {
            $this->io->overwrite(' - None found');

            return;
        }

        $this->io->overwrite(sprintf(
            ' - %s found',
            count($metricsWithStats)
        ));

        $this->listMetricResults($metricsWithStats);
    }

    /**
     * Lists metrics
     *
     * @param Metric[] $metrics Array of metrics
     * @return void
     */
    private function listMetricResults(array $metrics)
    {
        if (!$this->getConfirmation('List results?')) {
            return;
        }

        foreach ($metrics as $metric) {
            $this->io->out(sprintf(
                ' - %s: %s',
                $metric['id'],
                $metric['name']
            ));
        }
    }

    /**
     * Returns a count of statistics associated with a given metric
     *
     * @param int $metricId Metric ID
     * @return bool
     */
    private function hasStats($metricId)
    {
        return $this->statsTable
            ->find()
            ->where(['metric_id' => $metricId])
            ->count() > 0;
    }

    /**
     * Checks for selectable metrics with no stats in the most recent year (for which we have stats)
     *
     * @param int $year Year
     * @return void
     */
    private function checkNoStatsInYear(int $year)
    {
        $msg = "Check for selectable metrics with no stats in the most recent year ($year)?";
        if (!$this->getConfirmation($msg)) {
            return;
        }

        $this->io->out("Finding selectable metrics with no associated statistics in $year...");
        $selectableMetrics = $this->getSelectableMetrics();

        $progress = $this->makeProgressBar(count($selectableMetrics));
        $metricsWithoutStats = [];
        foreach ($selectableMetrics as $metric) {
            $hasStats = $this->statsTable
                ->find()
                ->where(['year' => $year])
                ->count() > 0;
            if (!$hasStats) {
                $metricsWithoutStats[] = $metric;
            }
            $progress->increment(1)->draw();
        }

        if (!$metricsWithoutStats) {
            $this->io->overwrite(' - None found');

            return;
        }

        $this->io->overwrite(sprintf(
            ' - %s found',
            count($metricsWithoutStats)
        ));

        $this->listMetricResults($metricsWithoutStats);
    }

    /**
     * Returns a simple array of selectable metrics (or unselectable if $selectable is FALSE)
     *
     * @param bool $selectable Set to FALSE to get unselectable metrics
     * @return array
     */
    private function getSelectableMetrics($selectable = true)
    {
        return $this->metricsTable
            ->find()
            ->select(['id', 'name'])
            ->where(['selectable' => $selectable])
            ->enableHydration(false)
            ->toArray();
    }

    /**
     * Fixes any structural errors in metric trees
     *
     * @throws \Exception
     * @return void
     */
    private function recoverMetricTrees()
    {
        if (!$this->getConfirmation('Recover metric trees? (fixes any structural errors)')) {
            return;
        }
        foreach (Context::getContexts() as $context) {
            $this->io->out("Recovering tree structure for $context metrics...");

            /** @var MetricsTable $scopedMetricsTable */
            $scopedMetricsTable = TableRegistry::getTableLocator()->get('Metrics');
            $scopedMetricsTable->setScope($context);
            $scopedMetricsTable->recover();
            unset($scopedMetricsTable);

            $this->io->out(' - Done');
        }
    }
}
