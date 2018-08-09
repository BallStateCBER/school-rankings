<?php
namespace App\Command;

use App\Model\Table\MetricsTable;
use App\Model\Table\StatisticsTable;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Database\Expression\QueryExpression;
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
                ' - %s %s found for %s %s',
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
        $this->metrics = $this->metricsTable->find()
            ->select(['id', 'name'])
            ->where([
                'OR' => [
                    function (QueryExpression $exp) {
                        return $exp->like('name', '%\%%');
                    },
                    function (QueryExpression $exp) {
                        return $exp->like('name', '%percent%');
                    },
                    function (QueryExpression $exp) {
                        return $exp->like('name', '%rate%');
                    },
                ]
            ])
            ->orderAsc('name')
            ->toArray();
        $this->metricCount = count($this->metrics);
        $this->io->out(sprintf(
            ' - %s %s found',
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

        $names = Hash::extract($this->metrics, '{n}.name');
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
        foreach ($this->metrics as &$metric) {
            $metric['statistics'] = $this->statisticsTable->find()
                ->select(['id', 'value', 'school_id', 'school_district_id'])
                ->where([
                    'metric_id' => $metric['id'],
                    function (QueryExpression $exp) {
                        return $exp->notLike('value', '%\%%');
                    }
                ])
                ->all();

            if ($metric['statistics']) {
                $this->flaggedMetricsCount++;
                $this->statisticsCount += $metric['statistics']->count();
            }
            $this->progress->increment(1)->draw();
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
        foreach ($this->metrics as $metric) {
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
                $value = round(($originalValue * 100), 2) . '%';
                $statistic = $this->statisticsTable->patchEntity($statistic, compact('value'));
                if ($this->updateResponse == 'dry run') {
                    if ($statistic->getErrors()) {
                        $this->io->overwrite('');
                        $this->io->error('Error updating ' . $originalValue . ' to ' . $value);
                        print_r($statistic->getErrors());
                    } else {
                        $this->io->overwrite('Would update ' . $originalValue . ' to ' . $value);
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
