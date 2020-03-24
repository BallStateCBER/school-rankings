<?php
namespace App\Command;

use App\Model\Context\Context;
use App\Model\Entity\School;
use App\Model\Entity\SchoolDistrict;
use App\Model\Table\SchoolDistrictsTable;
use App\Model\Table\SchoolsTable;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;

/**
 * Class LocationMergeCommand
 * @package App\Command
 *
 * @property string $description
 * @property string $context
 * @property string $tableName
 * @property SchoolsTable|SchoolDistrictsTable $table
 * @property School|SchoolDistrict $locationToRemove
 * @property School|SchoolDistrict $locationToKeep
 */
class LocationMergeCommand extends CommonCommand
{
    private $description = 'handles merging two school or corporation records into one';
    private $context;
    private $table;
    private $tableName;
    private $locationToRemove;
    private $locationToKeep;

    /**
     * Initializes the command
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * Display help for this console.
     *
     * @param ConsoleOptionParser $parser Console options parser object
     * @return ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser)
    {
        $parser = parent::buildOptionParser($parser);
        $parser->setDescription(ucfirst($this->description));

        return $parser;
    }

    /**
     * Handles a request to merge two school or corporation records together
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return void
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        parent::execute($args, $io);
        $this->io->out('This command ' . $this->description);
        $this->io->warning(
            'It is strongly recommended that you back up the database before using this, ' .
            'and to only merge records if one was added in error and refers to the same school or district'
        );

        $this->getContext();
        $this->getLocationToRemove();
        $this->getLocationToKeep();

        if (!$this->getMergeConfirmation()) {
            return;
        }

        $this->merge();
    }

    /**
     * Populates $this->context and $this->table
     *
     * @return void
     */
    private function getContext()
    {
        $this->io->out('Which of these needs to be merged?');
        $this->io->out('1: Two schools');
        $this->io->out('2: Two school districts');
        $response = $this->io->askChoice('Choose one', [1, 2]);
        $this->context = $response == 1 ? Context::SCHOOL_CONTEXT : Context::DISTRICT_CONTEXT;
        $this->tableName = $response == 1 ? 'Schools' : 'SchoolDistricts';
        $this->table = TableRegistry::getTableLocator()->get($this->tableName);
    }

    /**
     * Returns TRUE if the user consents to start preparing a merge operation
     *
     * @return bool
     */
    private function getMergeConfirmation()
    {
        $this->io->out();
        $response = $this->io->askChoice(
            sprintf(
                'Are you sure you want to merge these? ' .
                'The first %s (#%s) will be removed. ' .
                'ONLY do this if both records refer to the same %s and were duplicated in error.',
                $this->context,
                $this->locationToRemove->id,
                $this->context
            ),
            ['y', 'n'],
            'n'
        );

        return $response == 'y';
    }

    /**
     * Merges two school or districts into one record
     *
     * @return void
     */
    private function merge()
    {
        // Prepare updates
        $this->io->out();
        $this->io->out('Preparing updates...');
        $this->mergeScalarFields();
        $this->mergeAssociatedObjects();
        $this->mergeAssociationArrays();

        // Report all dirty fields
        $this->io->out();
        $this->io->out('Results...');
        $this->showDirtyFields($this->locationToKeep);

        // Check for errors
        $this->io->out();
        $this->io->out('Checking for errors...');
        if ($this->locationToKeep->getErrors()) {
            $this->io->err('There are errors preventing these updates from taking place:');
            print_r($this->locationToKeep->getErrors());

            return;
        }
        $this->io->success('No errors found');

        // Get confirmation
        $this->io->out();
        $response = $this->io->askChoice('Proceed with merge?', ['y', 'n'], 'n');
        if ($response == 'n') {
            return;
        }

        // Update $this->locationToKeep
        $this->io->out();
        $this->io->out("Updating {$this->context} with ID {$this->locationToKeep->id}...");
        if (!$this->table->save($this->locationToKeep)) {
            $this->io->error('There was an error saving those updates. Details: ');
            print_r($this->locationToKeep->getErrors());

            return;
        }
        $this->io->success('Done');

        // Delete $this->locationToRemove
        $this->io->out();
        $this->io->out("Deleting {$this->context} with ID {$this->locationToRemove->id}...");
        if (!$this->table->delete($this->locationToRemove)) {
            $this->io->error('There was an error deleting that record. Details: ');
            print_r($this->locationToRemove->getErrors());

            return;
        }
        $this->io->success('Done');

        $this->io->out();
        $this->io->out('Merge complete');
    }

