<?php
namespace App\Command;

use App\Model\Entity\School;
use App\Model\Entity\SchoolCode;
use App\Model\Entity\SchoolDistrict;
use App\Model\Entity\SchoolDistrictCode;
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
 *
 * @property ConsoleIo $io
 */
class PopulateCodeTablesCommand extends Command
{
    private $io;

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
        $this->io = $io;
        $models = $this->getModels();

        if ($this->getConfirmation() == 'n') {
            return;
        }

        $year = $io->ask('Using what year?', $this->getDefaultYear());
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

                /** @var SchoolCode|SchoolDistrictCode $existingRecord */
                $existingRecord = $destTable
                    ->find()
                    ->where(['code' => $record->code])
                    ->first();

                // There's a record of this code referring to a different school/district
                if ($existingRecord && $existingRecord->$foreignKey != $record->id) {
                    $this->outputMismatchError($existingRecord, $record, $key, $foreignKey);

                    return;
                }

                if ($existingRecord) {
                    continue;
                }

                $newRecord = $destTable->newEntity([
                    'code' => $record->code,
                    $foreignKey => $record->id,
                    'year' => $year,
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

    /**
     * Outputs an error that explains that the same code has been found referring to multiple schools
     *
     * This situation is not expected to happen, and if it does, then some manual correction will probably be necessary.
     * This would likely be the result of a school changing its code and mistakenly being imported as a new school.
     *
     * @param SchoolCode|SchoolDistrictCode $existingRecord A record in the codes table
     * @param School|SchoolDistrict $record A record in the school or districts table
     * @param string $key Either 'school' or 'district'
     * @param string $foreignKey Either the string 'school_id' or 'school_district_id'
     * @return void
     */
    private function outputMismatchError($existingRecord, $record, $key, $foreignKey)
    {
        $this->io->out();
        $this->io->err(sprintf(
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
    }

    /**
     * Returns a boolean indicating if the user is consenting to continue execution
     *
     * @return bool
     */
    private function getConfirmation()
    {
        $response = $this->io->askChoice(
            "Populate school_codes and school_district_codes tables?",
            ['y', 'n'],
            'n'
        );

        return $response == 'y';
    }

    /**
     * Returns the latest year in statistics table
     *
     * @return int
     */
    private function getDefaultYear()
    {
        $statisticsTable = TableRegistry::getTableLocator()->get('Statistics');

        return $statisticsTable->getMostRecentYear();
    }

    /**
     * Returns an array used to iterate through schools and districts
     *
     * @return array
     */
    private function getModels()
    {
        $tableLocator = TableRegistry::getTableLocator();

        return [
            'school' => [
                'source_table' => $tableLocator->get('Schools'),
                'destination_table' => $tableLocator->get('SchoolCodes'),
                'foreign_key' => 'school_id',
            ],
            'district' => [
                'source_table' => $tableLocator->get('SchoolDistricts'),
                'destination_table' => $tableLocator->get('SchoolDistrictCodes'),
                'foreign_key' => 'school_district_id',
            ],
        ];
    }
}
