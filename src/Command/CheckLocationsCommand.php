<?php
namespace App\Command;

use App\Model\Context\Context;
use App\Model\Entity\School;
use App\Model\Entity\SchoolDistrict;
use App\Model\Table\SchoolDistrictsTable;
use App\Model\Table\SchoolsTable;
use App\Model\Table\StatisticsTable;
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
        $this->checkForMultipleGeographies('school', 'cities');
        $this->checkForMultipleGeographies('school', 'counties');
        $this->checkForMultipleGeographies('school', 'states');
        $this->checkForMultipleGeographies('district', 'cities');
        $this->checkForMultipleGeographies('district', 'counties');
        $this->checkForMultipleGeographies('district', 'states');

        $runFixDistrictAssociations = $this->io->askChoice(
            'Run fix-district-associations?',
            ['y', 'n'],
            'y'
        ) == 'y';
        if ($runFixDistrictAssociations) {
            $command = new FixDistrictAssociationsCommand();
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
                'Schools',
                'States'
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
        $this->io->out();
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
        $this->io->out();
    }

    /**
     * Asks the user for input and optionally displays a table of results
     *
     * @param array $results Collection of schools or districts
     * @param string $resultNoun Such as 'school' or 'district'
     * @param array $headers Array of column headers to include in the displayed table
     * @return void
     * @throws \Aura\Intl\Exception
     */
    private function showResults($results, $resultNoun, $headers = ['Name', 'DoE Code'])
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
        $this->io->out();
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

    /**
     * Checks for districts that aren't associated with any cities
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function checkDistrictsWithoutCities()
    {
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
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function checkDistrictsWithoutCounties()
    {
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
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function checkDistrictsWithoutStates()
    {
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
     * @throws \Aura\Intl\Exception
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
                $record->code
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
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function checkSchoolsWithoutStats()
    {
        $this->checkForNoStats('school');
    }

    /**
     * Checks for districts that aren't associated with any statistical data
     *
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function checkDistrictsWithoutStats()
    {
        $this->checkForNoStats('district');
    }

    /**
     * Checks for any schools/districts associated with multiple cities, counties, etc.
     *
     * @param string $context Either 'school' or 'district'
     * @param string $field e.g. 'cities' or 'counties'
     * @throws \Aura\Intl\Exception
     * @return void
     */
    private function checkForMultipleGeographies($context, $field)
    {
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
                $record->code,
                implode(', ', Hash::extract($record->$field, '{n}.name'))
            ];
        }
        if ($results) {
            $headers = ['Name', 'DoE Code', ucwords($field)];
            $this->showResults($results, $context, $headers);

            return;
        }

        $this->io->overwrite(' - None found');
        $this->io->out();
    }
}