    /**
     * Updates scalar fields (not arrays or objects) in $this->locationToKeep
     *
     * @return void
     */
    private function mergeScalarFields()
    {
        foreach ($this->locationToKeep->getMergeableFields() as $field) {
            // Don't merge
            if (!is_scalar($this->locationToRemove->$field)) {
                continue;
            }
            if ($this->locationToRemove->$field == $this->locationToKeep->$field) {
                continue;
            }
            if ($this->locationToRemove->$field == '') {
                continue;
            }

            // Merge
            if ($this->locationToKeep->$field == '') {
                $this->io->out(sprintf(
                    'Will update blank %s to "%s"',
                    $field,
                    str_replace("\n", '\n', $this->locationToRemove->$field)
                ));
                $this->locationToKeep = $this->table->patchEntity($this->locationToKeep, [$field => $this->locationToRemove->$field]);
                continue;
            }

            // Have user resolve conflict and merge
            $this->io->out("These {$this->context}s have different values for $field");
            $this->io->out(sprintf(
                ' - 1: Use the new value "%s"',
                $this->locationToRemove->$field
            ));
            $this->io->out(sprintf(
                ' - 2: Keep the old value "%s"',
                $this->locationToKeep->$field
            ));
            $response = $this->io->askChoice('Which value should be kept?', [1, 2]);
            if ($response == 1) {
                $this->locationToKeep = $this->table->patchEntity($this->locationToKeep, [$field => $this->locationToRemove->$field]);
            }
        }
    }

    /**
     * Updates one-to-one associations in $this->locationToKeep
     *
     * @return void
     */
    private function mergeAssociatedObjects()
    {
        foreach ($this->locationToKeep->getMergeableFields() as $field) {
            // Don't merge
            if (!is_object($this->locationToRemove->$field)) {
                continue;
            }
            if (!$this->locationToRemove->$field) {
                continue;
            }
            if ($this->locationToRemove->$field->id == $this->locationToKeep->$field->id) {
                continue;
            }

            // Merge
            if (!$this->locationToKeep->$field) {
                $this->io->out('Will update ' . $field);
                $this->locationToKeep->$field = $this->locationToRemove->$field;
                $this->locationToKeep->setDirty($field);
                continue;
            }

            // Have user resolve conflict and merge
            $this->io->out("These {$this->context}s have different {$field}s");
            $this->io->warning(
                "This may indicate that these are not the same $this->context, " .
                'in which case you should press Ctrl+C to abort this script.'
            );
            $this->io->out(sprintf(
                ' - 1: %s to remove has value "%s"',
                ucfirst($this->context),
                $this->locationToRemove->$field
            ));
            $this->io->out(sprintf(
                ' - 2: %s to keep has value "%s"',
                ucfirst($this->context),
                $this->locationToKeep->$field
            ));
            $response = $this->io->askChoice('Which value should be kept?', [1, 2]);
            if ($response == 1) {
                $this->locationToKeep = $this->table->patchEntity($this->locationToKeep, [$field => $this->locationToRemove->$field]);
            }
        }
    }

    /**
     * Updates one-to-many associations in $this->locationToKeep
     *
     * @return void
     */
    private function mergeAssociationArrays()
    {
        foreach ($this->locationToKeep->getMergeableFields() as $field) {
            // Don't merge
            if (!is_array($this->locationToRemove->$field)) {
                continue;
            }
            if (count($this->locationToRemove->$field) == 0) {
                continue;
            }

            $existingIds = Hash::extract($this->locationToKeep->$field, '{n}.id');
            foreach ($this->locationToRemove->$field as $associatedRecord) {
                if (in_array($associatedRecord->id, $existingIds)) {
                    continue;
                }
                $this->io->out("Will add association with record #{$associatedRecord->id} from $field table");
                $this->locationToKeep->$field[] = $associatedRecord;
                $this->locationToKeep->setDirty($field);
            }
        }
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
     * Populates $this->locationToRemove based on user input
     *
     * @return void
     */
    private function getLocationToRemove()
    {
        while (!$this->locationToRemove) {
            $id = $this->io->ask("What is the ID of the $this->context to merge and then remove?");
            if (!$this->table->exists(['id' => $id])) {
                $this->io->error("No $this->context with that ID was found");
                continue;
            }
            $this->locationToRemove = $this->table->get($id);
        }

        $this->io->out(sprintf(
            '%s #%s selected: %s',
            ucfirst($this->context),
            $this->locationToRemove->id,
            $this->locationToRemove->name
        ));
    }

    /**
     * Populates $this->locationToKeep based on user input
     *
     * @return void
     */
    private function getLocationToKeep()
    {
        while (!$this->locationToKeep) {
            $id = $this->io->ask("What is the ID of the $this->context to merge and keep?");
            if ($id == $this->locationToRemove->id) {
                $this->io->error("You cannot merge a $this->context into itself");
                continue;
            }
            if (!$this->table->exists(['id' => $id])) {
                $this->io->error("No $this->context with that ID was found");
                continue;
            }
            $this->locationToKeep = $this->table->get($id);
        }
        $this->io->out(sprintf(
            '%s #%s selected: %s',
            ucfirst($this->context),
            $this->locationToKeep->id,
            $this->locationToKeep->name
        ));
        if ($this->locationToRemove->name != $this->locationToKeep->name) {
            $this->io->warning(
                'Note that these have different names, which may indicate that they shouldn\'t be merged'
            );
        }
    }
}
