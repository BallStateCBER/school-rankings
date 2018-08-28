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
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;

/**
 * Class FixPercentValuesCommand
 * @package App\Command
 * @property array $metrics
 * @property ConsoleIo $io
 * @property int $flaggedMetricsCount
 * @property int $metricCount
 * @property int $statisticsCount
 * @property int $unclassifiedMetricCount
 * @property MetricsTable $metricsTable
 * @property ProgressHelper $progress
 * @property StatisticsTable $statisticsTable
 * @property string $updateResponse
 */
class FixPercentValuesCommand extends Command
{
    const NONPERCENT = 'non-percent';
    const PERCENT = 'percent';
    private $flaggedMetricsCount = 0;
    private $io;
    private $metricCount = 0;
    private $metrics;
    private $metricsTable;
    private $progress;
    private $statisticsCount = 0;
    private $statisticsTable;
    private $unclassifiedMetricCount;
    private $updateResponse;

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
        $this->io = $io;
        $this->metricsTable = TableRegistry::getTableLocator()->get('Metrics');
        $this->progress = $this->io->helper('Progress');
        $this->statisticsTable = TableRegistry::getTableLocator()->get('Statistics');

        $this->getMetrics();
        $this->classifyMetrics();
        $this->saveMetricClassifications();
        $this->displayClassifiedMetrics();

