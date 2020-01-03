<?php
namespace App\Command;

use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Datasource\Connection;
use Cake\ElasticSearch\Index as ESIndex;
use Cake\ElasticSearch\IndexRegistry;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;
use Elastica\Client;
use Elastica\Index;
use Exception;

/**
 * Class PopulateElasticsearchCommand
 * @package App\Command
 *
 * @property bool $includeAllYears
 * @property bool $includeHidden
 * @property string $indexName
 * @property ConsoleIo $io
 */
class PopulateElasticsearchCommand extends Command
{
    private $includeAllYears;
    private $includeHidden;
    private $indexName;
    private $io;

    /**
     * Copies data from MySQL into Elasticsearch
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return int|null|void
     * @throws Exception
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->io = $io;
        $this->setIncludeHidden();
        $this->setIncludeAllYears();
        $this->setIndexName();

        /** @var Connection|Client $connection */
        $connection = ConnectionManager::get('elastic');

        /** @var Index $statisticsIndexRegistry */
        $statisticsIndexRegistry = $connection->getIndex($this->indexName);

        $exists = $statisticsIndexRegistry->exists();
        if ($exists) {
            if ($io->askChoice('Delete existing index with that name?', ['y', 'n'], 'n') === 'y') {
                $statisticsIndexRegistry->delete();
                $io->out("$this->indexName index deleted");
                $exists = false;
            }
        }

        if (!$exists && $io->askChoice('Create new index?', ['y', 'n'], 'n') === 'y') {
            $indexOptions = [
                'settings' => [
                    'number_of_shards' => 1
                ],
                'mappings' => [
                    '_doc' => [
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
            $io->out("$this->indexName index created");
        }

        $statisticsTable = TableRegistry::getTableLocator()->get('Statistics');
        $totalStatsCount = $statisticsTable->find()->count();
        $io->out("Total stats in MySQL table: " . number_format($totalStatsCount));

        /** @var ESIndex $statisticsIndex */
        $statisticsIndex = IndexRegistry::get($this->indexName);
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
                    new Entity([
                        'id' => $stat->id,
                        'metric_id' => $stat->metric_id,
                        'school_id' => $stat->school_id,
                        'school_district_id' => $stat->school_district_id,
                        'value' => $stat->value,
                        'year' => $stat->year
                    ])
                );
                if (!$saveResult) {
                    $io->error('Error adding stat');
                }
            }

            // Initial estimates
            if ($offset == 0) {
                $duration = microtime(true) - $start;
                $avgSeconds = ($duration / $perPage);
                $estTotalHours = round(($totalStatsCount * $avgSeconds) / 60 / 60, 2);
                $io->out("Estimated time to import all stats: $estTotalHours hours");
            }

            $progress->increment($perPage);
            $progress->draw();
        }

        // Actual duration
        $duration = microtime(true) - $start;
        $finalTotalHours = round($duration / 60 / 60, 2);
        $io->out();
        $io->out("Actual time to import all stats: $finalTotalHours hours");

        $statisticsIndexRegistry->refresh();
        $totalCopiedStats = $statisticsIndex->find()->count();
        $io->out("Updated total stats in elasticsearch index: " . number_format($totalCopiedStats));

        $io->info(
            'To start using this new index, update the Elasticsearch.statisticsIndex configuration value in ' .
            'app.php to ' . $this->indexName
        );
    }

    /**
     * Sets the $includeHidden property
     *
     * @return void
     */
    private function setIncludeHidden()
    {
        $response = $this->io->askChoice(
            'Would you like statistics associated with hidden metrics included?',
            ['y', 'n'],
            'n'
        );
        $this->includeHidden = $response == 'y';
    }

    /**
     * Sets the $includeAllYears property
     *
     * @return void
     */
    private function setIncludeAllYears()
    {
        $response = $this->io->askChoice(
            'Would you like all years of statistics included, rather than only the most recent years?',
            ['y', 'n'],
            'n'
        );
        $this->includeAllYears = $response == 'y';
    }

    /**
     * Sets the $indexName property
     *
     * @return void
     */
    private function setIndexName()
    {
        $indexNameSuggestion = sprintf(
            'statistics%s%s-%s',
            $this->includeHidden ? '' : '-nohidden',
            $this->includeAllYears ? '' : '-recent',
            date('Y-m-d')
        );
        $this->indexName = $this->io->ask('Enter the name of this new index', $indexNameSuggestion);
    }
}
