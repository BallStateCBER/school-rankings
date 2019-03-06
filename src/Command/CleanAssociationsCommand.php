<?php
namespace App\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;

/**
 * Class CleanAssociationsCommand
 * @package App\Command
 */
class CleanAssociationsCommand extends CommonCommand
{

    /**
     * Fixes redundant records in join tables
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return void
     * @throws \Exception
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        parent::execute($args, $io);

        $io->info('This command will search for and fix redundant records in join tables');

        $tables = [
            'CitiesCounties',
            'SchoolsCities',
            'SchoolsCounties',
            'SchoolsStates',
            'SchoolsGrades',
            'SchoolDistrictsCities',
            'SchoolDistrictsCounties',
            'SchoolDistrictsStates',
        ];
        foreach ($tables as $tableClassName) {
            $table = TableRegistry::getTableLocator()->get($tableClassName);
            $this->io->out();
            $io->info('Table: ' . $table->getTable());
            $io->out('Collecting records...');
            $records = $table->find()->all();
            $count = $records->count();
            $io->out(' - ' . number_format($count) . ' records found');

            $io->out('Analyzing for redundancies...');
            $processedAssociations = [];
            $redundantRecords = [];
            /** @var Entity $record */
            foreach ($records as $record) {
                $fields = $record->toArray();
                unset($fields['id']);
                $key = implode('-', $fields);
                if (array_key_exists($key, $processedAssociations)) {
                    $redundantRecords[] = $record;
                } else {
                    $processedAssociations[$key] = true;
                }
            }
            $redundantCount = count($redundantRecords);
            $this->io->out(' - ' . number_format($redundantCount) . ' redundant records found');

            if (!$redundantCount) {
                continue;
            }

            $response = $this->io->askChoice('Delete redundant records?', ['y', 'n'], 'y');
            if ($response === 'y') {
                $this->io->out('Deleting records...');
                $this->progress->init([
                    'total' => $redundantCount,
                    'width' => 40,
                ]);
                $this->progress->draw();
                foreach ($redundantRecords as $record) {
                    if (!$table->delete($record)) {
                        $this->io->error('Error deleting record #' . $record->id);

                        continue;
                    }
                    $this->progress->increment(1);
                    $this->progress->draw();
                }
                $io->out();
            }
        }
        $this->io->success('Finished');
    }
}