        foreach (Context::getContexts() as $context) {
            $this->findStatistics($context);
            if ($this->statisticsCount && !$this->updateResponse) {
                $this->updateResponse = $this->io->askChoice(
                    'Update misformatted statistics?',
                    ['y', 'n', 'dry run'],
                    'y'
                );
                if ($this->updateResponse == 'n') {
                    return;
                }
            }

            $this->updateStatistics($context);
        }
    }

    /**
     * Retrieves all metrics
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function getMetrics()
    {
        $this->io->out('Finding metrics...');
        foreach (Context::getContexts() as $context) {
            $this->metrics[$context] = $this->metricsTable
                ->find('threaded')
                ->select([
                    'id',
                    'name',
                    'is_percent',
                    'parent_id',
                    'type'
                ])
                ->where(['context' => $context])
                ->toArray();
            $this->metricCount += $this->metricsTable
                ->find()
                ->where(['context' => $context])
                ->count();
        }
        $this->io->out(sprintf(
            ' - Done; %s %s analyzed',
            $this->metricCount,
            __n('metric', 'metrics', $this->metricCount)
        ));
    }

    /**
     * Finds incorrectly formatted statistics that should be formatted as percentages
     *
     * @param string $context 'school' or 'district'
     * @return void
     * @throws \Aura\Intl\Exception
     */
    private function findStatistics($context)
    {
        $this->io->out();
        $this->io->out("Finding $context statistics that need reformatted...");
        $this->progress->init([
            'total' => $this->metricCount,
            'width' => 40,
        ]);
        $this->progress->draw();
        $this->statisticsCount = 0;
        $this->findStatisticsGroup($this->metrics[$context]);
        $this->io->overwrite(sprintf(
            ' - Done; %s misformatted %s found',
            $this->statisticsCount,
            __n('statistic', 'statistics', $this->statisticsCount)
        ));
    }

    /**
     * Updates statistics (or performs a dry run)
     *
     * @param string $context 'school' or 'district'
     * @return void
     */
    private function updateStatistics($context)
    {
        $this->io->out();
        $this->io->out(
            'Updating misformatted statistics...' . ($this->updateResponse == 'dry run' ? ' (dry run)' : '')
        );
        $this->progress->init([
            'total' => $this->statisticsCount,
            'width' => 40,
        ]);
        $this->progress->draw();
        foreach ($this->metrics[$context] as $metric) {
            if (!isset($metric->statistics) || !$metric->statistics) {
                continue;
            }

            foreach ($metric->statistics as $statistic) {
                $this->progress->increment(1)->draw();
                if (!is_numeric($statistic->value)) {
                    $this->io->overwrite('Skipping non-numeric value ' . $statistic->value);
                    continue;
                }

                $originalValue = $statistic->value;
                $newValue = $this->reformatValue($originalValue, $metric->is_percent);
                $statistic = $this->statisticsTable->patchEntity($statistic, ['value' => $newValue]);
                if ($this->updateResponse == 'dry run') {
                    if ($statistic->getErrors()) {
                        $this->io->overwrite('');
                        $this->io->error('Error updating ' . $originalValue . ' to ' . $newValue);
                        print_r($statistic->getErrors());
                        continue;
                    }

                    $this->io->overwrite('Would update ' . $originalValue . ' to ' . $newValue);
                    continue;
                }

                if (!$this->statisticsTable->save($statistic)) {
                    $this->io->overwrite('Error updating statistic. Details: ');
                    $this->io->out();
                    print_r($statistic->getErrors());
                    break 2;
                }
            }
        }
    }

    /**
     * Returns a value converted either to or from percent format
     *
     * @param mixed $value Value to convert
     * @param bool $isPercent TRUE if value should be formatted as a percentage
     * @return string
     * @throws InternalErrorException
     */
    private function reformatValue($value, $isPercent)
    {
        if ($isPercent === true) {
            return Statistic::convertValueToPercent($value);
        }
        if ($isPercent === false) {
            return Statistic::convertValueFromPercent($value);
        }

        throw new InternalErrorException('Non-boolean value for $isPercent');
    }

    /**
     * Returns a function to be used as a find condition to retrieve misformatted statistics
     *
     * @param bool $isPercent TRUE if the statistics SHOULD be percent-formatted
     * @return \Closure
     * @throws InternalErrorException
     */
    private function getMisformattedCondition($isPercent)
    {
        if ($isPercent === true) {
            return function (QueryExpression $exp) {
                return $exp->notLike('value', '%\%%');
            };
        }
        if ($isPercent === false) {
            return function (QueryExpression $exp) {
                return $exp->like('value', '%\%%');
            };
        }

        throw new InternalErrorException('Non-boolean $isPercent value');
    }

    /**
     * Sets the value of is_percent for each metric if it's currently null
     *
     * @return void
     * @throws \Aura\Intl\Exception
     */
    private function classifyMetrics()
    {
        $this->io->out('Classifying metrics with undetermined is_percent status...');
        $this->progress->init([
            'total' => $this->metricCount,
            'width' => 40,
        ]);
        $this->progress->draw();
        foreach (Context::getContexts() as $context) {
            $this->classifyMetricGroup($this->metrics[$context]);
        }
        $this->io->overwrite(sprintf(
            ' - Done; %s unclassified %s found',
            $this->unclassifiedMetricCount,
            __n('metric', 'metrics', $this->unclassifiedMetricCount)
        ));
    }

    /**
     * Sets is_percent for each metric it's null, then runs the same process on each metric's children
     *
     * @param Metric[] $metrics Threaded group of metrics
     * @param bool $parentIsPercent Reflects the is_percent value of this group's parent
     * @return void
     */
    private function classifyMetricGroup(&$metrics, $parentIsPercent = false)
    {
        foreach ($metrics as $metric) {
            $this->progress->increment(1)->draw();
            if (!isset($metric->is_percent)) {
                $isPercent = $parentIsPercent || $this->metricsTable->isPercentMetric($metric->name);
                $this->metricsTable->patchEntity($metric, ['is_percent' => $isPercent]);
                $this->unclassifiedMetricCount++;
            }
            if ($metric->children) {
                $this->classifyMetricGroup($metric->children, $metric->is_percent);
            }
        }
    }

    /**
     * Displays a nested list of metrics and their is_percent status
     *
     * @param Metric[] $metrics Threaded group of metrics
     * @param int $indent Indentation level
     * @return void
     */
    private function displayMetricGroup($metrics, $indent = 1)
    {
        foreach ($metrics as $metric) {
            $msg = sprintf(
                '%s- %s',
                str_repeat(' ', $indent),
                str_replace("\n", ' - ', $metric->name)
            );
            if ($metric->is_percent) {
                $this->io->success($msg);
            } else {
                $this->io->out($msg);
            }

            if ($metric->children) {
                $this->displayMetricGroup($metric->children, $indent + 2);
            }
        }
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
     * Populates each metric's statistics field with misformatted stats and runs the command on each metric's children
     *
     * @param Metric[] $metrics Group of metrics
     * @return void
     */
    private function findStatisticsGroup(&$metrics)
    {
        foreach ($metrics as &$metric) {
            $this->progress->increment(1)->draw();
            $metric->statistics = $this->statisticsTable->find()
                ->select(['id', 'value', 'school_id', 'school_district_id'])
                ->where([
                    'metric_id' => $metric->id,
                    $this->getMisformattedCondition($metric->is_percent)
                ])
                ->all();

            if ($metric->statistics) {
                $this->flaggedMetricsCount++;
                $this->statisticsCount += $metric->statistics->count();
            }

            if ($metric->children) {
                $this->findStatisticsGroup($metric->children);
            }
        }
    }

    /**
     * Displays a tree structure of all metrics, with percentage metrics colored green
     *
     * @return void
     */
    private function displayClassifiedMetrics()
    {
        foreach (Context::getContexts() as $context) {
            if ($this->getConfirmation("Show classified $context metrics? (Percent metrics will be in green)")) {
                $this->displayMetricGroup($this->metrics[$context]);
            }
        }
    }

    /**
     * Updates all dirty metrics in the database
     *
     * @return void
     */
    private function saveMetricClassifications()
    {
        if (!$this->unclassifiedMetricCount) {
            return;
        }
        if (!$this->getConfirmation('Update is_percent metric fields in database?')) {
            return;
        }

        $this->progress->init([
            'total' => $this->metricCount,
            'width' => 40,
        ]);
        $this->progress->draw();
        foreach (Context::getContexts() as $context) {
            $this->saveMetricClassificationsGroup($this->metrics[$context]);
        }
        $this->io->overwrite(' - Done');
    }

    /**
     * Updates a group of metrics in the database
     *
     * @param Metric[] $metrics Group of metrics
     * @return void
     */
    private function saveMetricClassificationsGroup($metrics)
    {
        foreach ($metrics as $metric) {
            $this->progress->increment(1)->draw();
            if ($metric->isDirty('is_percent')) {
                $this->metricsTable->save($metric);
            }
            if ($metric->children) {
                $this->saveMetricClassificationsGroup($metric->children);
            }
        }
    }
}
