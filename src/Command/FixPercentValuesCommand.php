<?php
namespace App\Command;

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
 */
class FixPercentValuesCommand extends Command
{
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
        $io->out('Finding percentage metrics...');
        $metricsTable = TableRegistry::getTableLocator()->get('Metrics');
        /** @var array $metrics */
        $metrics = $metricsTable->find()
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
        $metricCount = count($metrics);
        $io->out(sprintf(
            ' - %s %s found',
            $metricCount,
            __n('metric', 'metrics', $metricCount)
        ));

        $updateResponse = $io->askChoice('List metric names?', ['y', 'n'], 'n');
        if ($updateResponse == 'y') {
            $names = Hash::extract($metrics, '{n}.name');
            $names = array_values(array_unique($names));
            $count = count($names);
            for ($n = 0; $n < $count; $n += 50) {
                for ($i = 0; $i < 50; $i++) {
                    if (!isset($names[$n + $i])) {
                        break 2;
                    }
                    $name = $names[$n + $i];
                    $name = str_replace("\n", "\n   ", $name);
                    $io->out(' - ' . $name);
                }
                $updateResponse = $io->askChoice('Show more?', ['y', 'n'], 'y');
                if ($updateResponse == 'n') {
                    break;
                }
            }
        }

        if (!$metrics) {
            return;
        }

        $io->out();
        $io->out('Finding statistics that need reformatted...');
        /** @var ProgressHelper $progress */
        $progress = $io->helper('Progress');
        $progress->init([
            'total' => $metricCount,
            'width' => 40,
        ]);
        $progress->draw();
        $statsTable = TableRegistry::getTableLocator()->get('Statistics');
        $flaggedMetricsCount = 0;
        $statisticsCount = 0;
        foreach ($metrics as &$metric) {
            $metric['statistics'] = $statsTable->find()
                ->select(['id', 'value'])
                ->where([
                    'metric_id' => $metric['id'],
                    function (QueryExpression $exp) {
                        return $exp->notLike('value', '%\%%');
                    }
                ])
                ->all();

            if ($metric['statistics']) {
                $flaggedMetricsCount++;
                $statisticsCount += $metric['statistics']->count();
            }
            $progress->increment(1)->draw();
        }

        if ($flaggedMetricsCount) {
            $io->overwrite(sprintf(
                ' - %s %s found for %s %s',
                number_format($statisticsCount),
                __n('statistic', 'statistics', $statisticsCount),
                number_format($flaggedMetricsCount),
                __n('metric', 'metrics', $flaggedMetricsCount)
            ));
        } else {
            $io->overwrite(' - No statistics need updated');

            return;
        }

        $updateResponse = $io->askChoice('Update statistics?', ['y', 'n', 'dry run'], 'y');
        if ($updateResponse == 'n') {
            return;
        }

        $io->out();
        $io->out('Updating statistics...');
        $progress->init([
            'total' => $statisticsCount,
            'width' => 40,
        ]);
        $progress->draw();
        foreach ($metrics as $metric) {
            if (!$metric['statistics']) {
                continue;
            }

            foreach ($metric['statistics'] as $statistic) {
                $progress->increment(1)->draw();
                if (!is_numeric($statistic->value)) {
                    $io->overwrite('Skipping non-numeric value ' . $statistic->value);
                    continue;
                }

                $originalValue = $statistic->value;
                $value = round(($originalValue * 100), 2) . '%';
                $statistic = $statsTable->patchEntity($statistic, compact('value'));
                if ($updateResponse == 'dry run') {
                    if ($statistic->getErrors()) {
                        $io->overwrite('');
                        $io->error('Error updating ' . $originalValue . ' to ' . $value);
                        print_r($statistic->getErrors());
                    } else {
                        $io->overwrite('Would update ' . $originalValue . ' to ' . $value);
                    }
                } elseif (!$statsTable->save($statistic)) {
                    $io->overwrite('Error updating statistic. Details: ');
                    $io->out();
                    print_r($statistic->getErrors());

                    return;
                }
            }
        }
    }
}
