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
        $io->out();
        $this->checkSchoolsWithoutCodes();
        $io->out();
        $this->checkDistrictsWithoutCodes();
        $io->out();
        $this->checkSchoolsWithoutTypes();
        $io->out();
        $this->checkSchoolsWithoutGrades();
        $io->out();
        $this->checkSchoolsWithoutDistricts();
        $io->out();
        $this->checkDistrictsWithoutSchools();
        $io->out();
        $this->checkSchoolsWithoutCities();
        $io->out();
        $this->checkSchoolsWithoutCounties();
        $io->out();
        $this->checkSchoolsWithoutStates();
        $io->out();
        $this->checkSchoolsWithoutAddresses();
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
        $this->io->out('Collecting data...');
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
                'States',
                'Grades',
                'Counties'
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
            $this->showResults($results, 'school');

            return;
        }

        $this->io->overwrite(' - None found');
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
            $this->showResults($results, 'district');

            return;
        }

        $this->io->overwrite(' - None found');
    }

    /**
     * Asks the user for input and optionally displays a table of results
     *
     * @param array $results Collection of schools or districts
     * @param string $resultNoun Such as 'school' or 'district'
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function showResults($results, $resultNoun)
    {
        $this->io->overwrite(sprintf(
            ' - %s %s found',
            count($results),
            __n($resultNoun, $resultNoun . 's', count($results))
        ));
        $choice = $this->io->askChoice("List {$resultNoun}s?", ['y', 'n'], 'n');
        if ($choice == 'y') {
            array_unshift($results, ['Name', 'DoE Code']);
            $this->io->helper('Table')->output($results);
        }
    }

    /**
     * Checks for schools with missing public / private / charter type
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function checkSchoolsWithoutTypes()
    {
        $this->checkForEmptyField(
            $this->schools,
            'school',
            'Checking for schools with missing public/private type...',
            'school_type'
        );
    }

    /**
     * Checks for schools that aren't associated with any grade levels
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function checkSchoolsWithoutGrades()
    {
        $this->checkForEmptyField(
            $this->schools,
            'school',
            'Checking for schools without grade levels...',
            'grades'
        );
    }

    /**
     * Generic method for checking for an empty field in $this->schools
     *
     * @param array $records Array of either schools or districts
     * @param string $resultNoun Either 'school' or 'district'
     * @param string $message Starting message to output
     * @param string $fieldName Name of field of school object to check for empty status
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function checkForEmptyField($records, $resultNoun, $message, $fieldName)
    {
        $this->io->out($message);
        $progress = $this->makeProgressBar(count($records));
        $results = [];
        foreach ($records as $record) {
            $progress->increment(1)->draw();
            if ($record->$fieldName) {
                continue;
            }
            $results[] = [
                $record->name,
                $record->code
            ];
        }
        if ($results) {
            $this->showResults($results, $resultNoun);

            return;
        }

        $this->io->overwrite(' - None found');
    }

    /**
     * Checks for schools that aren't associated with any cities
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function checkSchoolsWithoutCities()
    {
        $this->checkForEmptyField(
            $this->schools,
            'school',
            'Checking for schools without cities...',
            'cities'
        );
    }

    /**
     * Checks for schools that aren't associated with any counties
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function checkSchoolsWithoutCounties()
    {
        $this->checkForEmptyField(
            $this->schools,
            'school',
            'Checking for schools without counties...',
            'counties'
        );
    }

    /**
     * Checks for schools that aren't associated with any cities
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function checkSchoolsWithoutStates()
    {
        $this->checkForEmptyField(
            $this->schools,
            'school',
            'Checking for schools without states...',
            'states'
        );
    }

    /**
     * Checks for schools with missing addresses
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function checkSchoolsWithoutAddresses()
    {
        $this->checkForEmptyField(
            $this->schools,
            'school',
            'Checking for schools without addresses...',
            'address'
        );
    }

    /**
     * Checks for schools with missing Indiana Department of Education codes
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function checkSchoolsWithoutCodes()
    {
        $this->checkForEmptyField(
            $this->schools,
            'school',
            'Checking for schools without DoE codes...',
            'code'
        );
    }

    /**
     * Checks for districts with missing Indiana Department of Education codes
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function checkDistrictsWithoutCodes()
    {
        $this->checkForEmptyField(
            $this->districts,
            'district',
            'Checking for districts without DoE codes...',
            'code'
        );
    }
}
