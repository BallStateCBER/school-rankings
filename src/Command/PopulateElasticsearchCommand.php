<?php
namespace App\Command;

use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;

/**
 * Class PopulateElasticsearchCommand
 * @package App\Command
 */
class PopulateElasticsearchCommand extends Command
{
    /**
     * Copies data from MySQL into Elasticsearch
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

        $exists = $statisticsIndexRegistry->exists();
        if ($exists) {
            if ($io->askChoice('Delete existing index?', ['y', 'n'], 'n') === 'y') {
                $statisticsIndexRegistry->delete();
                $io->out("$indexName index deleted");
                $exists = false;
            }
        }

        if (!$exists && $io->askChoice('Create new index?', ['y', 'n'], 'n') === 'y') {
            $indexOptions = [
                'settings' => [
                    'number_of_shards' => 1
                ],
                'mappings' => [
                    'statistics_test' => [
                        'properties' => [
                            'id' => ['type' => 'long'],
                            'metric_id' => ['type' => 'long'],
                            'school_id' => ['type' => 'long'],
                            'school_district_id' => ['type' => 'long'],
                            'value' => ['type' => 'keyword'],
                            'year' => ['type' => 'integer']
                        ]
                    ]
                ]
            ];
            $statisticsIndexRegistry->create($indexOptions);
            $io->out("$indexName index created");
        }

        $statisticsTable = TableRegistry::getTableLocator()->get('Statistics');
        $totalStatsCount = $statisticsTable->find()->count();
        $io->out("Total stats in MySQL table: " . number_format($totalStatsCount));

        /** @var \Cake\ElasticSearch\Index $statisticsIndex */
        $statisticsIndex = \Cake\ElasticSearch\IndexRegistry::get($indexName);
        $totalCopiedStats = $statisticsIndex->find()->count();
        $io->out("Total stats in elasticsearch index: " . number_format($totalCopiedStats));

        $continue = $io->askChoice('Import entire stats table into elasticsearch?', ['y', 'n'], 'n');
        if ($continue != 'y') {
            return;
        }

        $io->out("Importing all stats...");

        /** @var ProgressHelper $progress */
        $progress = $io->helper('Progress');
        $progress->init([
            'total' => $totalStatsCount,
            'width' => 40,
        ]);

        $start = microtime(true);
        $perPage = 100;
        for ($offset = 0; $offset <= $totalStatsCount; $offset += $perPage) {
            $stats = $statisticsTable
                ->find()
                ->select([
                    'id',
                    'metric_id',
                    'school_id',
                    'school_district_id',
                    'value',
                    'year'
                ])
                ->limit($perPage)
                ->offset($offset)
                ->orderAsc('id');

            foreach ($stats as $i => $stat) {
                $saveResult = $statisticsIndex->save(
                    new \Cake\ORM\Entity([
                        'id' => $stat->id,
                        'metric_id' => $stat->metric_id,
                        'school_id' => $stat->school_id,
                        'school_district_id' => $stat->school_district_id,
                        'value' => $stat->value,
                        'year' => $stat->year
                    ])
                );
                if ($saveResult) {
                    //$io->out("Stat record $i added");
                } else {
                    $io->error('Error adding stat');
                }
            }

            // Initial estimates
            if ($offset == 0) {
                $duration = microtime(true) - $start;
                $avgSeconds = ($duration / $perPage);
                $avgMs = round($avgSeconds / 1000, 2);
                $io->out("Average duration of a write: $avgMs ms");
                $estTotalSeconds = round($totalStatsCount * $avgSeconds);
                $estTotalMinutes = round($estTotalSeconds / 60);
                $estTotalHours = round($estTotalMinutes / 60);
                $io->out("Estimated time to import all stats:");
                $io->out(" - $estTotalSeconds seconds");
                $io->out(" - $estTotalMinutes minutes");
                $io->out(" - $estTotalHours hours");
            }

            $progress->increment($perPage);
            $progress->draw();
        }

        // Actual duration
        $duration = microtime(true) - $start;
        $finalTotalSeconds = round($duration);
        $finalTotalMinutes = round($finalTotalSeconds / 60);
        $finalTotalHours = round($finalTotalMinutes / 60);
        $io->out("Actual time to import all stats:");
        $io->out(" - $finalTotalSeconds seconds");
        $io->out(" - $finalTotalMinutes minutes");
        $io->out(" - $finalTotalHours hours");

        $statisticsIndexRegistry->refresh();
        $totalCopiedStats = $statisticsIndex->find()->count();
        $io->out("Updated total stats in elasticsearch index: " . number_format($totalCopiedStats));
    }
}
