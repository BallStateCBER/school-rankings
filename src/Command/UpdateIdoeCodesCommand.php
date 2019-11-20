<?php
namespace App\Command;

use App\Model\Entity\School;
use App\Model\Entity\SchoolDistrict;
use App\Model\Table\SchoolDistrictsTable;
use App\Model\Table\SchoolsTable;
use App\Model\Table\StatisticsTable;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;

/**
 * UpdateIdoeCodesCommand command.
 *
 * @property ConsoleIo $io
 * @property SchoolDistrictsTable $districtsTable
 * @property SchoolsTable $schoolsTable
 * @property SchoolsTable|SchoolDistrictsTable $table
 * @property School|SchoolDistrict $record
 * @property string $codeAssociationName
 * @property string $context
 * @property string $description
 * @property string $foreignKey
 * @property string $newCode
 * @property string $oldCode
 * @property string $tableName
 * @property string $year
 */
class UpdateIdoeCodesCommand extends Command
{
    private $codeAssociationName;
    private $context;
    private $description = 'handles a school or corporation switching to a new IDOE code';
    private $foreignKey;
    private $io;
    private $newCode;
    private $oldCode;
    private $record;
    private $table;
    private $tableName;
    private $year;

    /**
     * Initialization method
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * Hook method for defining this command's option parser.
     *
     * @param ConsoleOptionParser $parser The parser to be defined
     * @return ConsoleOptionParser The built parser.
     */
    public function buildOptionParser(ConsoleOptionParser $parser)
    {
        $parser = parent::buildOptionParser($parser);
        $parser->setDescription(ucfirst($this->description));

        return $parser;
    }

    /**
     * Takes a context, and old IDOE code, and a new IDOE code from the user and processes the request
     *
     * @param Arguments $args The command arguments.
     * @param ConsoleIo $io The console io
     * @return void
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->io = $io;
        $this->io->out('This command ' . $this->description);
        $this->io->warning('It is recommended that you back up the database before using this');
        $this->io->out();

        // Collect merge parameters
        $this->getContext();
        $this->getCodes();
        $this->getRecord();
        if (!$this->record) {
            return;
        }

        if ($this->recordFoundWithNewCode()) {
            return;
        }

        // Check if code has already been added
        if ($this->newCodeAlreadyAdded()) {
            $this->io->out();
            $this->io->out("The new IDOE code is already associated with this {$this->context}");

            return;
        }

        // Add new code
        $this->getYear();
        if ($this->addNewCode()) {
            $this->io->out();
            $this->io->success('Done');
        }
    }

    /**
     * Populates $this->context and $this->table
     *
     * @return void
     */
    private function getContext()
    {
        $this->io->out('Which of these has a new Indiana Department of Education code to add?');
        $this->io->out('1: A school');
        $this->io->out('2: A school district');
        $response = $this->io->askChoice('Choose one', [1, 2]);
        $this->context = $response == 1 ? 'school' : 'district';
        $this->tableName = $response == 1 ? 'Schools' : 'SchoolDistricts';
        $this->table = TableRegistry::getTableLocator()->get($this->tableName);
        $this->codeAssociationName = $this->context == 'school' ? 'SchoolCodes' : 'SchoolDistrictCodes';
        $this->foreignKey = $this->context == 'school' ? 'school_id' : 'school_district_id';
    }

    /**
     * Populates $this->oldCode and $this->newCode
     *
     * @return void
     */
    private function getCodes()
    {
        $this->oldCode = $this->io->ask('What was this ' . $this->context . '\'s original IDOE code?');
        $this->newCode = $this->io->ask('What new IDOE code has it been updated to?');
    }

