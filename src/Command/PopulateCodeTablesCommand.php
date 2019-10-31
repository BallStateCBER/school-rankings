<?php
namespace App\Command;

use App\Model\Entity\School;
use App\Model\Entity\SchoolDistrict;
use App\Model\Table\SchoolCodesTable;
use App\Model\Table\SchoolDistrictCodesTable;
use App\Model\Table\SchoolDistrictsTable;
use App\Model\Table\SchoolsTable;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;

/**
 * PopulateCodeTablesCommand command.
 */
class PopulateCodeTablesCommand extends Command
{

    /**
     * Hook method for defining this command's option parser.
     *
     * @see https://book.cakephp.org/3.0/en/console-and-shells/commands.html#defining-arguments-and-options
     *
     * @param ConsoleOptionParser $parser The parser to be defined
     * @return ConsoleOptionParser The built parser.
     */
    public function buildOptionParser(ConsoleOptionParser $parser)
    {
        $parser = parent::buildOptionParser($parser);

        return $parser;
    }

    /**
     * Implement this method with your command's logic.
     *
     * @param Arguments $args The command arguments.
     * @param ConsoleIo $io The console io
     * @return void
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $tableLocator = TableRegistry::getTableLocator();
        $models = [
            'school' => [
                'source_table' => $tableLocator->get('Schools'),
                'destination_table' => $tableLocator->get('SchoolCodes'),
                'foreign_key' => 'school_id'
            ],
            'district' => [
                'source_table' => $tableLocator->get('SchoolDistricts'),
                'destination_table' => $tableLocator->get('SchoolDistrictCodes'),
                'foreign_key' => 'school_district_id'
            ]
        ];

        // Get latest year in statistics table
        $statisticsTable = TableRegistry::getTableLocator()->get('Statistics');
        $result = $statisticsTable
            ->find()
            ->select(['year'])
            ->orderDesc('year')
            ->first();
        $year = $result->year;

        $response = $io->askChoice(
            "Populate school_codes and school_district_codes tables?",
            ['y', 'n'],
            'n'
        );
        if ($response == 'n') {
            return;
        }

        $year = $io->ask('Using what year?', $year);
        if (!is_numeric($year) || $year < 2015 || $year > date('Y')) {
            $io->err('That year is outside of the expected range');

            return;
        }

        /** @var ProgressHelper $progress */
        $progress = $io->helper('Progress');

        foreach ($models as $key => $model) {
            $io->info("Processing {$key}s...");
            /** @var SchoolsTable|SchoolDistrictsTable $sourceTable */
            $sourceTable = $model['source_table'];
            $records = $sourceTable
                ->find()
                ->select(['id', 'code'])
                ->all();
            $progress->init([
                'total' => $records->count(),
                'width' => 40,
            ]);
            $progress->draw();

            /** @var SchoolCodesTable|SchoolDistrictCodesTable $destTable */
            $destTable = $model['destination_table'];
            $foreignKey = $model['foreign_key'];
            /** @var School|SchoolDistrict $record */
            foreach ($records as $record) {
                $progress->increment(1);
                $progress->draw();

                $existingRecord = $destTable
                    ->find()
                    ->where(['code' => $record->code])
                    ->first();

                // There's a record of this code referring to a different school/district
                if ($existingRecord && $existingRecord->$foreignKey != $record->id) {
                    $io->out();
                    $io->err(sprintf(
                        'Record #%s in %s codes table has code %s referring to %s #%s, ' .
                        'but in the %s table it refers to %s #%s',
                        $existingRecord->id,
                        $key,
                        $record->code,
                        $key,
                        $existingRecord->$foreignKey,
                        $key . 's',
                        $key,
                        $record->id
                    ));

                    return;
                }

                if ($existingRecord) {
                    continue;
                }

                $newRecord = $destTable->newEntity([
                    'code' => $record->code,
                    $foreignKey => $record->id,
                    'year' => $year
                ]);
                if (!$destTable->save($newRecord)) {
                    $io->err('Error saving this record:');
                    print_r($newRecord);

                    return;
                }
            }

            $io->out();
        }

        $io->success('Done');
    }
}
