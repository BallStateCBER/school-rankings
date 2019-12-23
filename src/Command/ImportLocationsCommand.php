<?php
namespace App\Command;

use App\Import\ImportFile;
use App\Model\Context\Context;
use App\Model\Entity\City;
use App\Model\Entity\County;
use App\Model\Entity\Grade;
use App\Model\Entity\School;
use App\Model\Entity\SchoolDistrict;
use App\Model\Entity\SchoolType;
use App\Model\Entity\State;
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
use Cake\Datasource\EntityInterface;
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\TableRegistry;
use Elastica\Exception\NotFoundException;
use Exception;

/**
 * Class ImportLocationsCommand
 * @package App\Command
 * @property array $locationsAdded
 * @property CitiesTable $citiesTable
 * @property ConsoleIo $io
 * @property CountiesTable $countiesTable
 * @property Grade[] $allGrades
 * @property GradesTable $gradesTable
 * @property ImportFile $importFile
 * @property School[] $schools
 * @property SchoolDistrict[] $districts
 * @property SchoolDistrictsTable $districtsTable
 * @property SchoolsTable $schoolsTable
 * @property SchoolType[] $allSchoolTypes
 * @property SchoolTypesTable $schoolTypesTable
 * @property StatesTable $statesTable
 * @property string $filename
 */
class ImportLocationsCommand extends Command
{
    private $allGrades;
    private $allSchoolTypes;
    private $citiesTable;
    private $countiesTable;
    private $districts = [];
    private $districtsTable;
    private $filename;
    private $gradesTable;
    private $importFile;
    private $locationsAdded = [
        'schools' => [],
        'districts' => [],
        'cities' => [],
        'counties' => [],
        'states' => []
    ];
    private $schools = [];
    private $schoolsTable;
    private $schoolTypesTable;
    private $statesTable;
    public $io;

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

