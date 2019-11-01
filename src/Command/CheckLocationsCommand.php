<?php
namespace App\Command;

use App\Model\Context\Context;
use App\Model\Entity\School;
use App\Model\Entity\SchoolDistrict;
use App\Model\Table\SchoolDistrictsTable;
use App\Model\Table\SchoolsTable;
use App\Model\Table\StatisticsTable;
use Aura\Intl\Exception;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;
use Cake\Utility\Hash;

/**
 * Class CheckLocationsCommand
 * @package App\Command
 * @property ConsoleIo $io
 * @property School[] $schools
 * @property SchoolDistrict[] $districts
 * @property SchoolDistrictsTable $districtsTable
 * @property SchoolsTable $schoolsTable
 * @property StatisticsTable $statsTable
 */
class CheckLocationsCommand extends Command
{
    private $districts = [];
    private $districtsTable;
    private $io;
    private $schools = [];
    private $schoolsTable;
    private $statsTable;

    /**
     * Initialization method
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();

        $this->districtsTable = TableRegistry::getTableLocator()->get('SchoolDistricts');
        $this->schoolsTable = TableRegistry::getTableLocator()->get('Schools');
        $this->statsTable = TableRegistry::getTableLocator()->get('Statistics');
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
        $this->checkSchoolsWithoutCodes();
        $this->checkDistrictsWithoutCodes();
        $this->checkSchoolsWithoutTypes();
        $this->checkSchoolsWithoutGrades();
        $this->checkSchoolsWithoutDistricts();
        $this->checkDistrictsWithoutSchools();
        $this->checkSchoolsWithoutCities();
        $this->checkSchoolsWithoutCounties();
        $this->checkSchoolsWithoutStates();
        $this->checkSchoolsWithoutAddresses();
        $this->checkDistrictsWithoutCities();
        $this->checkDistrictsWithoutCounties();
        $this->checkDistrictsWithoutStates();
        $this->checkSchoolsWithoutStats();
        $this->checkDistrictsWithoutStats();

        $this->io->info(
            'Note: Schools or districts belonging to multiple cities, counties, etc. is not necessarily ' .
            'indicative of an error.'
        );
        $this->checkForMultipleGeographies('school', 'cities');
        $this->checkForMultipleGeographies('school', 'counties');
        $this->checkForMultipleGeographies('school', 'states');
        $this->checkForMultipleGeographies('district', 'cities');
        $this->checkForMultipleGeographies('district', 'counties');
        $this->checkForMultipleGeographies('district', 'states');

        $runFixDistrictAssociations = $this->io->askChoice(
            'Check for and fix districts with missing cities, counties, etc.?',
            ['y', 'n'],
            'y'
        ) == 'y';
        if ($runFixDistrictAssociations) {
            $command = new FixDistrictAssociationsCommand();
            $command->initialize();
            $command->execute($args, $io);
        }
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
        $progress = $this->makeProgressBar(2);
        $this->schools = $this->schoolsTable
            ->find()
            ->contain([
                'Cities',
                'Counties',
                'Grades',
                'SchoolCodes',
                'SchoolDistricts',
                'SchoolTypes',
                'States'
            ])
            ->all();
        $progress->increment(1)->draw();
        $this->districts = $this->districtsTable
            ->find()
            ->contain([
                'Cities',
                'Counties',
                'SchoolDistrictCodes',
                'Schools',
                'States'
            ])
            ->all();
        $this->io->overwrite(' - Done');
    }

    /**
     * Checks for public schools with no school districts
     *
     * @throws Exception
     * @return void
     */
    private function checkSchoolsWithoutDistricts()
    {
        if (!$this->getConfirmation('Check for public schools without districts?')) {
            return;
        }

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
                $school->id,
                $school->origin_file
            ];
        }
        if ($results) {
            $this->showResults($results, 'school');

            return;
        }

        $this->io->overwrite(' - None found');
        $this->io->out();
    }

    /**
     * Checks for school districts with no associated schools
     *
     * @throws Exception
     * @return void
     */
    private function checkDistrictsWithoutSchools()
    {
        if (!$this->getConfirmation('Check for districts without schools?')) {
            return;
        }

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
                $district->id,
                $district->origin_file
            ];
        }

        if ($results) {
            $this->showResults($results, 'district');

            return;
        }

        $this->io->overwrite(' - None found');
        $this->io->out();
    }

    /**
     * Asks the user for input and optionally displays a table of results
     *
     * @param array $results Collection of schools or districts
     * @param string $resultNoun Such as 'school' or 'district'
     * @param array $headers Array of column headers to include in the displayed table
     * @return void
     */
    private function showResults($results, $resultNoun, $headers = ['Name', 'ID', 'Origin File'])
    {
        $this->io->overwrite(sprintf(
            ' - %s %s found',
            count($results),
            __n($resultNoun, $resultNoun . 's', count($results))
        ));
        $choice = $this->io->askChoice("List {$resultNoun}s?", ['y', 'n'], 'n');
        if ($choice == 'y') {
            array_unshift($results, $headers);
            $this->io->helper('Table')->output($results);
        }
        $this->io->out();
    }

    /**
     * Checks for schools with missing public / private / charter type
     *
     * @throws Exception
     * @return void
     */
    private function checkSchoolsWithoutTypes()
    {
        if (!$this->getConfirmation('Check for schools with missing public/private/charter type?')) {
            return;
        }

        $this->checkForEmptyField(
            $this->schools,
            'school',
            'Checking for schools with missing public/private/charter type...',
            'school_type'
        );
    }

    /**
     * Checks for schools that aren't associated with any grade levels
     *
     * @throws Exception
     * @return void
     */
    private function checkSchoolsWithoutGrades()
    {
        if (!$this->getConfirmation('Check for schools without grade levels?')) {
            return;
        }
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
                $record->id,
                $record->origin_file
            ];
        }
        if ($results) {
            $this->showResults($results, $resultNoun);

            return;
        }

        $this->io->overwrite(' - None found');
        $this->io->out();
    }

    /**
     * Checks for schools that aren't associated with any cities
     *
     * @throws Exception
     * @return void
     */
    private function checkSchoolsWithoutCities()
    {
        if (!$this->getConfirmation('Check for schools without cities?')) {
            return;
        }

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
     * @throws Exception
     * @return void
     */
    private function checkSchoolsWithoutCounties()
    {
        if (!$this->getConfirmation('Check for schools without counties?')) {
            return;
        }

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
     * @throws Exception
     * @return void
     */
    private function checkSchoolsWithoutStates()
    {
        if (!$this->getConfirmation('Check for schools without states?')) {
            return;
        }

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
     * @throws Exception
     * @return void
     */
    private function checkSchoolsWithoutAddresses()
    {
        if (!$this->getConfirmation('Check for schools without addresses?')) {
            return;
        }

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
     * @return void
     */
    private function checkSchoolsWithoutCodes()
    {
        if (!$this->getConfirmation('Check for schools without Department of Education codes?')) {
            return;
        }

        $this->checkForEmptyField(
            $this->schools,
            'school',
            'Checking for schools without DoE codes...',
            'school_codes'
        );
    }

    /**
     * Checks for districts with missing Indiana Department of Education codes
     *
     * @throws Exception
     * @return void
     */
    private function checkDistrictsWithoutCodes()
    {
        if (!$this->getConfirmation('Check for districts without Department of Education codes?')) {
            return;
        }

        $this->checkForEmptyField(
            $this->districts,
            'district',
            'Checking for districts without DoE codes...',
            'district_codes'
        );
    }

    /**
     * Checks for districts that aren't associated with any cities
     *
     * @throws Exception
     * @return void
     */
    private function checkDistrictsWithoutCities()
    {
        if (!$this->getConfirmation('Check for districts without cities?')) {
            return;
        }

        $this->checkForEmptyField(
            $this->districts,
            'district',
            'Checking for districts without cities...',
            'cities'
        );
    }

    /**
     * Checks for districts that aren't associated with any counties
     *
     * @throws Exception
     * @return void
     */
    private function checkDistrictsWithoutCounties()
    {
        if (!$this->getConfirmation('Check for districts without counties?')) {
            return;
        }

        $this->checkForEmptyField(
            $this->districts,
            'district',
            'Checking for districts without counties...',
            'counties'
        );
    }

    /**
     * Checks for districts that aren't associated with any cities
     *
     * @throws Exception
     * @return void
     */
    private function checkDistrictsWithoutStates()
    {
        if (!$this->getConfirmation('Check for districts without states?')) {
            return;
        }

        $this->checkForEmptyField(
            $this->districts,
            'district',
            'Checking for districts without states...',
            'states'
        );
    }

    /**
     * Checks for any schools/districts with no associated data
     *
     * @param string $context Either 'school' or 'district'
     * @throws Exception
     * @return void
     */
    private function checkForNoStats($context)
    {
        $this->io->out("Checking for {$context}s without stats...");
        $records = ($context == 'school') ? $this->schools : $this->districts;
        $progress = $this->makeProgressBar(count($records));
        $results = [];
        foreach ($records as $record) {
            $progress->increment(1)->draw();
            $locationField = Context::getLocationField($context);
            $dataFound = $this->statsTable->exists([
                $locationField => $record->id
            ]);
            if ($dataFound) {
                continue;
            }
            $results[] = [
                $record->name,
                $record->id,
                $record->origin_file
            ];
        }
        if ($results) {
            $this->showResults($results, $context);

            return;
        }

        $this->io->overwrite(' - None found');
        $this->io->out();
    }

    /**
     * Checks for schools that aren't associated with any statistical data
     *
     * @throws Exception
     * @return void
     */
    private function checkSchoolsWithoutStats()
    {
        if (!$this->getConfirmation('Check for schools without statistics?')) {
            return;
        }

        $this->checkForNoStats('school');
    }

    /**
     * Checks for districts that aren't associated with any statistical data
     *
     * @throws Exception
     * @return void
     */
    private function checkDistrictsWithoutStats()
    {
        if (!$this->getConfirmation('Check for districts without statistics?')) {
            return;
        }

        $this->checkForNoStats('district');
    }

    /**
     * Checks for any schools/districts associated with multiple cities, counties, etc.
     *
     * @param string $context Either 'school' or 'district'
     * @param string $field e.g. 'cities' or 'counties'
     * @throws Exception
     * @return void
     */
    private function checkForMultipleGeographies($context, $field)
    {
        if (!$this->getConfirmation("Check for {$context}s with multiple $field?")) {
            return;
        }

        $this->io->out("Checking for {$context}s associated with multiple $field...");
        $records = ($context == 'school') ? $this->schools : $this->districts;
        $progress = $this->makeProgressBar(count($records));
        $results = [];
        foreach ($records as $record) {
            $progress->increment(1)->draw();
            if (count($record->$field) < 2) {
                continue;
            }
            $results[] = [
                $record->name,
                $record->id,
                implode(', ', Hash::extract($record->$field, '{n}.name'))
            ];
        }
        if ($results) {
            $headers = ['Name', 'ID', ucwords($field)];
            $this->showResults($results, $context, $headers);

            return;
        }

        $this->io->overwrite(' - None found');
        $this->io->out();
    }

    /**
     * Displays a message and a prompt for a 'y' or 'n' response and returns TRUE if response is 'y'
     *
     * @param string $msg Message to display
     * @param string $default Default selection (leave blank for 'y')
     * @return bool
     */
    private function getConfirmation($msg, $default = 'y')
    {
        return $this->io->askChoice(
            $msg,
            ['y', 'n'],
            $default
        ) == 'y';
    }
}