    /**
     * Populates $this->record with a school or district if (=== 1) record is found with $this->oldCode
     *
     * @return void
     */
    private function getRecord()
    {
        $results = $this->table->find('byCode', ['code' => $this->oldCode])->all();

        $count = $results->count();
        if ($count > 1) {
            $this->io->err(sprintf(
                'There are %s %ss associated with the code %s. Manual investigation and correction recommended.',
                $results->count(),
                $this->context,
                $this->oldCode
            ));

            return;
        }

        if ($count == 0) {
            $this->io->err(sprintf(
                'No %ss were found associated with the code %s',
                $this->context,
                $this->oldCode
            ));

            return;
        }

        $this->record = $results->first();
        $this->io->out(sprintf(
            'Old code corresponds to %s #%s (%s)',
            $this->context,
            $this->record->id,
            $this->record->name
        ));
    }

    /**
     * Returns an existing school/district with the new code, or NULL if none is found
     *
     * @return null|School|SchoolDistrict
     */
    private function getExistingWithNewCode()
    {
        $results = $this->table
            ->find('byCode', ['code' => $this->newCode])
            ->where([$this->tableName . '.id !=' => $this->record->id])
            ->select(['id', 'name'])
            ->all();

        $count = $results->count();
        if ($count == 0) {
            return null;
        }

        if ($count == 1) {
            return $results->first();
        }

        $this->io->err(sprintf(
            '%s %ss appear to have been added with the new IDOE code. ' .
                'Manual investigation and correction recommended.',
            $results->count(),
            $this->context
        ));
        $this->abort();

        return null;
    }

    /**
     * Returns TRUE if the new code is already associated with this record
     *
     * @return bool
     */
    private function newCodeAlreadyAdded()
    {
        return $this->table->getAssociation($this->codeAssociationName)->exists([
            $this->foreignKey => $this->record->id,
            'code' => $this->newCode
        ]);
    }

    /**
     * Returns TRUE if $year appears to be a valid year
     *
     * @param string $year The value inputted by the user for 'year'
     * @return bool
     */
    private function validateYear($year)
    {
        /** @var StatisticsTable $statisticsTable */
        $statisticsTable = TableRegistry::getTableLocator()->get('Statistics');

        if ($statisticsTable->validateYear($year) === true) {
            return true;
        }

        $this->io->err('That doesn\'t appear to be a valid year');

        return false;
    }

    /**
     * Attempts to save a new record to the SchoolCodes or SchoolDistrictCodes table and returns TRUE if successful
     *
     * @return bool
     */
    private function addNewCode()
    {
        $association = $this->table->getAssociation($this->codeAssociationName);
        $newRecord = $association->newEntity([
            'code' => $this->newCode,
            $this->foreignKey => $this->record->id,
            'year' => $this->year
        ]);

        if ($association->save($newRecord)) {
            return true;
        }

        $this->io->err('There was an error saving the new code. Details:');
        print_r($newRecord->getErrors());

        return false;
    }

    /**
     * Populates $this->year
     *
     * @return void
     */
    private function getYear()
    {
        do {
            $this->year = $this->io->ask('In roughly what year did this code change take place?');
        } while (!$this->validateYear($this->year));
    }

    /**
     * Displays recommendations and returns TRUE if the new code corresponds to another existing school/district record
     *
     * @return bool
     */
    private function recordFoundWithNewCode()
    {
        $existingRecord = $this->getExistingWithNewCode();
        if (!$existingRecord) {
            return false;
        }

        // Give recommendation
        $this->io->out(sprintf(
            'The new IDOE code is associated with the %s with ID #%s and %s. If a %s changes its code to that ' .
                'of another existing %s, it\'s recommended that the former %s just be marked as closed.',
            $this->context,
            $existingRecord->id,
            ($existingRecord->name == $this->record->name) ? 'the same name' : "the name {$existingRecord->name}",
            $this->context,
            $this->context,
            $this->context
        ));

        $this->io->out(sprintf(
            'The %s with ID #%s is %s.',
            $this->context,
            $this->record->id,
            $this->record->closed ? 'already marked as being closed, so further action is required' : 'marked as open'
        ));

        return true;
    }
}
