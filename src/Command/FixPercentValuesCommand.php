<?php
namespace App\Command;

use App\Model\Entity\Statistic;
use App\Model\Table\MetricsTable;
use App\Model\Table\StatisticsTable;
use Cake\Cache\Cache;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Database\Expression\QueryExpression;
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;
use Cake\Utility\Hash;

/**
 * Class FixPercentValuesCommand
 * @package App\Command
 * @property ProgressHelper $progress
 * @property int $flaggedMetricsCount;
 * @property ConsoleIo $io;
 * @property int $metricCount;
 * @property array $metrics;
 * @property MetricsTable $metricsTable;
 * @property int $statisticsCount;
 * @property StatisticsTable $statisticsTable;
 * @property string $updateResponse;
 */
class FixPercentValuesCommand extends Command
{
    private $flaggedMetricsCount;
    private $io;
    private $metricCount;
    private $metrics;
    private $metricsTable;
    private $progress;
    private $statisticsCount;
    private $statisticsTable;
    private $updateResponse;
    const PERCENT = 'percent';
    const NONPERCENT = 'non-percent';

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
        $this->showMetrics();

        if (!$this->metrics) {
            return;
        }

        $this->findStatistics();

        if ($this->flaggedMetricsCount) {
            $this->io->overwrite(sprintf(
                ' - %s %s found for %s %s that need reformatted',
                number_format($this->statisticsCount),
                __n('statistic', 'statistics', $this->statisticsCount),
                number_format($this->flaggedMetricsCount),
                __n('metric', 'metrics', $this->flaggedMetricsCount)
            ));
        } else {
            $this->io->overwrite(' - No statistics need updated');

            return;
        }

        $this->updateResponse = $this->io->askChoice('Update statistics?', ['y', 'n', 'dry run'], 'y');
        if ($this->updateResponse == 'n') {
            return;
        }

