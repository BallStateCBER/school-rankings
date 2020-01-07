<?php
namespace App\Command;

use App\Model\Entity\Statistic;
use App\Model\Table\StatisticsTable;
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
 * @property array $indexOptions
 * @property bool $includeAllYears
 * @property bool $includeHidden
 * @property ConsoleIo $io
 * @property ESIndex $statisticsIndex
 * @property float $start
 * @property Index $statisticsIndexRegistry
 * @property int $perPage
 * @property int $totalStatsCount
 * @property StatisticsTable $statisticsTable
 * @property string $indexName
 * @property string[] $importFields
 */
class PopulateElasticsearchCommand extends Command
{
    private $importFields = [
        'id',
        'metric_id',
        'school_id',
        'school_district_id',
        'value',
        'year'
    ];
    private $includeAllYears;
    private $includeHidden;
    private $indexName;
    private $indexOptions = [
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
    private $io;
    private $perPage = 100;
    private $start;
    private $statisticsIndex;
    private $statisticsIndexRegistry;
    private $statisticsTable;
    private $totalStatsCount;

    /**
     * Initialization method
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();

        $this->statisticsTable = TableRegistry::getTableLocator()->get('Statistics');
    }

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
        $this->statisticsIndexRegistry = $connection->getIndex($this->indexName);

        $this->deleteExistingIndex();
        $this->createNewIndex();

        $this->totalStatsCount = $this->statisticsTable->find()->count();
        $this->statisticsIndex = IndexRegistry::get($this->indexName);
        $this->showRecordCounts();

        $continue = $io->askChoice('Import entire stats table into Elasticsearch?', ['y', 'n'], 'n');
        if ($continue !== 'y') {
            return;
        }

        $this->importStats();
        $this->showDuration();
        $this->showNewRecordCount();

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

    /**
     * Deletes an existing index with a matching name if the user consents
     *
     * @return void
     */
    private function deleteExistingIndex()
    {
        $exists = $this->statisticsIndexRegistry->exists();
        if (!$exists) {
            return;
        }

        $response = $this->io->askChoice('Delete existing index with that name?', ['y', 'n'], 'n');
        if ($response !== 'y') {
            return;
        }

        $this->statisticsIndexRegistry->delete();
        $this->io->out("$this->indexName index deleted");
    }

    /**
     * Returns TRUE if a new index should be created (because none by this name exist and the user requests it)
     *
     * @return void
     */
    private function createNewIndex()
    {
        $exists = $this->statisticsIndexRegistry->exists();
        if ($exists) {
            return;
        }

        $response = $this->io->askChoice('Create new index?', ['y', 'n'], 'n');
        if ($response !== 'y') {
            return;
        }

        $this->statisticsIndexRegistry->create($this->indexOptions);
        $this->io->out("$this->indexName index created");
    }

    /**
     * Displays the current number of records / documents in the MySQL table and Elasticsearch index
     *
     * @return void
     */
    private function showRecordCounts()
    {
        $this->io->out("Total stats in MySQL table: " . number_format($this->totalStatsCount));

        $totalCopiedStats = $this->statisticsIndex->find()->count();
        $this->io->out("Total stats in Elasticsearch index: " . number_format($totalCopiedStats));
    }

    /**
     * Displays the updated number of records / documents in the Elasticsearch index
     *
     * @return void
     */
    private function showNewRecordCount()
    {
        $this->statisticsIndexRegistry->refresh();
        $totalCopiedStats = $this->statisticsIndex->find()->count();
        $this->io->out("Updated total stats in Elasticsearch index: " . number_format($totalCopiedStats));
    }

    /**
     * Copies records from the statistics MySQL table to an Elasticsearch index
     *
     * @return void
     */
    private function importStats()
    {
        $this->io->out("Importing all stats...");
        $this->start = microtime(true);

        /** @var ProgressHelper $progress */
        $progress = $this->io->helper('Progress');
        $progress->init([
            'total' => $this->totalStatsCount,
            'width' => 40,
        ]);

        for ($offset = 0; $offset <= $this->totalStatsCount; $offset += $this->perPage) {
            $stats = $this->statisticsTable
                ->find()
                ->select($this->importFields)
                ->limit($this->perPage)
                ->offset($offset)
                ->orderAsc('id');

            foreach ($stats as $i => $stat) {
                $this->importSingleStat($stat);
            }

            if ($offset == 0) {
                $this->showDurationEstimate();
            }

            $progress->increment($this->perPage);
            $progress->draw();
        }
    }

    /**
     * Displays how much time the previous import process took to compelete
     *
     * @return void
     */
    private function showDuration()
    {
        $duration = microtime(true) - $this->start;
        $finalTotalHours = round($duration / 60 / 60, 2);
        $this->io->out();
        $this->io->out("Actual time to import all stats: $finalTotalHours hours");
    }

    /**
     * Returns a new generic Entity with data from the provided Statistic entity
     *
     * @param Statistic $stat Statistic entity
     * @return Entity
     */
    private function getEntityForImport($stat)
    {
        return new Entity([
            'id' => $stat->id,
            'metric_id' => $stat->metric_id,
            'school_id' => $stat->school_id,
            'school_district_id' => $stat->school_district_id,
            'value' => $stat->value,
            'year' => $stat->year
        ]);
    }

    /**
     * Attempts to copy a Statistic entity to the Elasticsearch index and displays details about errors
     *
     * @param Statistic $stat Statistic entity
     * @return void
     */
    private function importSingleStat($stat)
    {
        $saveResult = $this->statisticsIndex->save($this->getEntityForImport($stat));
        if ($saveResult) {
            return;
        }

        $this->io->error('Error adding stat. Details:');
        print_r($saveResult->getErrors());

        $response = $this->io->askChoice('Continue?', ['y', 'n'], 'y');
        if ($response !== 'y') {
            exit;
        }
    }

    /**
     * Shows an initial estimate of how long this import should take
     *
     * @return void
     */
    private function showDurationEstimate()
    {
        $duration = microtime(true) - $this->start;
        $avgSeconds = ($duration / $this->perPage);
        $estTotalHours = round(($this->totalStatsCount * $avgSeconds) / 60 / 60, 2);
        $this->io->out("Estimated time to import all stats: $estTotalHours hours");
    }
}
