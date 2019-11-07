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
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;

/**
 * Class DeleteCommand
 * @package App\Command
 * @property ConsoleIo $io
 * @property StatisticsTable $statsTable
 * @property string $tableName
 * @property array $choices
 * @property School|SchoolDistrict $subjectRecord
 * @property SchoolsTable|SchoolDistrictsTable $subjectTable
 * @property array $associations
 */
class DeleteCommand extends Command
{
    private $io;
    private $description =
        'This command will tell you if a record is safe to delete and then delete it and associated records.';
    private $statsTable;
    private $tableName;
    private $choices = [
        1 => 'Schools',
        2 => 'SchoolDistricts'
    ];
    private $subjectRecord;
    private $subjectTable;
    private $associations;

    /**
     * Initialization method
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();

        $this->statsTable = TableRegistry::getTableLocator()->get('Statistics');
    }

    protected function buildOptionParser(ConsoleOptionParser $parser)
    {
        $parser->setDescription($this->description);

        return $parser;
    }

    /**
     * Runs this command
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return void
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->io = $io;
        $this->io->out($this->description);
        $this->io->warning('It is recommended that you back up the database before deleting anything.');
        while (true) {
            $this->io->out();
            $this->getTableName();
            $this->subjectTable = TableRegistry::getTableLocator()->get($this->tableName);
            $this->getRecord();
            $this->listAssociationCounts();
            $this->io->out();
            if ($this->getDeleteConfirmation()) {
                if ($this->subjectTable->delete($this->subjectRecord)) {
                    $this->io->success('Deleted ' . $this->subjectRecord->name);
                }
            }
            if (!$this->continue()) {
                return;
            }
        }
    }

    /**
     * Asks the user for a table name and populates $this->tableName
     *
     * @return void
     */
    private function getTableName()
    {
        $this->io->out('Records that can be deleted:');
        foreach ($this->choices as $number => $choice) {
            $this->io->out(" $number - $choice");
        }
        $choice = $this->io->askChoice('What would you like to delete?', array_keys($this->choices));

        $this->tableName = $this->choices[$choice];
    }

    /**
     * Populates $this->subjectRecord
     *
     * @return void
     */
    private function getRecord()
    {
        $record = null;
        $allContain = [
            'Cities',
            'Counties',
            'States',
            'Statistics'
        ];
        $schoolsContain = [
            'Grades',
            'RankingResultsSchools',
            'SchoolCodes',
            'SchoolDistricts',
            'SchoolTypes',
        ];
        $districtsContain = [
            'RankingResultsSchoolDistricts',
            'Rankings',
            'SchoolDistrictCodes',
            'Schools',
        ];
        switch ($this->tableName) {
            case 'Schools':
                $this->associations = array_merge($allContain, $schoolsContain);
                break;
            case 'SchoolDistricts':
                $this->associations = array_merge($allContain, $districtsContain);
                break;
            default:
                throw new InternalErrorException('Invalid table name: ' . $this->tableName);
        }
        $entityClassArray = explode('\\', $this->subjectTable->getEntityClass());
        $entityName = array_pop($entityClassArray);
        do {
            $id = $this->io->ask($entityName . ' ID:');
            if (!$this->subjectTable->exists(['id' => $id])) {
                $this->io->err('A ' . $entityName . ' with that ID doesn\'t exist');
                continue;
            }

            $this->subjectRecord = $this->subjectTable
                ->find()
                ->where([$this->tableName . '.id' => $id])
                ->contain($this->associations)
                ->first();
            $this->io->out('Selected: ' . $this->subjectRecord->name);

            return;
        } while (true);
    }

    /**
     * Displays a table of all associations that this record has and the count of records for each
     *
     * @return void
     */
    private function listAssociationCounts()
    {
        $belongsToAssociations = [
            'SchoolDistricts' => 'school_district',
            'SchoolTypes' => 'school_type'
        ];
        $counts = [['Association', 'Count', 'Associated records will be deleted?']];
        foreach ($this->associations as $association) {
            $associationField = array_key_exists($association, $belongsToAssociations)
                ? $belongsToAssociations[$association]
                : Inflector::tableize($association);
            $count = count($this->subjectRecord->$associationField);
            $willBeDeleted = $this->subjectTable->$association->getDependent() ? 'Yes' : '';
            $counts[] = [
                $association,
                $count,
                $count ? $willBeDeleted : ''
            ];
        }

        $this->io->helper('Table')->output($counts);
    }

    /**
     * Returns TRUE if the user appears to consent to potentially wrecking the database
     *
     * @return bool
     */
    private function getDeleteConfirmation()
    {
        if (count($this->subjectRecord->statistics)) {
            $this->io->warning('This location has associated statistics. Deleting it is NOT recommended.');
            $response = $this->io->ask('Please type "ok" to acknowledge this and continue.');
            if ($response != 'ok') {
                $this->io->out('That ain\'t ok. Aborting.');

                return false;
            }
        } else {
            $this->io->success('This location has no associated statistics and can probably be safely deleted.');
        }
        $response = $this->io->askChoice(
            'Are you sure you want to delete this record and associated records?',
            ['y', 'n'],
            'n'
        );

        return $response == 'y';
    }

    /**
     * Returns TRUE if the user wants to continue deleting additional records
     *
     * @return bool
     */
    private function continue()
    {
        $response = $this->io->askChoice('Delete something else?', ['y', 'n'], 'n');

        return $response == 'y';
    }
}
