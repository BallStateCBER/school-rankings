<?php
namespace App\Command;

use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\Query;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;
use Cake\Utility\Hash;

/**
 * Class SpeedTestElasticsearchCommand
 * @package App\Command
 */
class SpeedTestElasticsearchCommand extends Command
{
    /**
     * Tests speed of MySQL vs. Elasticsearch when looking up a large number of statistics
     *
     * Uses an example scenario of looking up statistics for two metrics and for all 325 schools in Marion county,
     * resulting in a 650-query operation
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return int|null|void
     * @throws \Exception
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $indexName = 'statistics';

        /** @var \Cake\ElasticSearch\Datasource\Connection|\Elastica\Client $connection */
        $connection = ConnectionManager::get('elastic');

        /** @var \Elastica\Index $statisticsIndexRegistry */
        $statisticsIndexRegistry = $connection->getIndex($indexName);

        if (!$statisticsIndexRegistry->exists()) {
            $io->error("$indexName index does not exist");

            return;
        }

        $statisticsTable = TableRegistry::getTableLocator()->get('Statistics');
        $totalStatsCount = $statisticsTable->find()->count();
        $io->out("Total stats in MySQL table: " . number_format($totalStatsCount));

        /** @var \Cake\ElasticSearch\Index $statisticsIndex */
        $statisticsIndex = \Cake\ElasticSearch\IndexRegistry::get($indexName);
        $totalCopiedStats = $statisticsIndex->find()->count();
        $io->out("Total stats in elasticsearch index: " . number_format($totalCopiedStats));

        if ($totalStatsCount == $totalCopiedStats) {
            $io->success('Stat counts match');
        } else {
            $io->warning(sprintf(
                "Note: Only %s of %s have been copied into Elasticsearch. " .
                'Speed should be re-tested after all statistics are copied over.',
                number_format($totalCopiedStats),
                number_format($totalStatsCount)
            ));
        }

        /** @var ProgressHelper $progress */
        $progress = $io->helper('Progress');
        $limit = 10;
        $metricId = 5841;
        $schoolId = 1158;
        $queryCount = 650;

        $io->out();
        if ($io->askChoice('Test MySQL?', ['y', 'n'], 'n') === 'y') {
            $io->info('MySQL:');
            $progress->init([
                'total' => $limit,
                'width' => 40,
            ]);
            $start = microtime(true);
            for ($i = 0; $i < $limit; $i++) {
                $statisticsTable->find()
                    ->select(['id', 'metric_id', 'value', 'year'])
                    ->where([
                        'metric_id' => $metricId,
                        'school_id' => $schoolId
                    ])
                    ->orderDesc('year')
                    ->first();
                $progress->increment(1);
                $progress->draw();
            }
            $mysqlDuration = (microtime(true) - $start) / $limit;
            $mysqlMs = round(($mysqlDuration / 1000), 5);
            $estTotalMinutes = round(($queryCount * $mysqlDuration) / 60, 2);
            $io->overwrite(" - Average query time: $mysqlMs ms");
            $io->out(" - Estimated time for looking up $queryCount stats: $estTotalMinutes minutes");
        }

        $io->out();
        $io->info('Elasticsearch:');
        $start = microtime(true);
        $progress->init([
            'total' => $limit,
            'width' => 40,
        ]);
        for ($i = 0; $i < $limit; $i++) {
            $statisticsIndex->find()
                ->select(['id', 'metric_id', 'school_id', 'value', 'year'])
                ->where([
                    'metric_id' => $metricId,
                    'school_id' => $schoolId
                ])
                ->order(['year' => 'DESC'])
                ->first();
            $progress->increment(1);
            $progress->draw();
        }
        $elasticsearchDuration = (microtime(true) - $start) / $limit;
        $elasticsearchMs = round(($elasticsearchDuration / 1000), 5);
        $estTotalSeconds = round(($queryCount * $elasticsearchDuration), 2);
        $io->overwrite(" - Average query time: $elasticsearchMs ms");
        $io->out(" - Estimated time for looking up $queryCount stats: $estTotalSeconds seconds");

        $metricIds = [5966, 2622];
        $schoolIds = $this->getSchoolIds();
        $queryCount = count($metricIds) * count($schoolIds);
        $start = microtime(true);
        $stats = [];
        $progress->init([
            'total' => $queryCount,
            'width' => 40,
        ]);
        foreach ($metricIds as $metricId) {
            foreach ($schoolIds as $schoolId) {
                $result = $statisticsIndex->find()
                    ->select(['id', 'metric_id', 'school_id', 'value', 'year'])
                    ->where([
                        'metric_id' => $metricId,
                        'school_id' => $schoolId
                    ])
                    ->order(['year' => 'DESC'])
                    ->first();
                $progress->increment(1);
                $progress->draw();
                if ($result) {
                    $stats[$metricId][$schoolId] = $result;
                }
            }
        }
        $elasticsearchDuration = microtime(true) - $start;
        $io->overwrite(sprintf(
            ' - Actual time for looking up %s stats: %s seconds',
            number_format($queryCount),
            round($elasticsearchDuration, 2)
        ));
    }

    /**
     * Returns an array of IDs of all schools in Marion County
     *
     * @return int[]
     */
    private function getSchoolIds()
    {
        $schoolsTable = TableRegistry::getTableLocator()->get('Schools');
        $schools = $schoolsTable
            ->find()
            ->select(['Schools.id'])
            ->matching('Counties', function (Query $q) {
                return $q->where(['Counties.id' => 51]);
            })
            ->toArray();

        return Hash::extract($schools, '{n}.id');
    }
}
