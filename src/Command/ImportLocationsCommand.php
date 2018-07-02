<?php
namespace App\Command;

use App\Import\ImportFile;
use App\Model\Entity\City;
use App\Model\Entity\County;
use App\Model\Entity\School;
use App\Model\Entity\SchoolDistrict;
use App\Model\Entity\State;
use App\Model\Table\CitiesTable;
use App\Model\Table\CountiesTable;
use App\Model\Table\SchoolDistrictsTable;
use App\Model\Table\SchoolsTable;
use App\Model\Table\SchoolTypesTable;
use App\Model\Table\StatesTable;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Datasource\EntityInterface;
use Cake\Filesystem\Folder;
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;
use Exception;

/**
 * Class ImportLocationsCommand
 * @package App\Command
 * @property array $locationsAdded
 * @property CitiesTable $citiesTable
 * @property ConsoleIo $io
 * @property CountiesTable $countiesTable
 * @property ImportFile $importFile
 * @property School[] $schools
 * @property SchoolDistrict[] $districts
 * @property SchoolDistrictsTable $districtsTable
 * @property SchoolsTable $schoolsTable
 * @property SchoolTypesTable $schoolTypesTable
 * @property StatesTable $statesTable
 * @property string[] $files
 */
class ImportLocationsCommand extends Command
{
    private $citiesTable;
    private $countiesTable;
    private $districts = [];
    private $districtsTable;
    private $files;
    private $importFile;
    private $io;
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
        $this->citiesTable = TableRegistry::getTableLocator()->get('Cities');
        $this->countiesTable = TableRegistry::getTableLocator()->get('Counties');
        $this->districtsTable = TableRegistry::getTableLocator()->get('SchoolDistricts');
        $this->schoolsTable = TableRegistry::getTableLocator()->get('Schools');
        $this->schoolTypesTable = TableRegistry::getTableLocator()->get('SchoolTypes');
        $this->statesTable = TableRegistry::getTableLocator()->get('States');

        $file = $this->selectFile();
        if (!$file) {
            return;
        }
        $io->out($file . ' selected');

        $year = $this->getYearFromFilename($file);
        if (!$year) {
            return;
        }

        $io->out("Opening $file...");
        $dir = $this->getDirectory();
        $this->importFile = new ImportFile($year, $dir, $file, $io);
        $this->importFile->ignoreWorksheets(['closed']);
        if ($this->importFile->getError()) {
            $io->error($this->importFile->getError());

            return;
        }

        // Read in worksheet info and validate data
        $io->out('Analyzing worksheets...');
        $io->out();
        $this->importFile->read();
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

            $this->reportAddedLocations();

