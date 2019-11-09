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
use Cake\Utility\Hash;

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
 * @property string $newCode
 * @property string $oldCode
 * @property string $tableName
 * @property string $foreignKey
 * @property string $year
 */
class UpdateIdoeCodesCommand extends Command
{
    private $codeAssociationName;
    private $context;
    private $description = 'handles updating a school or corporation\'s IDOE code being updated';
    private $io;
    private $newCode;
    private $oldCode;
    private $record;
    private $table;
    private $tableName;
    private $foreignKey;
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
     * Implement this method with your command's logic.
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

        // Handle merging
        $duplicateRecord = $this->checkForDuplicate();
        if ($duplicateRecord) {
            if ($this->getMergePrepConfirmation($duplicateRecord)) {
                if ($this->merge($duplicateRecord->id, $this->record->id)) {
                    return;
                }
            } else {
                return;
            }
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
     * Checks if a duplicate entry for this school/district was previously added
     *
     * @return null|School|SchoolDistrict
     */
    private function checkForDuplicate()
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
     * Returns TRUE if the user consents to start preparing a merge operation
     *
     * @param School|SchoolDistrict $duplicateRecord The existing record to be merged with $this->record and deleted
     * @return bool
     */
    private function getMergePrepConfirmation($duplicateRecord)
    {
        $hasSameName = $this->record->name == $duplicateRecord->name;
        $this->io->out();
        $this->io->warning(sprintf(
            'It appears that a second %s record with %s was added with the new IDOE code.',
            $this->context,
            $hasSameName ? 'the same name' : 'the name "' . $duplicateRecord->name . '"'
        ));
        $response = $this->io->askChoice(
            sprintf(
                'Merge these %ss? The newer record (ID: %s, code: %s) will be removed.',
                $this->context,
                $duplicateRecord->id,
                $this->newCode
            ),
            ['y', 'n'],
            'n'
        );
        if (!$hasSameName) {
            $response = $this->io->askChoice(
                sprintf(
                    'Are you sure that %s (ID: %s) and %s (ID: %s) are the same %s and should be merged? ' .
                    'The latter record will be removed.',
                    $this->record->name,
                    $this->record->id,
                    $duplicateRecord->name,
                    $duplicateRecord->id,
                    $this->context
                ),
                ['y', 'n'],
                'n'
            );
        }

        return $response == 'y';
    }

    /**
     * Takes a pair of IDs of school or districts to merge into one record
     *
     * @param int $fromId ID of record to remove after merging
     * @param int $intoId ID of record to retain
     * @return bool
     */
    private function merge($fromId, $intoId)
    {
        // Get both records
        $this->io->out();
        $this->io->out('Retrieving records...');
        list($fromRecord, $intoRecord) = $this->getRecordsToMerge($fromId, $intoId);

        // Prepare updates
        $this->io->out();
        $this->io->out('Preparing updates...');
        $intoRecord = $this->mergeScalarFields($fromRecord, $intoRecord);
        $intoRecord = $this->mergeAssociatedObjects($fromRecord, $intoRecord);
        $intoRecord = $this->mergeAssociationArrays($fromRecord, $intoRecord);

        // Report all dirty fields
        $this->io->out();
        $this->io->out('Results...');
        $this->showDirtyFields($intoRecord);

        // Check for errors
        $this->io->out();
        $this->io->out('Checking for errors...');
        if ($intoRecord->getErrors()) {
            $this->io->err('There are errors preventing these updates from taking place:');
            print_r($intoRecord->getErrors());

            return false;
        }
        $this->io->success('No errors found');

        // Get confirmation
        $this->io->out();
        $response = $this->io->askChoice('Proceed with merge?', ['y', 'n'], 'n');
        if ($response == 'n') {
            return false;
        }

        // Update $intoRecord
        $this->io->out();
        $this->io->out("Updating {$this->context} with ID {$intoRecord->id}...");
        if (!$this->table->save($intoRecord)) {
            $this->io->error('There was an error saving those updates. Details: ');
            print_r($intoRecord->getErrors());

            return false;
        }
        $this->io->success('Done');

        // Delete $fromRecord
        $this->io->out();
        $this->io->out("Deleting {$this->context} with ID {$fromRecord->id}...");
        if (!$this->table->delete($fromRecord)) {
            $this->io->error('There was an error deleting that record. Details: ');
            print_r($fromRecord->getErrors());

            return false;
        }
        $this->io->success('Done');

        $this->record = $intoRecord;

        $this->io->out();
        $this->io->out('Merge complete');

        return true;
    }

    /**
     * Updates scalar fields (not arrays or objects) in $intoRecord
     *
     * @param School|SchoolDistrict $fromRecord Record to be removed and source of new values
     * @param School|SchoolDistrict $intoRecord Record to be retained and updated
     * @return School|SchoolDistrict
     */
    private function mergeScalarFields(School $fromRecord, School $intoRecord)
    {
        foreach ($intoRecord->getMergeableFields() as $field) {
            // Don't merge
            if (!is_scalar($fromRecord->$field)) {
                continue;
            }
            if ($fromRecord->$field == $intoRecord->$field) {
                continue;
            }
            if ($fromRecord->$field == '') {
                continue;
            }

            // Merge
            if ($intoRecord->$field == '') {
                $this->io->out(sprintf(
                    'Will update blank %s to "%s"',
                    $field,
                    str_replace("\n", '\n', $fromRecord->$field)
                ));
                $intoRecord = $this->table->patchEntity($intoRecord, [$field => $fromRecord->$field]);
                continue;
            }

            // Have user resolve conflict and merge
            $this->io->out("These {$this->context}s have different values for $field");
            $this->io->out(sprintf(
                ' - 1: Use the new value "%s"',
                $fromRecord->$field
            ));
            $this->io->out(sprintf(
                ' - 2: Keep the old value "%s"',
                $intoRecord->$field
            ));
            $response = $this->io->askChoice('Which value should be kept?', [1, 2]);
            if ($response == 1) {
                $intoRecord = $this->table->patchEntity($intoRecord, [$field => $fromRecord->$field]);
            }
        }

        return $intoRecord;
    }

    /**
     * Updates one-to-one associations in $intoRecord
     *
     * @param School|SchoolDistrict $fromRecord Record to be removed and source of new values
     * @param School|SchoolDistrict $intoRecord Record to be retained and updated
     * @return School|SchoolDistrict
     */
    private function mergeAssociatedObjects(School $fromRecord, School $intoRecord)
    {
        foreach ($intoRecord->getMergeableFields() as $field) {
            // Don't merge
            if (!is_object($fromRecord->$field)) {
                continue;
            }
            if (!$fromRecord->$field) {
                continue;
            }
            if ($fromRecord->$field->id == $intoRecord->$field->id) {
                continue;
            }

            // Merge
            if (!$intoRecord->$field) {
                $this->io->out('Will update ' . $field);
                $intoRecord->$field = $fromRecord->$field;
                $intoRecord->setDirty($field);
                continue;
            }

            // Have user resolve conflict and merge
            $this->io->out("These {$this->context}s have different {$field}s");
            $this->io->out(sprintf(
                ' - 1: %s to remove has value "%s"',
                ucfirst($this->context),
                $fromRecord->$field
            ));
            $this->io->out(sprintf(
                ' - 2: %s to keep has value "%s"',
                ucfirst($this->context),
                $intoRecord->$field
            ));
            $response = $this->io->askChoice('Which value should be kept?', [1, 2]);
            if ($response == 1) {
                $intoRecord = $this->table->patchEntity($intoRecord, [$field => $fromRecord->$field]);
            }
        }

        return $intoRecord;
    }

    /**
     * Updates one-to-many associations in $intoRecord
     *
     * @param School|SchoolDistrict $fromRecord Record to be removed and source of new values
     * @param School|SchoolDistrict $intoRecord Record to be retained and updated
     * @return School|SchoolDistrict
     */
    private function mergeAssociationArrays($fromRecord, $intoRecord)
    {
        foreach ($intoRecord->getMergeableFields() as $field) {
            // Don't merge
            if (!is_array($fromRecord->$field)) {
                continue;
            }
            if (count($fromRecord->$field) == 0) {
                continue;
            }

            $existingIds = Hash::extract($intoRecord->$field, '{n}.id');
            foreach ($fromRecord->$field as $associatedRecord) {
                if (in_array($associatedRecord->id, $existingIds)) {
                    continue;
                }
                $this->io->out("Will add association with record #{$associatedRecord->id} from $field table");
                $intoRecord->$field[] = $associatedRecord;
                $intoRecord->setDirty($field);
            }
        }

        return $intoRecord;
    }

    /**
     * Returns an array of the record to remove, followed by the record to retain
     *
     * @param int $fromId ID of record to merge and delete
     * @param int $intoId ID of record to merge and retain
     * @return School[]|SchoolDistrict[]
     */
    private function getRecordsToMerge(int $fromId, int $intoId)
    {
        $records = [];
        foreach ([$fromId, $intoId] as $id) {
            $records[] = $this->table
                ->find()
                ->where([$this->tableName . '.id' => $id])
                ->contain($this->table->getAssociationNames())
                ->first();
        }

        return $records;
    }

    /**
     * Displays a table of all mergeable fields and whether or not they are dirty
     *
     * @param School|SchoolDistrict $record School or district record
     * @return void
     */
    private function showDirtyFields($record)
    {
        $resultsTable = [['Field', 'Will be updated']];
        foreach ($record->getMergeableFields() as $field) {
            $resultsTable[] = [$field, $record->isDirty($field) ? 'Yes' : 'No'];
        }
        $this->io->helper('Table')->output($resultsTable);
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
}
