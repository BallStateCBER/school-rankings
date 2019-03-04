<?php
namespace App\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\ORM\TableRegistry;

/**
 * Class CleanCityCountyCommand
 * @package App\Command
 */
class CleanCityCountyCommand extends CommonCommand
{

    /**
     * Fixes redundant records in the cities_counties table
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return void
     * @throws \Exception
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        parent::execute($args, $io);

        $table = TableRegistry::getTableLocator()->get('CitiesCounties');

        $io->info('This command will search for and fix redundant records in the cities_counties join table');

        $io->out('Collecting records...');
        $records = $table->find()->all();
        $count = $records->count();
        $io->out(number_format($count) . ' records found');

        $io->out('Analyzing for redundancies...');
        $processedAssociations = [];
        $redundantRecords = [];
        foreach ($records as $record) {
            $key = $record->city_id . '-' . $record->county_id;
            if (array_key_exists($key, $processedAssociations)) {
                $redundantRecords[] = $record;
            } else {
                $processedAssociations[$key] = true;
            }
        }
        $redundantCount = count($redundantRecords);
        $this->io->out();
        $this->io->out(number_format($redundantCount) . ' redundant records found');

        if (!$redundantCount) {
            return;
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

                    return;
                }
                $this->progress->increment(1);
                $this->progress->draw();
            }

            $this->io->out();
            $this->io->success('Finished');
        }
    }
}
