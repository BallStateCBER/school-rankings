<?php
namespace App\Import;

use App\Model\Entity\SchoolDistrict;
use App\Model\Table\MetricsTable;
use App\Model\Table\SchoolDistrictsTable;
use App\Model\Table\SchoolsTable;
use App\Model\Table\SpreadsheetColumnsMetricsTable;
use App\Model\Table\StatisticsTable;
use App\Shell\ImportShell;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Class ImportFile
 * @package App\Import
 * @property array $worksheets
 * @property bool $overwrite
 * @property ImportShell $shell
 * @property Spreadsheet $spreadsheet
 * @property string $filename
 * @property string $year
 * @property string|null $activeWorksheet
 * @property string|null $error
 */
class ImportFile
{
    private $error;
    private $filename;
    private $overwrite;
    private $shell;
    private $worksheets;
    private $year;
    public $activeWorksheet;
    public $spreadsheet;

    /**
     * ImportFile constructor
     *
     * @param string $year Year (subdirectory of /data)
     * @param string $filename Filename of spreadsheet to import
     * @param ImportShell $shell Shell object
     */
    public function __construct($year, $filename, $shell)
    {
        $type = 'Xlsx';
        $path = ROOT . DS . 'data' . DS . $year . DS . $filename;
        $this->year = $year;
        $this->filename = $filename;
        $this->shell = $shell;

        try {
            // Read spreadsheet
            /** @var Xlsx $reader */
            $reader = IOFactory::createReader($type);
            $reader->setReadDataOnly(true);
            $this->spreadsheet = $reader->load($path);

            // Analyze each worksheet
            foreach ($reader->listWorksheetInfo($path) as $worksheet) {
                $wsName = $worksheet['worksheetName'];
                $this->selectWorksheet($wsName);
                $this->worksheets[$wsName] = [
                    'context' => $this->getContext(),
                    'firstDataRow' => $this->getFirstDataRow(),
                    'firstDataCol' => $this->getFirstDataCol(),
                    'totalRows' => $worksheet['totalRows'],
                    'totalCols' => $worksheet['totalColumns']
                ];

                // The following methods depend on the values in the above array and must be handled separately
                $this->worksheets[$wsName]['groupings'] = $this->getGroupings();
                $this->worksheets[$wsName]['dataColumns'] = $this->getDataColumns();
                $this->worksheets[$wsName]['locations'] = $this->getLocations();
                $this->trimTotalRows();
            }
        } catch (Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    /**
     * Returns the current error message, or null
     *
     * @return string|null
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Returns the array of worksheet names for the current file
     *
     * @return array
     */
    public function getWorksheets()
    {
        return $this->worksheets;
    }

    /**
     * Returns specified information about the active worksheet
     *
     * @param string $property Array key for $this->worksheets['worksheetName']
     * @return mixed
     */
    public function getActiveWorksheetProperty($property)
    {
        return $this->worksheets[$this->activeWorksheet][$property];
    }

    /**
     * Sets the specified worksheet as the currently active worksheet
     *
     * @param string $worksheet Worksheet name
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @return void
     */
    public function selectWorksheet($worksheet)
    {
        $worksheet = (string)$worksheet;
        $this->spreadsheet->setActiveSheetIndexByName($worksheet);
        $this->activeWorksheet = $worksheet;
    }

    /**
     * Returns the value in the specified cell for the active worksheet
     *
     * @param int $col Column index (starting at one)
     * @param int $row Row index (starting at one)
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @return mixed
     */
    public function getValue($col, $row)
    {
        $value = $this->getCell($col, $row)->getValue();

        return is_string($value) ? trim($value) : $value;
    }

    /**
     * Returns a PhpSpreadsheet cell object
     *
     * @param int $col Column index (starting at one)
     * @param int $row Row index (starting at one)
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @return null|\PhpOffice\PhpSpreadsheet\Cell\Cell
     */
    public function getCell($col, $row)
    {
        return $this->spreadsheet->getActiveSheet()->getCellByColumnAndRow($col, $row);
    }

    /**
     * Returns the index of the first row on which statistical data will be read
     *
     * @return int
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws Exception
     */
    private function getFirstDataRow()
    {
        for ($row = 1; $row <= 3; $row++) {
            for ($col = 1; $col <= 4; $col++) {
                if ($this->isLocationHeader($col, $row)) {
                    return $row + 1;
                }
            }
        }

        throw new Exception('First data row could not be found');
    }

    /**
     * Returns the index of the first column on which statistical data will be read
     *
     * Assumed to be the first column after the school/district identifier columns
     *
     * @return int
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws Exception
     */
    private function getFirstDataCol()
    {
        for ($row = 1; $row <= 3; $row++) {
            $isLocationHeaderRow = false;
            for ($col = 1; $col <= 5; $col++) {
                $isLocationHeader = $this->isLocationHeader($col, $row);
                if ($isLocationHeader) {
                    $isLocationHeaderRow = true;
                } elseif ($isLocationHeaderRow) {
                    return $col;
                }
            }
        }

        throw new Exception('First data col could not be found');
    }

    /**
     * Returns true or false, indicating whether or not the given cell is a header for a school/district id/name column
     *
     * @param int $col Column index (starting with one)
     * @param int $row Row index (starting with one)
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @return bool
     */
    private function isLocationHeader($col, $row)
    {
        return $this->isDistrictCodeHeader($col, $row)
            || $this->isDistrictNameHeader($col, $row)
            || $this->isSchoolCodeHeader($col, $row)
            || $this->isSchoolNameHeader($col, $row);
    }

    /**
     * Returns a string indicating if the column is districtCode, districtName, schoolCode, or schoolName
     *
     * @param int $col Column number
     * @return string
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws Exception
     */
    private function getLocationColumnType($col)
    {
        $headerRow = $this->getActiveWorksheetProperty('firstDataRow') - 1;

        if ($this->isDistrictCodeHeader($col, $headerRow)) {
            return 'districtCode';
        }

        if ($this->isDistrictNameHeader($col, $headerRow)) {
            return 'districtName';
        }

        if ($this->isSchoolCodeHeader($col, $headerRow)) {
            return 'schoolCode';
        }

        if ($this->isSchoolNameHeader($col, $headerRow)) {
            return 'schoolName';
        }

        throw new Exception('Unrecognized location column type in column ' . $col);
    }

    /**
     * Returns the active worksheet's context (school or district)
     *
     * @return string
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws Exception
     */
    public function getContext()
    {
        if (isset($this->worksheets[$this->activeWorksheet]['context'])) {
            return $this->worksheets[$this->activeWorksheet]['context'];
        }

        for ($row = 1; $row <= 2; $row++) {
            $isSchoolContext = (
                    $this->isSchoolCodeHeader(1, $row)
                    && $this->isSchoolNameHeader(2, $row)
                ) || (
                    $this->isDistrictCodeHeader(1, $row)
                    && $this->isDistrictNameHeader(2, $row)
                    && $this->isSchoolCodeHeader(3, $row)
                    && $this->isSchoolNameHeader(4, $row)
                );
            if ($isSchoolContext) {
                return 'school';
            }

            $isDistrictContext = $this->isDistrictCodeHeader(1, $row)
                && $this->isDistrictNameHeader(2, $row)
                && !$this->isSchoolCodeHeader(3, $row)
                && !$this->isSchoolNameHeader(4, $row);
            if ($isDistrictContext) {
                return 'district';
            }
        }

        throw new Exception('Cannot determine school/district context of worksheet ' . $this->activeWorksheet);
    }

    /**
     * Returns true if the given cell contains a header for a district code column
     *
     * Known values:
     * Corp / Corp ID / IDOE_CORPORATION_ID / CORP ID / Corporation Id / Corp. Id / Corp. ID
     *
     * @param int $col Column index (starting at one)
     * @param int $row Row index (starting at one)
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @return bool
     */
    private function isdistrictCodeHeader($col, $row)
    {
        $value = $this->getValue($col, $row);

        // Attempt to normalize all the variations of this header
        $value = strtolower($value);
        $value = str_replace([' ', '_', '.', 'idoe'], '', $value);
        $value = str_replace('corporation', 'corp', $value);

        return in_array($value, ['corp', 'corpid']);
    }

    /**
     * Returns true if the given cell contains a header for a district name column
     *
     * Known values:
     * Corp Name / Corp name / CORPORATION_NAME / CORPORATION NAME / Corporation Name / Corp. Name
     *
     * @param int $col Column index (starting at one)
     * @param int $row Row index (starting at one)
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @return bool
     */
    private function isDistrictNameHeader($col, $row)
    {
        $value = $this->getValue($col, $row);

        // Attempt to normalize all the variations of this header
        $value = strtolower($value);
        $value = str_replace([' ', '_', '.'], '', $value);
        $value = str_replace('corporation', 'corp', $value);

        return $value == 'corpname';
    }

    /**
     * Returns true if the given cell contains a header for a school code column
     *
     * Known values:
     * School / School ID / Sch ID / IDOE_SCHOOL_ID / SCH ID / Schl. Id
     *
     * @param int $col Column index (starting at one)
     * @param int $row Row index (starting at one)
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @return bool
     */
    private function isSchoolCodeHeader($col, $row)
    {
        $value = $this->getValue($col, $row);

        // Attempt to normalize all the variations of this header
        $value = strtolower($value);
        $value = str_replace([' ', '_', '.', 'idoe'], '', $value);

        return in_array($value, ['school', 'schoolid', 'schid', 'schlid']);
    }

    /**
     * Returns true if the given cell contains a header for a school name column
     *
     * Known values:
     * School Name / SCHOOL_NAME / SCHOOL NAME / Schl. Name
     *
     * @param int $col Column index (starting at one)
     * @param int $row Row index (starting at one)
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @return bool
     */
    private function isSchoolNameHeader($col, $row)
    {
        $value = $this->getValue($col, $row);

        // Attempt to normalize all the variations of this header
        $value = strtolower($value);
        $value = str_replace([' ', '_', '.'], '', $value);

        return in_array($value, ['schoolname', 'schlname', 'schname']);
    }

    /**
     * Returns an array of information about how columns are grouped in the current worksheet
     *
     * e.g. by grade, ethnic group, etc.
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws Exception
     * @return array|null
     */
    public function getGroupings()
    {
        // Check that the second row is the column header row
        $col = 1;
        $row = 2;
        if (!$this->isLocationHeader($col, $row)) {
            return null;
        }

        // Check that the first row contains the same number of blank cells as there are location identifier columns
        $row = 1;
        $firstDataCol = $this->getActiveWorksheetProperty('firstDataCol');
        for ($col = 1; $col < $firstDataCol; $col++) {
            $value = $this->getValue($col, $row);
            if (!empty($value)) {
                throw new Exception('Error: Grouping row contains values in location identifier column(s)');
            }
        }

        $lastDataCol = $this->getActiveWorksheetProperty('totalCols');
        $groupings = [];
        $previousGroup = null;
        for ($col = $firstDataCol; $col <= $lastDataCol; $col++) {
            $value = $this->getValue($col, $row);
            if (empty($value)) {
                continue;
            }
            if ($previousGroup) {
                $groupings[$previousGroup]['end'] = $col - 1;
            }
            $groupings[$value] = ['start' => $col];
            $previousGroup = $value;
        }

        if (!$groupings) {
            throw new Exception('Error: Groupings row is blank');
        }

        $groupings[$previousGroup]['end'] = $col - 1;

        return $groupings;
    }

    /**
     * Returns an array of information about the data columns for the selected worksheet
     *
     * @return array
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws Exception
     */
    private function getDataColumns()
    {
        $row = empty($this->getActiveWorksheetProperty('groupings')) ? 1 : 2;
        $col = 1;
        if (!$this->isLocationHeader($col, $row)) {
            throw new Exception('Can\'t find column header row');
        }

        $dataColumns = [];
        $lastDataCol = $this->getActiveWorksheetProperty('totalCols');
        for ($col = 2; $col <= $lastDataCol; $col++) {
            if ($this->isLocationHeader($col, $row)) {
                continue;
            }
            $colName = $this->getValue($col, $row);
            $colGroup = $this->getColGroup($col);
            $dataColumns[$col] = [
                'name' => $colName,
                'group' => $colGroup,
                'metricId' => $this->getMetricId($colGroup, $colName)
            ];
        }

        return $dataColumns;
    }

    /**
     * Returns the SchoolMetric ID or SchoolDistrictMetric ID associated with the given column, or NULL
     *
     * @param string $colGroup The name of the grouping that the current column is part of
     * @param string $colName The name of the current column
     * @return int|null
     */
    private function getMetricId($colGroup, $colName)
    {
        /** @var SpreadsheetColumnsMetricsTable $table */
        $table = TableRegistry::get('SpreadsheetColumnsMetrics');

        return $table->getMetricId([
            'year' => $this->year,
            'filename' => $this->filename,
            'context' => $this->getActiveWorksheetProperty('context'),
            'worksheet' => $this->activeWorksheet,
            'group_name' => $colGroup,
            'column_name' => $colName
        ]);
    }

    /**
     * Returns the name of the relevant column group, or null if this worksheet has no column groups
     *
     * @param int $col Column index (starting at one)
     * @return null|string
     * @throws Exception
     */
    private function getColGroup($col)
    {
        if (empty($this->getActiveWorksheetProperty('groupings'))) {
            return null;
        }

        foreach ($this->getActiveWorksheetProperty('groupings') as $groupName => $groupInfo) {
            if ($col >= $groupInfo['start'] && $col <= $groupInfo['end']) {
                return $groupName;
            }
        }

        throw new Exception('Error: Column ' . $col . ' not captured by any column group');
    }

    /**
     * Returns information about what columns have nonblank titles and no metric ID
     *
     * @return array
     */
    public function getUnknownMetrics()
    {
        $unknownMetrics = [];
        foreach ($this->getActiveWorksheetProperty('dataColumns') as $colNum => $column) {
            if ($column['name'] && !$column['metricId']) {
                $unknownMetrics[$colNum] = $column;
            }
        }

        return $unknownMetrics;
    }

    /**
     * Returns the value of $this->year
     *
     * @return string
     */
    public function getYear()
    {
        return $this->year;
    }

    /**
     * Returns the value of $this->filename
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Sets metric ID in $this->dataColumns
     *
     * @param int $colNum Column number
     * @param int $metricId SchoolMetric ID or SchoolDistrictMetric ID
     * @return void
     * @throws Exception
     */
    public function setMetricId($colNum, $metricId)
    {
        if ($this->worksheets[$this->activeWorksheet]['dataColumns'][$colNum]['metricId']) {
            throw new Exception('Cannot set metric ID; metric ID already set');
        }

        $context = $this->getActiveWorksheetProperty('context');
        if (!MetricsTable::recordExists($context, $metricId)) {
            throw new Exception(ucwords($context) . ' metric ID ' . $metricId . ' not found');
        }

        $this->worksheets[$this->activeWorksheet]['dataColumns'][$colNum]['metricId'] = $metricId;
    }

    /**
     * Returns an array of $row => $location, with $location keys districtCode, districtName, schoolCode, and schoolName
     *
     * @return array
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function getLocations()
    {
        $locations = [];
        $firstRow = $this->getActiveWorksheetProperty('firstDataRow');
        $lastRow = $this->getActiveWorksheetProperty('totalRows');
        $lastCol = $this->getActiveWorksheetProperty('firstDataCol') - 1;
        for ($row = $firstRow; $row <= $lastRow; $row++) {
            $location = [];
            for ($col = 1; $col <= $lastCol; $col++) {
                $type = $this->getLocationColumnType($col);

                $value = $this->getValue($col, $row);
                if ($value == '') {
                    continue;
                }
                if ($type == 'districtCode' || $type == 'schoolCode') {
                    $value = $this->removeLeadingZeros($value);
                }
                if ($type == 'districtCode' && SchoolDistrict::isDummyCode($value)) {
                    continue;
                }

                $location[$type] = $value;
            }

            if ($location) {
                $locations[$row] = $location;
            }
        }

        return $locations;
    }

    /**
     * Strips out leading zeros from a string
     *
     * @param string $string String to remove leading zeros from
     * @return string
     */
    private function removeLeadingZeros($string)
    {
        return ltrim($string, '0');
    }

    /**
     * Sets location information for the specified row
     *
     * @param int $rowNum Row number
     * @param string $var Array key for location information
     * @param mixed $val Value to write to array
     * @return void
     */
    public function setLocationInfo($rowNum, $var, $val)
    {
        $this->worksheets[$this->activeWorksheet]['locations'][$rowNum][$var] = $val;
    }

    /**
     * Loops through all data cells in the active worksheet and aborts script if any are invalid
     *
     * @return void
     */
    public function validateData()
    {
        $this->shell->out('Validating data...');

        $ws = $this->getWorksheets()[$this->activeWorksheet];
        $dataRowCount = $ws['totalRows'] - ($ws['firstDataRow'] - 1);
        $dataColCount = $ws['totalCols'] - ($ws['firstDataCol'] - 1);

        /** @var ProgressHelper $progress */
        $progress = $this->shell->helper('Progress');
        $progress->init([
            'total' => $dataRowCount * $dataColCount,
            'width' => 40,
        ]);
        $progress->draw();

        $datum = new Datum();
        $invalidData = [];
        for ($row = $ws['firstDataRow']; $row <= $ws['totalRows']; $row++) {
            for ($col = $ws['firstDataCol']; $col <= $ws['totalCols']; $col++) {
                try {
                    $value = $this->getValue($col, $row);
                    $cell = $this->getCell($col, $row);
                    if (!$datum->isValid($value, $cell)) {
                        $invalidData[] = compact('col', 'row', 'value');
                    }
                } catch (Exception $e) {
                    $value = '(Cannot read value)';
                    $invalidData[] = compact('col', 'row', 'value');
                }
                $progress->increment(1);
                $progress->draw();
            }
        }

        if ($invalidData) {
            $limit = 10;
            $count = count($invalidData);
            if ($count > $limit) {
                $invalidData = array_slice($invalidData, 0, $limit);
            }

            $this->shell->getIo()->overwrite('Data errors:');
            array_unshift($invalidData, ['Col', 'Row', 'Invalid value']);
            $this->shell->helper('Table')->output($invalidData);
            if (count($invalidData) < $count) {
                $difference = $count - count($invalidData);
                $msg = '+ ' . $difference . ' more invalid ' . __n('value', 'values', $difference);
                $this->shell->out($msg);
            }
            $this->shell->abort('Cannot continue. Invalid data found.');
        }

        $this->shell->getIo()->overwrite(' - Done');
    }

    /**
     * Identifies all metrics used in this worksheet
     *
     * Checks the database for already-identified metrics and interacts with the user if necessary
     *
     * @return void
     */
    public function identifyMetrics()
    {
        $this->shell->out('Identifying metrics...');
        $unknownMetrics = $this->getUnknownMetrics();
        if (!$unknownMetrics) {
            $this->shell->out(' - Done');

            return;
        }

        $context = $this->getWorksheets()[$this->activeWorksheet]['context'];
        $count = count($unknownMetrics);
        $msg = $count . ' new ' . __n('metric', 'metrics', $count) . ' found' . "\n" .
            "Options for each:\n" .
            " - Enter an existing $context metric ID\n" .
            " - Enter the name of a new metric to create \n" .
            " - Enter nothing to accept the suggested name";
        $this->shell->out($msg);

        $filename = $this->getFilename();
        $worksheetName = $this->activeWorksheet;
        $import = new Import();
        foreach ($unknownMetrics as $colNum => $unknownMetric) {
            $cleanColName = str_replace("\n", ' ', $unknownMetric['name']);
            $this->shell->info("\nColumn: $cleanColName");
            $suggestedName = $import->getSuggestedName($filename, $worksheetName, $unknownMetric);
            $this->shell->out('Suggested metric name: ' . $suggestedName);
            try {
                $metricId = $this->getMetricInput($suggestedName, $unknownMetric);
                $this->setMetricId($colNum, $metricId);
            } catch (\Exception $e) {
                $this->shell->err('Error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Identifies all schools/districts used in this worksheet
     *
     * Checks the database for already-identified schools/districts and interacts with the user if necessary
     *
     * @return void
     * @throws Exception
     */
    public function identifyLocations()
    {
        /**
         * @var SchoolDistrictsTable $schoolDistrictsTable
         * @var SchoolsTable $schoolsTable
         */
        $schoolDistrictsTable = TableRegistry::get('SchoolDistricts');
        $schoolsTable = TableRegistry::get('Schools');
        $context = $this->getWorksheets()[$this->activeWorksheet]['context'];
        $subject = ($context == 'district' ? 'districts' : 'schools/districts');
        $this->shell->out("Identifying $subject...");

        foreach ($this->getLocations() as $rowNum => $location) {
            $districtId = null;
            if (isset($location['districtCode']) && isset($location['districtName'])) {
                $districtId = $schoolDistrictsTable->getOrCreate(
                    $location['districtCode'],
                    $location['districtName'],
                    $this->shell
                );
                $this->setLocationInfo($rowNum, 'districtId', $districtId);
            } elseif (isset($location['districtCode'])) {
                throw new Exception('District name missing in row ' . $rowNum);
            }

            $schoolId = null;
            if (isset($location['schoolCode']) && isset($location['schoolName'])) {
                $schoolId = $schoolsTable->getOrCreate(
                    $location['schoolCode'],
                    $location['schoolName'],
                    $districtId,
                    $this->shell
                );
                $this->setLocationInfo($rowNum, 'schoolId', $schoolId);
            } elseif (isset($location['schoolCode']) || isset($location['schoolName'])) {
                throw new Exception('Incomplete school information in row ' . $rowNum);
            }
        }
        $this->shell->out(' - Done');
    }

    /**
     * Interacts with the user and returns a metric ID, creating a new metric record if appropriate
     *
     * @param string $suggestedName Default name for this metric
     * @param array $unknownMetric Array of name and group information for the current column
     * @return int
     * @throws Exception
     */
    private function getMetricInput($suggestedName, $unknownMetric)
    {
        $input = $this->shell->in('Metric ID or name:');
        $context = $this->getContext();

        // Existing metric ID entered
        if (is_numeric($input)) {
            $metricId = (int)$input;
            if (!MetricsTable::recordExists($context, $metricId)) {
                $this->shell->err(ucwords($context) . ' metric ID ' . $metricId . ' not found');

                return $this->getMetricInput($suggestedName, $unknownMetric);
            }

            return $metricId;
        }

        // Name of new metric entered
        try {
            $metricName = $input ?: $suggestedName;
            $metric = MetricsTable::addRecord($context, $metricName);
            if (!$metric) {
                throw new Exception('Metric could not be saved.');
            }
            $this->shell->out('Metric #' . $metric->id . ' added');

            /** @var SpreadsheetColumnsMetricsTable $ssColsMetricsTable */
            $ssColsMetricsTable = TableRegistry::get('SpreadsheetColumnsMetrics');
            $ssColsMetricsTable->add($this, $unknownMetric, $metric->id);

            return $metric->id;
        } catch (Exception $e) {
            $this->shell->err('Error: ' . $e->getMessage());

            return $this->getMetricInput($suggestedName, $unknownMetric);
        }
    }

    /**
     * Adjusts totalRows to not include rows at the end of the file with blank values in their first column
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @return void
     */
    private function trimTotalRows()
    {
        for ($row = $this->worksheets[$this->activeWorksheet]['totalRows']; $row >= 1; $row--) {
            $col = 1;
            if ($this->getValue($col, $row)) {
                $this->worksheets[$this->activeWorksheet]['totalRows'] = $row;
                break;
            }
        }
    }

    /**
     * Returns the value of $this->overwrite, indicating whether overwriting is allowed, and prompts input if unset
     *
     * @return bool
     */
    private function getOverwrite()
    {
        if (isset($this->overwrite)) {
            return $this->overwrite;
        }

        $input = $this->shell->in("\nOverwrite statistics that have already been recorded?", ['y', 'n']);
        $this->overwrite = ($input == 'y');

        return $this->overwrite;
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @return void
     */
    public function recordData()
    {
        $this->shell->out('Importing data...');

        $ws = $this->getWorksheets()[$this->activeWorksheet];
        $dataRowCount = $ws['totalRows'] - ($ws['firstDataRow'] - 1);
        $dataColCount = $ws['totalCols'] - ($ws['firstDataCol'] - 1);

        /** @var ProgressHelper $progress */
        $progress = $this->shell->helper('Progress');
        $progress->init([
            'total' => $dataRowCount * $dataColCount,
            'width' => 40,
        ]);
        $progress->draw();

        $datum = new Datum();
        $context = $this->getContext();
        $year = $this->year;
        $counts = [
            'added' => 0,
            'updated' => 0,
            'ignored' => 0
        ];
        $table = StatisticsTable::getContextTable($context);
        for ($row = $ws['firstDataRow']; $row <= $ws['totalRows']; $row++) {

            // Skip rows with no location
            if (!isset($this->worksheets[$this->activeWorksheet]['locations'][$row])) {
                break;
            }

            $locationIdKey = ($context == 'school') ? 'schoolId' : 'districtId';
            $locationId = $this->worksheets[$this->activeWorksheet]['locations'][$row][$locationIdKey];
            for ($col = $ws['firstDataCol']; $col <= $ws['totalCols']; $col++) {
                $progress->increment(1);
                $progress->draw();

                $value = $this->getValue($col, $row);
                if ($datum->isIgnorable($value)) {
                    continue;
                }

                $metricId = $this->worksheets[$this->activeWorksheet]['dataColumns'][$col]['metricId'];
                $existingStat = StatisticsTable::getStatistic($context, $metricId, $locationId, $year);

                // Add
                if (!$existingStat) {
                    $locationIdField = ($context == 'school') ? 'school_id' : 'school_district_id';
                    $statistic = $table->newEntity([
                        'metric_id' => $metricId,
                        $locationIdField => $locationId,
                        'value' => $value,
                        'year' => $this->year,
                        'file' => $this->filename,
                        'contiguous' => true
                    ]);
                    if ($statistic->getErrors()) {
                        $errors = print_r($statistic->getErrors(), true);
                        $this->shell->abort("Error adding statistic. Details: \n" . $errors);

                        return;
                    } else {
                        $table->save($statistic);
                    }
                    $counts['added']++;
                    continue;
                }

                // Update
                if ($this->getOverwrite()) {
                    $statistic = $table->get($existingStat['id']);
                    $table->patchEntity($statistic, ['value' => $value]);
                    if ($statistic->getErrors()) {
                        $errors = print_r($statistic->getErrors(), true);
                        $this->shell->abort("Error updating statistic. Details: \n" . $errors);

                        return;
                    } else {
                        $table->save($statistic);
                    }
                    $counts['updated']++;
                    continue;
                }

                $counts['ignored']++;
            }
        }

        $this->shell->getIo()->overwrite(' - Done');
        foreach ($counts as $action => $count) {
            if (!$count) {
                continue;
            }
            $msg = " - $count " . __n('stat ', 'stats ', $count) . $action;
            $this->shell->out($msg);
        }
    }
}