            $io->out();
        }

        /*
        ImportRunCommand::markFileProcessed($year, $file, $io);

        // Free up memory
        $this->importFile->spreadsheet->disconnectWorksheets();
        unset($this->importFile);
        */
    }

    /**
     * Returns the full path to the directory that contains school/district information
     *
     * @return string
     */
    private function getDirectory()
    {
        return ROOT . DS . 'data-location';
    }

    /**
     * Asks the user for input and returns a filename
     *
     * @return string|bool
     */
    private function selectFile()
    {
        $dataPath = $this->getDirectory();
        $this->files = (new Folder($dataPath))->find();
        if (!$this->files) {
            $this->io->out('No files found in ' . $dataPath);

            return false;
        }

        $this->io->out('Available files:');

        $tableData = [];
        foreach ($this->files as $key => $file) {
            $tableData[] = [$key + 1, $file];
        }
        array_unshift($tableData, ['Key', 'File']);
        $this->io->helper('Table')->output($tableData);

        $maxKey = (count($tableData) - 1);
        $fileKey = $this->io->ask('Select a file (1-' . $maxKey . '):');

        return $this->files[$fileKey - 1];
    }

    /**
     * Returns the year in a filename formatted like "foo (2018).xlsx", or false if no valid year is found
     *
     * @param string $file Filename
     * @return bool|string
     */
    private function getYearFromFilename($file)
    {
        $substr = strrchr($file, '(');

        if ($substr === false) {
            $this->io->error("No year found in filename. Please format filename like 'Foo (2018).xlsx'.");

            return false;
        }

        $year = substr($substr, 1, strpos($substr, ')') - 1);

        if (!ImportRunCommand::isYear($year)) {
            $this->io->error("$year is not a valid year");

            return false;
        }

        return $year;
    }

    /**
     * Returns the School or SchoolDistrict entity matching the provided $code
     *
     * @param string $context Either 'school' or 'district'
     * @param string $code The Department of Education identification code
     * @return array|\Cake\Datasource\EntityInterface
     */
    private function getLocation($context, $code)
    {
        $table = ($context == 'district') ? $this->districtsTable : $this->schoolsTable;

        return $table->find()
            ->where(['code' => Utility::removeLeadingZeros($code)])
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
            'COUNTY NAME' => 'county'
        ];

        /** @var ProgressHelper $progress */
        $progress = $this->io->helper('Progress');
        $progress->init([
            'total' => $lastRow - $firstRow + 1,
            'width' => 40,
        ]);
        $progress->draw();

        for ($row = $firstRow; $row <= $lastRow; $row++) {
            $progress->increment(1);
            $progress->draw();

            $data = $this->getData($row, $firstCol, $lastCol, $columnNames, $fieldMap);
            $data['code'] = Utility::removeLeadingZeros($data['code']);
            if (in_array($data['code'], $ignoredDistrictCodes)) {
                continue;
            }
            $state = $this->getState($data['state']);
            $county = $this->getCounty($data['county'], $state->id);
            $city = $this->getCity($data['city'], $state->id);
            if (!$this->citiesTable->Counties->link($city, [$county])) {
                $this->io->error('Error linking ' . $city->name . ' to ' . $county->name . ' County');
                $this->abort();
            }

            /** @var SchoolDistrict $district */
            $district = $this->getLocation($context, $data['code']);
            $district = $this->districtsTable->patchEntity($district, [
                'name' => $data['name'],
                'url' => $data['url'],
                'cities' => [$city],
                'counties' => [$county],
                'states' => [$state],
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
            'IDOE SCHOOL ID' => 'code',
            'SCHOOL NAME' => 'name',
            'ADDRESS' => 'address',
            'CITY' => 'city',
            'STATE' => 'state',
            'ZIP' => 'zip',
            'COUNTY NAME' => 'county',
            'SCHOOL HOMEPAGE' => 'url'
        ];

        /** @var ProgressHelper $progress */
        $progress = $this->io->helper('Progress');
        $progress->init([
            'total' => $lastRow - $firstRow + 1,
            'width' => 40,
        ]);
        $progress->draw();

        for ($row = $firstRow; $row <= $lastRow; $row++) {
            $progress->increment(1);
            $progress->draw();

            $data = $this->getData($row, $firstCol, $lastCol, $columnNames, $fieldMap);

            /** @var School $school */
            $school = $this->getLocation($context, $data['code']);

            // Get associated model data
            if (array_key_exists('district code', $data)) {
                $district = $this->districtsTable->find()
                    ->select(['id'])
                    ->where(['code' => Utility::removeLeadingZeros($data['district code'])])
                    ->firstOrFail();
            } else {
                $district = null;
            }
            $schoolTypeName = $this->getSchoolType($this->importFile->activeWorksheet);
            $schoolTypeId = $this->schoolTypesTable->findOrCreate(['name' => $schoolTypeName])->id;
            $state = $this->getState($data['state']);
            $county = $this->getCounty($data['county'], $state->id);
            $city = $this->getCity($data['city'], $state->id);

            // Prepare update
            $school = $this->schoolsTable->patchEntity($school, [
                'school_district_id' => $district ? $district->id : null,
                'school_type_id' => $schoolTypeId,
                'name' => $data['name'],
                'address' => sprintf(
                    "%s\n%s, %s%s",
                    $data['address'],
                    $data['city'],
                    $data['state'],
                    $data['zip'] ? " {$data['zip']}" : ''
                ),
                'url' => $data['url'],
                'cities' => [$city],
                'counties' => [$county],
                'states' => [$state]
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
     * @return \App\Model\Entity\County
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
     * @return string
     */
    private function getSchoolType($worksheetName)
    {
        switch ($worksheetName) {
            case 'PUBLIC':
                return 'public';
            case 'NONPUBLIC':
            case 'PRIVATE':
                return 'private';
            case 'CHARTER':
                return 'charter';
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
}