        $this->allGrades = $this->gradesTable->getAll();
        $this->allSchoolTypes = $this->schoolTypesTable->getAll();
    }

    /**
     * Processes location info file and updates the database
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return int|null|void
     * @throws Exception
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->io = $io;

        $file = $this->selectFile();
        if (!$file) {
            return;
        }
        $io->out($file . ' selected');
        $this->filename = $file;

        $year = Utility::getYearFromFilename($file, $this->io);
        if (!$year) {
            return;
        }

        $io->out("Opening $file...");
        $dir = $this->getDirectory();
        $this->importFile = new ImportFile($year, $dir, $file, $io);
        $this->importFile->ignoreWorksheets(['closed']);

        // Read in worksheet info and validate data
        $io->out('Analyzing worksheets...');
        $io->out();
        $this->importFile->read();
        if ($this->importFile->getError()) {
            $this->io->err($this->importFile->getError());

            return;
        }
        foreach ($this->importFile->getWorksheets() as $worksheetName => $worksheetInfo) {
            $context = $worksheetInfo['context'];
            $io->info('Worksheet: ' . $worksheetName);
            $io->out('Context: ' . ucwords($context));

            try {
                $this->importFile->selectWorksheet($worksheetName);
                $this->importFile->identifyLocations();
            } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                $io->nl();
                $io->error($e->getMessage());

                return;
            } catch (Exception $e) {
                $io->nl();
                $io->error($e->getMessage());

                return;
            }

            $io->out('Preparing updates...');
            if ($context == 'district') {
                $this->prepareDistricts();
            } elseif ($context == 'school') {
                $this->prepareSchools();
            }
            $this->confirmNameChanges($context);
            $this->reportAddedLocations();

            $io->out();
        }

        $this->updateRecords();

        $this->io->out();
        $this->io->success('Import complete');

        (new ImportStatsCommand())->markFileProcessed($year, $file, $io);

        // Free up memory
        $this->importFile->spreadsheet->disconnectWorksheets();
        unset($this->importFile);

        $runCommand = $this->io->askChoice(
            'Run check-locations command?',
            ['y', 'n'],
            'y'
        ) == 'y';
        if ($runCommand) {
            $command = new CheckLocationsCommand();
            $command->initialize();
            $command->execute($args, $this->io);
        }
    }

    /**
     * Returns the full path to the directory that contains school/district information
     *
     * @return string
     */
    public static function getDirectory()
    {
        return ROOT . DS . 'data' . DS . 'locations' . DS . 'open';
    }

    /**
     * Asks the user for input and returns a filename
     *
     * @return string|bool
     */
    public function selectFile()
    {
        return Utility::selectFile($this->getDirectory(), $this->io);
    }

    /**
     * Returns the School or SchoolDistrict entity matching the provided $code
     *
     * @param string $context Either 'school' or 'district'
     * @param string $code The Department of Education identification code
     * @return array|EntityInterface
     */
    private function getLocation($context, $code)
    {
        $table = ($context == 'district') ? $this->districtsTable : $this->schoolsTable;

        return $table
            ->find('byCode', ['code' => Utility::removeLeadingZeros($code)])
            ->firstOrFail();
    }

    /**
     * Returns the parameters for looping through all cells with location data
     *
     * @return array
     */
    private function getLoopParams()
    {
        $firstRow = $this->importFile->getActiveWorksheetProperty('firstDataRow');
        $lastRow = $this->importFile->getActiveWorksheetProperty('totalRows');
        $firstCol = 1;
        $lastCol = $this->importFile->getActiveWorksheetProperty('totalCols');

        return [$firstRow, $lastRow, $firstCol, $lastCol];
    }

    /**
     * Adds districts from the active worksheet to $this->districts, prepares them for updating, and aborts on errors
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @return void
     */
    private function prepareDistricts()
    {
        $context = 'district';
        $districts = [];
        $ignoredDistrictCodes = SchoolDistrictsTable::getIgnoredDistrictCodes();
        $columnNames = $this->importFile->getColumnNames();
        list($firstRow, $lastRow, $firstCol, $lastCol) = $this->getLoopParams();
        $fieldMap = [
            'IDOE CORPORATION ID' => 'code',
            'CORPORATION NAME' => 'name',
            'CORPORATION HOMEPAGE' => 'url',
            'CITY' => 'city',
            'STATE' => 'state',
            'COUNTY NAME' => 'county',
            'PHONE' => 'phone'
        ];

        $progress = Utility::makeProgressBar($lastRow - $firstRow + 1, $this->io);
        for ($row = $firstRow; $row <= $lastRow; $row++) {
            $progress->increment(1);
            $progress->draw();

            $data = $this->getData($row, $firstCol, $lastCol, $columnNames, $fieldMap);

            // Skip ignorable districts
            $data['code'] = Utility::removeLeadingZeros($data['code']);
            if (in_array($data['code'], $ignoredDistrictCodes)) {
                continue;
            }

            // Get associated model data
            $state = $this->getState($data['state']);
            $county = $this->getCounty($data['county'], $state->id);
            $city = $this->getCity($data['city'], $state->id);
            if (!$this->citiesTable->Counties->link($city, [$county])) {
                $this->io->error('Error linking ' . $city->name . ' to ' . $county->name . ' County');
                $this->abort();
            }

            // Avoid saving dummy data
            if ($this->isDummyPhone($data['phone'])) {
                $data['phone'] = null;
            }

            // Prepare update
            $district = $this->getLocation($context, $data['code']);
            $district = $this->districtsTable->patchEntity($district, [
                'name' => $data['name'],
                'url' => $data['url'],
                'phone' => $data['phone'],
                'origin_file' => $this->filename,
                'cities' => [
                    '_ids' => [$city->id]
                ],
                'counties' => [
                    '_ids' => [$county->id]
                ],
                'states' => [
                    '_ids' => [$state->id]
                ]
            ]);
            $errors = $district->getErrors();
            $passesRules = $this->districtsTable->checkRules($district, 'update');
            if (empty($errors) && $passesRules) {
                $districts[] = $district;
                continue;
            }

            $this->reportValidationError($context, $district->id, $data['name'], $errors);
        }

        $this->districts = array_merge($this->districts, $districts);
        $this->io->overwrite(' - Done');
    }

    /**
     * Adds schools from the active worksheet to $this->schools, prepares them for updating, and aborts on errors
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @return void
     */
    private function prepareSchools()
    {
        $context = 'school';
        $schools = [];
        $columnNames = $this->importFile->getColumnNames();
        list($firstRow, $lastRow, $firstCol, $lastCol) = $this->getLoopParams();
        $fieldMap = [
            'IDOE CORPORATION ID' => 'district code',
            'CORP' => 'district code',
            'SCHL' => 'code',
            'IDOE SCHOOL ID' => 'code',
            'SCHOOL NAME' => 'name',
            'SCHL_NAME' => 'name',
            'ADDRESS' => 'address',
            'CITY' => 'city',
            'STATE' => 'state',
            'ZIP' => 'zip',
            'COUNTY NAME' => 'county',
            'COUNTY_NAME' => 'county',
            'SCHOOL HOMEPAGE' => 'url',
            'SCHOOL_HOMEPAGE' => 'url',
            'PHONE' => 'phone',
            'LOW GRADE 1' => 'low grade',
            'LOW_GRADE' => 'low grade',
            'HIGH GRADE 1' => 'high grade',
            'HIGH_GRADE' => 'high grade'
        ];

        $progress = Utility::makeProgressBar($lastRow - $firstRow + 1, $this->io);
        for ($row = $firstRow; $row <= $lastRow; $row++) {
            $progress->increment(1);
            $progress->draw();

            $data = $this->getData($row, $firstCol, $lastCol, $columnNames, $fieldMap);

            /** @var School $school */
            $school = $this->getLocation($context, $data['code']);

            // Get associated model data
            if (array_key_exists('district code', $data)) {
                $district = $this->districtsTable
                    ->find('byCode', ['code' => Utility::removeLeadingZeros($data['district code'])])
                    ->select(['id'])
                    ->first();
                if (!$district) {
                    throw new NotFoundException(
                        "Cannot add school with district code {$data['district code']} " .
                        ' because that district has not yet been added to the database'
                    );
                }
            } else {
                $district = null;
            }
            $schoolType = $this->getSchoolType($this->importFile->activeWorksheet);
            $state = $this->getState($data['state']);
            $county = $this->getCounty($data['county'], $state->id);
            $city = $this->getCity($data['city'], $state->id);
            if (!$this->citiesTable->Counties->link($city, [$county])) {
                $this->io->error('Error linking ' . $city->name . ' to ' . $county->name . ' County');
                $this->abort();
            }

            // Avoid saving dummy data
            if ($this->isDummyPhone($data['phone'])) {
                $data['phone'] = null;
            }

            // Prepare update
            $school = $this->schoolsTable->patchEntity($school, [
                'school_district_id' => $district ? $district->id : null,
                'school_type_id' => $schoolType->id,
                'name' => $data['name'],
                'address' => sprintf(
                    "%s\n%s, %s%s",
                    $data['address'],
                    $data['city'],
                    $data['state'],
                    $data['zip'] ? " {$data['zip']}" : ''
                ),
                'url' => $data['url'],
                'phone' => $data['phone'],
                'origin_file' => $this->filename,
                'cities' => [
                    '_ids' => [$city->id]
                ],
                'counties' => [
                    '_ids' => [$county->id]
                ],
                'states' => [
                    '_ids' => [$state->id]
                ],
                'grades' => [
                    '_ids' => $this->getGradeIdsInRange($data)
                ]
            ]);
            $errors = $school->getErrors();
            $passesRules = $this->schoolsTable->checkRules($school, 'update');
            if (empty($errors) && $passesRules) {
                $schools[] = $school;
                continue;
            }

            $this->reportValidationError($context, $school->id, $data['name'], $errors);
        }

        $this->schools = array_merge($this->schools, $schools);
        $this->io->overwrite(' - Done');
    }

    /**
     * Returns an array with data specified by $fieldMap pulled out of the specified row
     *
     * @param int $row Current row number
     * @param int $firstCol First column to loop through
     * @param int $lastCol Last column to loop through
     * @param string[] $columnNames Names of each column
     * @param array $fieldMap Array of column name => entity field name pairs
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @return array
     */
    private function getData($row, $firstCol, $lastCol, $columnNames, $fieldMap)
    {
        $data = [];
        for ($col = $firstCol; $col <= $lastCol; $col++) {
            $colName = $columnNames[$col];
            if (!isset($fieldMap[$colName])) {
                continue;
            }
            $key = $fieldMap[$colName];
            $data[$key] = $this->importFile->getValue($col, $row);
        }

        return $data;
    }

    /**
     * Outputs a message regarding a validation error and aborts the script
     *
     * @param string $context Either 'school' or 'district'
     * @param int $id School or district ID
     * @param string $name School or district name
     * @param array $errors Array of validation errors
     * @return void
     */
    private function reportValidationError($context, $id, $name, $errors)
    {
        $msg = sprintf(
            "\nCannot update %s #%s (%s)\n%s",
            $context,
            $id,
            $name,
            $errors
                ? "Details:\n" . print_r($errors, true)
                : ' No details available. (Check for application rule violation)'
        );
        $this->io->error($msg);
        $this->abort();
    }

    /**
     * Returns a state record, creating it if necessary
     *
     * @param string $name State name or abbreviation
     * @return State|EntityInterface
     */
    private function getState($name)
    {
        $abbreviation = StatesTable::abbreviateName($name);
        $state = $this->statesTable->find()
            ->where(['abbreviation' => $abbreviation])
            ->first();
        if ($state) {
            return $state;
        }

        $fullName = StatesTable::unabbreviateName($name);
        $state = $this->statesTable->newEntity([
            'name' => $fullName,
            'abbreviation' => $abbreviation
        ]);
        if ($this->statesTable->save($state)) {
            $this->locationsAdded['states'][] = $fullName;

            return $state;
        }

        throw new InternalErrorException(
            'Error adding state. Details: ' . print_r($state->getErrors(), true)
        );
    }

    /**
     * Returns the specified county, creating it if necessary
     *
     * @param string $name County name
     * @param int $stateId State ID
     * @return County
     */
    private function getCounty($name, $stateId)
    {
        $conditions = [
            'name' => $name,
            'state_id' => $stateId
        ];
        /** @var County $county */
        $county = $this->countiesTable->find()
            ->where($conditions)
            ->first();

        if ($county) {
            return $county;
        }

        $county = $this->countiesTable->newEntity($conditions);
        if ($this->countiesTable->save($county)) {
            $this->locationsAdded['counties'][] = $name;

            return $county;
        }

        throw new InternalErrorException(
            'Error adding county. Details: ' . print_r($county->getErrors(), true)
        );
    }

    /**
     * Returns the specified city, creating it if necessary
     *
     * @param string $name City name
     * @param int $stateId State ID
     * @return City
     */
    private function getCity($name, $stateId)
    {
        $conditions = [
            'name' => $name,
            'state_id' => $stateId
        ];
        /** @var City $city */
        $city = $this->citiesTable->find()
            ->where($conditions)
            ->first();

        if ($city) {
            return $city;
        }

        $city = $this->citiesTable->newEntity($conditions);
        if ($this->citiesTable->save($city)) {
            $this->locationsAdded['cities'][] = $name;

            return $city;
        }

        throw new InternalErrorException(
            'Error adding city. Details: ' . print_r($city->getErrors(), true)
        );
    }

    /**
     * Returns the name of the school type associated with the specified worksheet name
     *
     * @param string $worksheetName Spreadsheet worksheet name
     * @return SchoolType
     */
    private function getSchoolType($worksheetName)
    {
        switch ($worksheetName) {
            case 'PUBLIC':
                return $this->allSchoolTypes['public'];
            case 'NONPUBLIC':
            case 'PRIVATE':
                return $this->allSchoolTypes['private'];
            case 'CHARTER':
                return $this->allSchoolTypes['charter'];
        }

        throw new InternalErrorException(
            'School type cannot be determined from worksheet name ' . $worksheetName
        );
    }

    /**
     * Displays data collected in $this->locationsAdded and clears it from that property
     *
     * @return void
     */
    private function reportAddedLocations()
    {
        foreach ($this->locationsAdded as $type => $locations) {
            if (!$locations) {
                continue;
            }
            $this->io->out(ucwords($type) . ' added:');
            sort($locations);
            foreach ($locations as $location) {
                $this->io->out(' - ' . $location);
            }
            $this->locationsAdded[$type] = [];
        }
    }

    /**
     * Save all stored schools/districts
     *
     * @return void
     */
    private function updateRecords()
    {
        foreach (Context::getContexts() as $context) {
            $entities = $context == 'school' ? $this->schools : $this->districts;
            if (!$entities) {
                continue;
            }

            $this->io->out("Updating {$context}s...");

            $progress = Utility::makeProgressBar(count($entities), $this->io);
            $table = $context == 'school' ? $this->schoolsTable : $this->districtsTable;
            foreach ($entities as $entity) {
                if (!$table->save($entity)) {
                    throw new InternalErrorException(
                        'Error saving district. District data: ' . print_r($entity, true)
                    );
                }

                $progress->increment(1);
                $progress->draw();
            }
            $this->io->overwrite(' - Done');
        }
    }

    /**
     * Returns the IDs of the grade records that match the 'low grade' to 'high grade' range in $data
     *
     * @param array $data Array of data from a row of the location data spreadsheet
     * @return array
     */
    private function getGradeIdsInRange(array $data)
    {
        $grades = $this->gradesTable->getGradesInRange(
            [
                'low' => $data['low grade'],
                'high' => $data['high grade']
            ],
            $this->allGrades
        );
        $gradeIds = [];
        foreach ($grades as $grade) {
            $gradeIds[] = $grade->id;
        }

        return $gradeIds;
    }

    /**
     * Returns TRUE if this appears to be a dummy phone number, e.g. (000) 000-0000
     *
     * For some reason, the IDOE lists dummy phone numbers for some schools and districts instead of leaving them blank
     *
     * @param string $phone A phone number
     * @return bool
     */
    private function isDummyPhone($phone)
    {
        return strpos($phone, '000-0000') !== false;
    }

    /**
     * Confirms every name change and reverts any that the user doesn't consent to
     *
     * @param string $context Either 'school' or 'district'
     * @return void
     */
    private function confirmNameChanges($context)
    {
        $records = $context == 'school' ? $this->schools : $this->districts;
        $table = $context == 'school' ? $this->schoolsTable : $this->districtsTable;
        foreach ($records as &$record) {
            if (!$record->isDirty('name')) {
                continue;
            }
            $response = $this->io->askChoice(
                sprintf(
                    "Change %s #%s's name from %s to %s?",
                    $context,
                    $record->id,
                    $record->getOriginal('name'),
                    $record->name
                ),
                ['y', 'n'],
                'n'
            );
            if ($response == 'n') {
                $record = $table->patchEntity($record, [
                    'name' => $record->getOriginal('name')
                ]);
            }
        }
        if ($context == 'school') {
            $this->schools = $records;
        } else {
            $this->districts = $records;
        }
    }
}