        $this->updateStatistics();
    }

    /**
     * Gets metrics whose statistics should be formatted as percentages
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function getMetrics()
    {
        $this->io->out('Finding percentage metrics...');
        $results = $this->metricsTable->find()
            ->select(['id', 'name'])
            ->enableHydration(false)
            ->orderAsc('name')
            ->toArray();
        $this->metricCount = count($results);
        $this->progress->init([
            'total' => $this->metricCount,
            'width' => 40,
        ]);
        $this->progress->draw();
        foreach ($results as $metric) {
            $key = $this->isPercentMetric($metric) ? self::PERCENT : self::NONPERCENT;
            $this->metrics[$key][] = $metric;
            $this->progress->increment(1)->draw();
        }

        $this->io->overwrite(sprintf(
            ' - Done; %s %s analyzed',
            $this->metricCount,
            __n('metric', 'metrics', $this->metricCount)
        ));
    }

    /**
     * Optionally shows a list of metrics found
     *
     * @return void
     */
    private function showMetrics()
    {
        $this->updateResponse = $this->io->askChoice('List metric names?', ['y', 'n'], 'n');
        if ($this->updateResponse != 'y') {
            return;
        }

        foreach ($this->metrics as $key => $metrics) {
            $this->io->out();
            $this->io->info(ucwords($key) . ' Metrics');
            $names = Hash::extract($this->metrics[$key], '{n}.name');
            $names = array_values(array_unique($names));
            $count = count($names);
            for ($n = 0; $n < $count; $n += 50) {
                for ($i = 0; $i < 50; $i++) {
                    if (!isset($names[$n + $i])) {
                        break 2;
                    }
                    $name = $names[$n + $i];
                    $name = str_replace("\n", "\n   ", $name);
                    $this->io->out(' - ' . $name);
                }
                $this->updateResponse = $this->io->askChoice('Show more?', ['y', 'n'], 'y');
                if ($this->updateResponse == 'n') {
                    break;
                }
            }
        }
    }

    /**
     * Finds incorrectly formatted statistics that should be formatted as percentages
     *
     * @return void
     */
    private function findStatistics()
    {
        $this->io->out();
        $this->io->out('Finding statistics that need reformatted...');
        $this->progress->init([
            'total' => $this->metricCount,
            'width' => 40,
        ]);
        $this->progress->draw();
        $this->flaggedMetricsCount = 0;
        $this->statisticsCount = 0;
        foreach ($this->metrics as $key => $metrics) {
            foreach ($metrics as &$metric) {
                $metric['statistics'] = $this->statisticsTable->find()
                    ->select(['id', 'value', 'school_id', 'school_district_id'])
                    ->where([
                        'metric_id' => $metric['id'],
                        $this->getMisformattedCondition($key)
                    ])
                    ->all();

                if ($metric['statistics']) {
                    $this->flaggedMetricsCount++;
                    $this->statisticsCount += $metric['statistics']->count();
                }
                $this->progress->increment(1)->draw();
            }
        }
    }

    /**
     * Updates statistics (or performs a dry run)
     *
     * @return void
     */
    private function updateStatistics()
    {
        $this->io->out();
        $this->io->out('Updating statistics...');
        $this->progress->init([
            'total' => $this->statisticsCount,
            'width' => 40,
        ]);
        $this->progress->draw();
        foreach ($this->metrics as $key => $metrics) {
            foreach ($metrics as $metric) {
                if (!$metric['statistics']) {
                    continue;
                }

                foreach ($metric['statistics'] as $statistic) {
                    $this->progress->increment(1)->draw();
                    if (!is_numeric($statistic->value)) {
                        $this->io->overwrite('Skipping non-numeric value ' . $statistic->value);
                        continue;
                    }

                    $originalValue = $statistic->value;
                    $newValue = $this->reformatValue($originalValue, $key);
                    $statistic = $this->statisticsTable->patchEntity($statistic, compact('newValue'));
                    if ($this->updateResponse == 'dry run') {
                        if ($statistic->getErrors()) {
                            $this->io->overwrite('');
                            $this->io->error('Error updating ' . $originalValue . ' to ' . $newValue);
                            print_r($statistic->getErrors());
                        } else {
                            $this->io->overwrite('Would update ' . $originalValue . ' to ' . $newValue);
                        }
                    } elseif (!$this->statisticsTable->save($statistic)) {
                        $this->io->overwrite('Error updating statistic. Details: ');
                        $this->io->out();
                        print_r($statistic->getErrors());

                        return;
                    }
                }
            }
        }
    }

    /**
     * Returns whether or not the specific metric's statistic values should be styled as percents
     *
     * Also populates the "isPercent" cache
     *
     * @param array $metric Metric array
     * @return bool
     */
    private function isPercentMetric($metric)
    {
        $cacheKey = 'metric-' . $metric['id'] . '-isPercent';
        $isPercent = Cache::read($cacheKey);
        if ($isPercent === false) {
            $isPercent = $this->metricsTable->isPercentMetric($metric['name']);
            Cache::write($cacheKey, $isPercent);
        }

        return (bool)$isPercent;
    }

    /**
     * Returns a value converted either to or from percent format
     *
     * @param mixed $value Value to convert
     * @param string $key 'percent' or 'non-percent' key
     * @return string
     * @throws InternalErrorException
     */
    private function reformatValue($value, $key)
    {
        if ($key == self::PERCENT) {
            return Statistic::convertValueToPercent($value);
        }
        if ($key == self::NONPERCENT) {
            return Statistic::convertValueFromPercent($value);
        }

        throw new InternalErrorException('Invalid percent/nonpercent key');
    }

    /**
     * Returns a function to be used as a find condition to retrieve misformatted statistics
     *
     * @param string $key 'percent' or 'non-percent' key
     * @return \Closure
     * @throws InternalErrorException
     */
    private function getMisformattedCondition($key)
    {
        if ($key == self::PERCENT) {
            return function (QueryExpression $exp) {
                return $exp->notLike('value', '%\%%');
            };
        }
        if ($key == self::NONPERCENT) {
            return function (QueryExpression $exp) {
                return $exp->like('value', '%\%%');
            };
        }

        throw new InternalErrorException('Invalid percent/nonpercent key');
    }
}
