<?php
namespace App\Command;

use App\Model\Entity\Grade;
use App\Model\Entity\School;
use App\Model\Entity\SchoolDistrict;
use App\Model\Entity\SchoolType;
use App\Model\Table\CitiesTable;
use App\Model\Table\CountiesTable;
use App\Model\Table\GradesTable;
use App\Model\Table\SchoolDistrictsTable;
use App\Model\Table\SchoolsTable;
use App\Model\Table\SchoolTypesTable;
use App\Model\Table\StatesTable;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;

/**
 * Class CheckLocationsCommand
 * @package App\Command
 * @property CitiesTable $citiesTable
 * @property ConsoleIo $io
 * @property CountiesTable $countiesTable
 * @property Grade[] $allGrades
 * @property GradesTable $gradesTable
 * @property School[] $schools
 * @property SchoolDistrict[] $districts
 * @property SchoolDistrictsTable $districtsTable
 * @property SchoolsTable $schoolsTable
 * @property SchoolType[] $allSchoolTypes
 * @property SchoolTypesTable $schoolTypesTable
 * @property StatesTable $statesTable
 */
class CheckLocationsCommand extends Command
{
    private $allGrades;
    private $allSchoolTypes;
    private $citiesTable;
    private $countiesTable;
    private $districts = [];
    private $districtsTable;
    private $gradesTable;
    private $io;
    private $schools = [];
    private $schoolsTable;
    private $schoolTypesTable;
    private $statesTable;

    /**
     * Initialization method
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();

        $this->citiesTable = TableRegistry::getTableLocator()->get('Cities');
        $this->countiesTable = TableRegistry::getTableLocator()->get('Counties');
        $this->districtsTable = TableRegistry::getTableLocator()->get('SchoolDistricts');
        $this->gradesTable = TableRegistry::getTableLocator()->get('Grades');
        $this->schoolsTable = TableRegistry::getTableLocator()->get('Schools');
        $this->schoolTypesTable = TableRegistry::getTableLocator()->get('SchoolTypes');
        $this->statesTable = TableRegistry::getTableLocator()->get('States');
    }

    /**
     * Processes location info file and updates the database
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return int|null|void
     * @throws \Exception
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->io = $io;
        $this->setup();
        $this->checkSchoolsWithoutDistricts();
        $this->checkDistrictsWithoutSchools();
    }

    /**
     * Creates a progress bar, draws it, and returns it
     *
     * @param int $total Total number of items to be processed
     * @return ProgressHelper
     */
    private function makeProgressBar($total)
    {
        /** @var ProgressHelper $progress */
        $progress = $this->io->helper('Progress');
        $progress->init([
            'total' => $total,
            'width' => 40,
        ]);
        $progress->draw();

        return $progress;
    }

    /**
     * Collects data from the database and loads it into class properties
     *
     * @return void
     */
    private function setup()
    {
        $this->io->out('Collecting schools...');
        $progress = $this->makeProgressBar(4);
        $this->allGrades = $this->gradesTable->getAll();
        $progress->increment(1)->draw();
        $this->allSchoolTypes = $this->schoolTypesTable->getAll();
        $progress->increment(1)->draw();
        $this->schools = $this->schoolsTable
            ->find()
            ->contain([
                'SchoolDistricts',
                'SchoolTypes',
                'Cities',
                'States'
            ])
            ->all();
        $progress->increment(1)->draw();
        $this->districts = $this->districtsTable
            ->find()
            ->contain([
                'Schools'
            ])
            ->all();
        $this->io->overwrite(' - Done');
    }

    /**
     * Checks for public schools with no school districts
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function checkSchoolsWithoutDistricts()
    {
        $this->io->out('Checking for public schools without districts...');
        $progress = $this->makeProgressBar(count($this->schools));
        $results = [];
        foreach ($this->schools as $school) {
            $progress->increment(1)->draw();
            if (!isset($school->school_type->name)
                || $school->school_type->name != 'public'
                || $school->school_district_id
            ) {
                continue;
            }
            $results[] = [
                $school->name,
                $school->code
            ];
        }
        if ($results) {
            $this->io->overwrite(sprintf(
                ' - %s %s found',
                count($results),
                __n('school', 'schools', count($results))
            ));
            $choice = $this->io->askChoice('List schools?', ['y', 'n'], 'y');
            if ($choice == 'y') {
                array_unshift($results, ['Name', 'DoE Code']);
                $this->io->helper('Table')->output($results);
            }
        } else {
            $this->io->overwrite(' - None found');
        }
    }

    /**
     * Checks for school districts with no associated schools
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function checkDistrictsWithoutSchools()
    {
        $this->io->out('Checking for districts without schools...');
        $progress = $this->makeProgressBar(count($this->districts));
        $results = [];
        foreach ($this->districts as $district) {
            $progress->increment(1)->draw();
            if ($district->schools) {
                continue;
            }
            $results[] = [
                $district->name,
                $district->code
            ];
        }
        if ($results) {
            $this->io->overwrite(sprintf(
                ' - %s %s found',
                count($results),
                __n('district', 'districts', count($results))
            ));
            $choice = $this->io->askChoice('List districts?', ['y', 'n'], 'y');
            if ($choice == 'y') {
                array_unshift($results, ['Name', 'DoE Code']);
                $this->io->helper('Table')->output($results);
            }
        } else {
            $this->io->overwrite(' - None found');
        }
    }
}
