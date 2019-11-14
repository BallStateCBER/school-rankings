<?php
namespace App\Import;

use App\Command\Utility;
use App\Model\Entity\Metric;
use App\Model\Entity\School;
use App\Model\Entity\SchoolDistrict;
use App\Model\Entity\Statistic;
use App\Model\Table\MetricsTable;
use App\Model\Table\SchoolDistrictCodesTable;
use App\Model\Table\SchoolDistrictsTable;
use App\Model\Table\SchoolsTable;
use App\Model\Table\SpreadsheetColumnsMetricsTable;
use App\Model\Table\StatisticsTable;
use Cake\Console\ConsoleIo;
use Cake\Database\Expression\QueryExpression;
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;
use Exception;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Exception as PhpOfficeException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use ZipArchive;

/**
 * Class ImportFile
 * @package App\Import
 * @property array $worksheets
 * @property bool $acceptMetricSuggestions
 * @property bool $autoNameMetrics
 * @property bool $overwrite
 * @property ConsoleIo $shell_io
 * @property MetricsTable $metricsTable
 * @property Spreadsheet $spreadsheet
 * @property SpreadsheetColumnsMetricsTable $spreadsheetColsMetricsTable
 * @property StatisticsTable $statisticsTable
 * @property string $filename
 * @property string $path
 * @property string $year
 * @property string[] $ignoredWorksheets
 * @property string|null $activeWorksheet
 * @property string|null $error
 */
class ImportFile
{
    private $autoNameMetrics;
    private $counts;
    private $error;
    private $filename;
    private $ignoredWorksheets = ['Sources'];
    private $isPercentMetric = [];
    private $metricsTable;
    private $overwrite;
    private $path;
    private $shell_io;
    private $spreadsheetColsMetricsTable;
    private $statisticsTable;
    private $worksheets;
    private $year;
    public $acceptMetricSuggestions;
    public $activeWorksheet;
    public $automaticallyAddLocations = true;
    public $spreadsheet;

    /**
     * ImportFile constructor
     *
     * @param string $year Year (subdirectory of /data)
     * @param string $dir Full path to the directory containing the spreadsheet
     * @param string $filename Filename of spreadsheet to import
     * @param ConsoleIo $io Console IO object
     */
    public function __construct($year, $dir, $filename, $io)
    {
        // Make sure $dir ends with a directory-separator character
        if (substr($dir, -1, 1) != DS) {
            $dir .= DS;
        }

        $this->path = $dir . $filename;
        $this->year = $year;
        $this->filename = $filename;
        $this->shell_io = $io;
        $this->metricsTable = TableRegistry::getTableLocator()->get('Metrics');
        $this->spreadsheetColsMetricsTable = TableRegistry::getTableLocator()->get('SpreadsheetColumnsMetrics');
        $this->statisticsTable = TableRegistry::getTableLocator()->get('Statistics');

        $zip = new ZipArchive();
        $readable = $zip->open($this->path);
        if ($readable !== true) {
            $this->error = $msg = 'Error opening ' . $filename . "\n" . $this->getZipArchiveErrorMsg($readable);

            return;
        }
        $zip->close();
    }

    /**
     * Reads the file and populates the 'spreadsheet' and 'worksheets' properties
     *
     * @return void
     */
    public function read()
    {
        try {
            // Read spreadsheet
            /** @var Xlsx $reader */
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $this->spreadsheet = $reader->load($this->path);

            // Analyze each worksheet
            foreach ($reader->listWorksheetInfo($this->path) as $worksheet) {
                $wsName = $worksheet['worksheetName'];
                if (in_array($wsName, $this->ignoredWorksheets)) {
                    continue;
                }

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
     * @return void
     * @throws PhpOfficeException
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
     * @throws PhpOfficeException
     * @return string|int|float
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
     * @return null|Cell
     * @throws PhpOfficeException
     */
    public function getCell($col, $row)
    {
        return $this->spreadsheet->getActiveSheet()->getCellByColumnAndRow($col, $row);
    }

    /**
     * Returns the index of the first row on which statistical data will be read
     *
     * @return int
     * @throws PhpOfficeException
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
     * @throws PhpOfficeException
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
     * @throws PhpOfficeException
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
     * @throws PhpOfficeException
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
     * @throws PhpOfficeException
     * @throws Exception
     */
    public function getContext()
    {
        // Context has already been determined
        if (isset($this->worksheets[$this->activeWorksheet]['context'])) {
            return $this->worksheets[$this->activeWorksheet]['context'];
        }

        // Context can be determined from the worksheet name
        switch ($this->activeWorksheet) {
            case 'Schools':
                return 'school';
            case 'Corporations':
                return 'district';
        }

        // Context can be determined from the column headers
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
     * Corp / Corp ID / IDOE_CORPORATION_ID / CORP ID / Corporation Id / Corp. Id / Corp. ID / Corp. No
     *
     * @param int $col Column index (starting at one)
     * @param int $row Row index (starting at one)
     * @throws PhpOfficeException
     * @return bool
     */
    private function isDistrictCodeHeader($col, $row)
    {
        $value = $this->getValue($col, $row);

        // Attempt to normalize all the variations of this header
        $value = strtolower($value);
        $value = str_replace([' ', '_', '.', 'idoe'], '', $value);
        $value = str_replace('corporation', 'corp', $value);

        return in_array($value, ['corp', 'corpcode', 'corpid', 'corpno']);
    }

    /**
     * Returns true if the given cell contains a header for a district name column
     *
     * Known values:
     * Corp Name / Corp name / CORPORATION_NAME / CORPORATION NAME / Corporation Name / Corp. Name
     *
     * @param int $col Column index (starting at one)
     * @param int $row Row index (starting at one)
     * @throws PhpOfficeException
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
     * School / School ID / Sch ID / IDOE_SCHOOL_ID / SCH ID / Schl. Id / Sch No
     *
     * @param int $col Column index (starting at one)
     * @param int $row Row index (starting at one)
     * @throws PhpOfficeException
     * @return bool
     */
    private function isSchoolCodeHeader($col, $row)
    {
        $value = $this->getValue($col, $row);

        // Attempt to normalize all the variations of this header
        $value = strtolower($value);
        $value = str_replace([' ', '_', '.', 'idoe'], '', $value);

        return in_array($value, ['school', 'schoolcode', 'schoolid', 'schid', 'schlid', 'schno', 'schl']);
    }

    /**
     * Returns true if the given cell contains a header for a school name column
     *
     * Known values:
     * School Name / SCHOOL_NAME / SCHOOL NAME / Schl. Name / Sch Name
     *
     * @param int $col Column index (starting at one)
     * @param int $row Row index (starting at one)
     * @throws PhpOfficeException
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
     * @throws PhpOfficeException
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
        unset($value);

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
        unset($lastDataCol, $value);

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
     * @throws PhpOfficeException
     * @throws Exception
     */
    private function getDataColumns()
    {
        $row = $this->hasGroupingRow() ? 2 : 1;
        $col = 1;
        if (!$this->isLocationHeader($col, $row)) {
            throw new Exception('Can\'t find column header row');
        }
        unset($col);

        $dataColumns = [];
        $lastDataCol = $this->getActiveWorksheetProperty('totalCols');
        $invalidMetricIdMsgs = [];
        for ($col = 2; $col <= $lastDataCol; $col++) {
            // Skip if this is a location column
            if ($this->isLocationHeader($col, $row)) {
                continue;
            }

            // Process statistical data column
            $colName = $this->getValue($col, $row);
            $colGroup = $this->getColGroup($col);
            $metricId = $this->getMetricId($colGroup, $colName);
            $dataColumns[$col] = [
                'name' => $colName,
                'group' => $colGroup,
                'metricId' => $metricId
            ];

            // Check if non-null metric ID is invalid
            if ($metricId && !$this->metricsTable->exists(['id' => $metricId])) {
                $invalidMetricIdMsgs[] = sprintf(
                    "%s > %s > %s (col #%s) corresponds to metric #%s, which doesn't exist",
                    $this->activeWorksheet,
                    str_replace("\n", ' ', $colGroup),
                    str_replace("\n", ' ', $colName),
                    $col,
                    $metricId
                );
            }
        }
        unset($lastDataCol, $colName, $colGroup);

        // Abort if invalid metric IDs are found
        if ($invalidMetricIdMsgs) {
            throw new Exception(implode("\n", $invalidMetricIdMsgs));
        }

        return $dataColumns;
    }

    /**
     * Returns the Metric ID associated with the given column, or NULL
     *
     * @param string $colGroup The name of the grouping that the current column is part of
     * @param string $colName The name of the current column
     * @return int|null
     */
    private function getMetricId($colGroup, $colName)
    {
        return $this->spreadsheetColsMetricsTable->getMetricId([
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
        if (!$this->hasGroupingRow()) {
            return null;
        }

        foreach ($this->getActiveWorksheetProperty('groupings') as $groupName => $groupInfo) {
            if ($col >= $groupInfo['start'] && $col <= $groupInfo['end']) {
                unset($groupInfo, $col);

                return $groupName;
            }
        }

        throw new Exception(sprintf(
            'Error: Column %s of worksheet %s not captured by any column group',
            $col,
            $this->activeWorksheet
        ));
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
        unset($colNum, $column);

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
     * @param int $metricId Metric ID
     * @return void
     * @throws Exception
     */
    public function setMetricId($colNum, $metricId)
    {
        if ($this->worksheets[$this->activeWorksheet]['dataColumns'][$colNum]['metricId']) {
            throw new Exception('Cannot set metric ID; metric ID already set');
        }

        if (!$this->metricsTable->exists(['id' => $metricId])) {
            $context = $this->getActiveWorksheetProperty('context');
            throw new Exception(ucwords($context) . ' metric ID ' . $metricId . ' not found');
        }

        $this->worksheets[$this->activeWorksheet]['dataColumns'][$colNum]['metricId'] = $metricId;
    }

    /**
     * Returns an array of $row => $location, with $location keys districtCode, districtName, schoolCode, and schoolName
     *
     * @return array
     * @throws PhpOfficeException
     */
    public function getLocations()
    {
        // Get variables for iteration
        $firstRow = $this->getActiveWorksheetProperty('firstDataRow');
        $lastRow = $this->getActiveWorksheetProperty('totalRows');
        $lastCol = $this->getActiveWorksheetProperty('firstDataCol') - 1;

        // Iterate through all rows
        $locations = [];
        for ($row = $firstRow; $row <= $lastRow; $row++) {
            // Iterate through all columns with location info in this row
            $location = [];
            for ($col = 1; $col <= $lastCol; $col++) {
                $type = $this->getLocationColumnType($col);
                $value = $this->getValue($col, $row);

                if ($type == 'districtCode' || $type == 'schoolCode') {
                    $value = Utility::removeLeadingZeros($value);
                }

                // Skip if this location is blank or a dummy location (e.g. code is "N/A" or "-0999")
                $isDummyDistrict = $type == 'districtCode' && SchoolDistrict::isDummyCode($value);
                if ($value == '' || $isDummyDistrict) {
                    unset($type, $value);
                    continue;
                }

                $location[$type] = $value;
                unset($type, $value);
            }

            if ($location) {
                $locations[$row] = $location;
            }
            unset($location);
        }
        unset($firstRow, $lastRow, $lastCol, $col);

        return $locations;
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
        unset($rowNum, $var, $val);
    }

    /**
     * Loops through all data cells in the active worksheet and aborts script if any are invalid
     *
     * @return void
     * @throws Exception
     */
    public function validateData()
    {
        $this->shell_io->out('Validating data...');

        $ws = $this->getWorksheets()[$this->activeWorksheet];
        $dataRowCount = $ws['totalRows'] - ($ws['firstDataRow'] - 1);
        $dataColCount = $ws['totalCols'] - ($ws['firstDataCol'] - 1);

        /** @var ProgressHelper $progress */
        $progress = $this->shell_io->helper('Progress');
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
                unset($cell, $value);
            }
        }

        if ($invalidData) {
            $limit = 10;
            $count = count($invalidData);
            if ($count > $limit) {
                $invalidData = array_slice($invalidData, 0, $limit);
            }

            $this->shell_io->overwrite('Data errors:');
            array_unshift($invalidData, ['Col', 'Row', 'Invalid value']);
            $this->shell_io->helper('Table')->output($invalidData);
            if (count($invalidData) < $count) {
                $difference = $count - count($invalidData);
                $msg = '+ ' . $difference . ' more invalid ' . __n('value', 'values', $difference);
                $this->shell_io->out($msg);
            }
            $this->shell_io->error('Cannot continue. Invalid data found.');
            throw new Exception();
        }

        $this->shell_io->overwrite(' - Done');
        unset(
            $dataColCount,
            $dataRowCount,
            $datum,
            $invalidData,
            $progress,
            $ws
        );
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
        $this->shell_io->out('Identifying metrics...');
        $unknownMetrics = $this->getUnknownMetrics();
        if (!$unknownMetrics) {
            $this->shell_io->out(' - Done');
            unset($unknownMetrics);

            return;
        }

        $context = $this->getWorksheets()[$this->activeWorksheet]['context'];
        $count = count($unknownMetrics);
        $this->shell_io->out(sprintf(
            "%s new %s found",
            $count,
            __n('metric', 'metrics', $count)
        ));
        if (!$this->acceptMetricSuggestions) {
            $this->shell_io->out(
                "\n\n" .
                (($count == 1) ? "Options:\n" : "Options for each:\n") .
                " - Enter an existing $context metric ID\n" .
                " - Enter the name of a new metric to create \n" .
                " - Enter nothing to accept the suggested name \n" .
                " - Enter \"auto\" to accept all suggested names for this file\n\n" .
                'To nest a metric underneath one or more ancestors, separate each level with " > ", ' .
                'e.g. "Population > Nerds > Sci-fi nerds > Star Trek nerds"'
            );
        }

        $filename = $this->getFilename();
        $worksheetName = $this->activeWorksheet;
        foreach ($unknownMetrics as $colNum => $unknownMetric) {
            $cleanColName = str_replace("\n", ' ', $unknownMetric['name']);
            $this->shell_io->info("\nColumn: $cleanColName");
            $suggestedName = $this->getSuggestedMetricName($filename, $worksheetName, $unknownMetric);
            $this->shell_io->out('Suggested metric name: ' . $suggestedName);
            try {
                $metricId = $this->getMetricInput($suggestedName, $unknownMetric);
                $this->setMetricId($colNum, $metricId);
            } catch (Exception $e) {
                $this->shell_io->error('Error: ' . $e->getMessage());
            }
            unset($cleanColName, $metricId, $suggestedName);
        }
        unset(
            $context,
            $count,
            $filename,
            $unknownMetrics,
            $worksheetName
        );
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
         * @var SchoolDistrictCodesTable $districtCodesTable
         */
        $schoolDistrictsTable = TableRegistry::getTableLocator()->get('SchoolDistricts');
        $schoolsTable = TableRegistry::getTableLocator()->get('Schools');
        $districtCodesTable = TableRegistry::getTableLocator()->get('SchoolDistrictCodes');
        $schoolCodesTable = TableRegistry::getTableLocator()->get('SchoolCodes');
        $context = $this->getWorksheets()[$this->activeWorksheet]['context'];

        // Note that both districts and schools will be identified if present, regardless of the current context
        $this->shell_io->out(sprintf(
            'Identifying %s...',
            ($context == 'district') ? 'districts' : 'schools'
        ));

        /** @var ProgressHelper $progress */
        $progress = $this->shell_io->helper('Progress');
        $locations = $this->getActiveWorksheetProperty('locations');
        $count = count($locations);

        if ($count == 0) {
            $this->shell_io->out(' - None found');

            return;
        }

        $progress->init([
            'total' => $count,
            'width' => 40,
        ]);
        $progress->draw();

        $log = [
            'district' => [
                'identifiedList' => [],
                'addedList' => []
            ],
            'school' => [
                'identifiedList' => [],
                'addedList' => []
            ]
        ];
        foreach ($locations as $rowNum => $location) {
            // Identify district
            $districtId = null;
            if (isset($location['districtCode']) && isset($location['districtName'])) {
                $district = $schoolDistrictsTable
                    ->find('byCode', ['code' => $location['districtCode']])
                    ->select(['id', 'name'])
                    ->first();
                if ($district) {
                    $log['district']['identifiedList'][$district->id] = true;
                } else {
                    $district = $schoolDistrictsTable->newEntity(['name' => $location['districtName']]);
                    $district->school_district_codes = [
                        $districtCodesTable->newEntity([
                            'code' => $location['districtCode'],
                            'year' => $this->year
                        ])
                    ];
                    $schoolDistrictsTable->saveOrFail($district);
                    $log['district']['addedList'][] = "#{$location['districtCode']}: $district->name";
                }
                $districtId = $district->id;
                $this->setLocationInfo($rowNum, 'districtId', $district->id);
            } elseif (isset($location['districtCode'])) {
                throw new Exception('District name missing in row ' . $rowNum);
            }

            // Identify school
            $schoolId = null;
            if (isset($location['schoolCode']) && isset($location['schoolName'])) {
                /** @var School $school */
                $school = $schoolsTable
                    ->find('byCode', ['code' => $location['schoolCode']])
                    ->select(['id', 'name', 'school_district_id'])
                    ->first();
                if ($school) {
                    $log['school']['identifiedList'][$school->id] = true;

                    // Add missing school district ID
                    if (!$school->school_district_id) {
                        $school = $schoolsTable->patchEntity($school, ['school_district_id' => $districtId]);
                        $school = $schoolsTable->saveOrFail($school);
                    }
                } else {
                    $this->checkNewSchoolName($location['schoolName']);
                    $school = $schoolsTable->newEntity([
                        'name' => $location['schoolName'],
                        'school_district_id' => $districtId
                    ]);
                    $school->school_codes = [
                        $schoolCodesTable->newEntity([
                            'code' => $location['schoolCode'],
                            'year' => $this->year
                        ])
                    ];
                    $schoolsTable->saveOrFail($school);
                    $log['school']['addedList'][] = "#{$location['schoolCode']}: $school->name";
                }
                $this->setLocationInfo($rowNum, 'schoolId', $school->id);
            } elseif (isset($location['schoolCode']) || isset($location['schoolName'])) {
                throw new Exception('Incomplete school information in row ' . $rowNum);
            }

            $progress->increment(1);
            $progress->draw();
            unset($districtId, $district, $schoolId, $school);
        }
        unset($progress, $schoolDistrictsTable, $schoolsTable);

        // Report on what's been identified
        $firstLine = true;
        foreach ($log as $context => $contextLog) {
            if ($contextLog['identifiedList']) {
                $count = count($contextLog['identifiedList']);
                $msg = sprintf(
                    ' - %s %s identified',
                    $count,
                    __n($context, "{$context}s", $count)
                );
                if ($firstLine) {
                    $this->shell_io->overwrite($msg);
                    $firstLine = false;
                } else {
                    $this->shell_io->out($msg);
                }
                unset($count, $msg);
            }
            foreach ($contextLog['addedList'] as $addedRecord) {
                $msg = sprintf(
                    ' - Added %s %s',
                    $context,
                    $addedRecord
                );
                if ($firstLine) {
                    $this->shell_io->overwrite($msg);
                    $firstLine = false;
                } else {
                    $this->shell_io->out($msg);
                }
                unset($msg);
            }
        }
        unset($context, $firstLine, $log);
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
        $context = $this->getContext();

        if ($this->autoNameMetrics) {
            return $this->addMetricChain($context, $unknownMetric, $suggestedName);
        }

        $input = $this->acceptMetricSuggestions
            ? 'auto'
            : $this->shell_io->ask('Metric ID or name:');

        // Existing metric ID entered
        if (is_numeric($input)) {
            $metricId = (int)$input;
            if (!$this->metricsTable->exists(['id' => $metricId])) {
                $this->shell_io->error(ucwords($context) . ' metric ID ' . $metricId . ' not found');
                unset($context, $input, $metricId);

                return $this->getMetricInput($suggestedName, $unknownMetric);
            }
            unset($context, $input);

            return $metricId;
        }

        // Name of new metric entered
        try {
            if ($input == 'auto') {
                $metricName = $suggestedName;
                $this->autoNameMetrics = true;
            } else {
                $metricName = $input ?: $suggestedName;
            }
            unset($input);

            return $this->addMetricChain($context, $unknownMetric, $metricName);
        } catch (Exception $e) {
            $this->shell_io->error('Error: ' . $e->getMessage());
            unset($context, $input);

            return $this->getMetricInput($suggestedName, $unknownMetric);
        }
    }

    /**
     * Adds a new metric to the database
     *
     * @param string $context Either school or district
     * @param array $unknownMetric Array of name and group information for the current column
     * @param string $metricName Name of new metric
     * @return int
     * @throws Exception
     * @throws PhpOfficeException
     */
    private function addMetricChain($context, $unknownMetric, $metricName)
    {
        $metricParents = explode(' > ', $metricName);
        $finalMetric = array_pop($metricParents);
        $metric = null;
        $parentId = null;

        // Add ancestor metrics (e.g. grandparent > parent > final metric
        foreach ($metricParents as $namePart) {
            $metric = $this->getOrAddMetricParent($context, $namePart, $parentId);
            $parentId = $metric->id;
        }

        // Add final metric
        $finalMetric = $this->addMetric($context, $finalMetric, $parentId);

        // Store column info => metric ID information in DB to save time later
        $this->spreadsheetColsMetricsTable->add($this, $unknownMetric, $finalMetric->id);

        // Free up memory
        unset(
            $metric,
            $metricParents,
            $namePart,
            $parentId
        );

        return $finalMetric->id;
    }

    /**
     * Adds a new metric to the database
     *
     * @param string $context Either school or district
     * @param string $metricName Name of new metric
     * @param int|null $parentId ID of parent metric, or null for root
     * @throws Exception
     * @return Metric
     */
    private function getOrAddMetricParent($context, $metricName, $parentId)
    {
        // Get existing
        $conditions = [
            'context' => $context,
            'name' => $metricName
        ];
        if ($parentId) {
            $conditions['parent_id'] = $parentId;
        } else {
            $conditions[] = function (QueryExpression $exp) {
                return $exp->isNull('parent_id');
            };
        }
        /** @var Metric $existingMetric */
        $existingMetric = $this->metricsTable->find()
            ->where($conditions)
            ->first();
        if ($existingMetric) {
            unset(
                $conditions,
                $context,
                $metricName,
                $parentId
            );

            return $existingMetric;
        }

        unset($conditions);

        // Create new
        return $this->addMetric($context, $metricName, $parentId, false);
    }

    /**
     * Adds a new metric to the database
     *
     * @param string $context Either school or district
     * @param string $metricName Name of new metric
     * @param int|null $parentId ID of parent metric, or null for root
     * @param bool $selectable Whether or not this metric should be selectable when creating a ranking formula
     * @throws Exception
     * @return Metric
     */
    private function addMetric($context, $metricName, $parentId, $selectable = true)
    {
        $this->metricsTable->setScope($context);
        $metric = $this->metricsTable->newEntity([
            'context' => $context,
            'name' => $metricName,
            'description' => '',
            'type' => 'numeric',
            'parent_id' => $parentId,
            'selectable' => $selectable,
            'visible' => true,
            'is_percent' => null
        ]);
        if (!$this->metricsTable->save($metric)) {
            $msg = 'Cannot add metric ' . $metricName . "\nDetails: " . print_r($metric->getErrors(), true);
            throw new Exception($msg);
        }

        $this->shell_io->out('Metric #' . $metric->id . ' added');

        unset(
            $context,
            $metricName,
            $parentId,
            $selectable
        );

        return $metric;
    }

    /**
     * Adjusts totalRows to not include rows at the end of the file with blank values in their first column
     *
     * @throws PhpOfficeException
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
        unset($col, $row);
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

        // Blank out progress bar
        $this->shell_io->overwrite('');

        $input = $this->shell_io->askChoice("\nOverwrite statistics that have already been recorded?", ['y', 'n']);
        $this->overwrite = ($input == 'y');

        return $this->overwrite;
    }

    /**
     * Sets the value of $this->overwrite
     *
     * @param bool $overwrite True or false, indicating if existing statistics should be overwritten
     * @return void
     */
    public function setOverwrite($overwrite)
    {
        if (is_bool($overwrite)) {
            $this->overwrite = $overwrite;

            return;
        }

        $type = gettype($overwrite);
        throw new InternalErrorException("Value for overwrite property must be boolean ($type provided)");
    }

    /**
     * @throws PhpOfficeException
     * @return void
     * @throws Exception
     */
    public function recordData()
    {
        $this->shell_io->out('Importing data...');

        $ws = $this->getWorksheets()[$this->activeWorksheet];
        $dataRowCount = $ws['totalRows'] - ($ws['firstDataRow'] - 1);
        $dataColCount = $ws['totalCols'] - ($ws['firstDataCol'] - 1);

        /** @var ProgressHelper $progress */
        $progress = $this->shell_io->helper('Progress');
        $progress->init([
            'total' => $dataRowCount * $dataColCount,
            'width' => 40,
        ]);
        $progress->draw();
        unset($dataColCount, $dataRowCount);

        $datum = new Datum();
        $context = $this->getContext();
        $year = $this->year;
        $this->counts = [
            'added' => 0,
            'updated' => 0,
            'ignored' => 0,
            'deleted' => 0
        ];
        $statsToDelete = [];
        for ($row = $ws['firstDataRow']; $row <= $ws['totalRows']; $row++) {
            // Skip rows with no location
            if (!isset($this->worksheets[$this->activeWorksheet]['locations'][$row])) {
                break;
            }

            $location = $this->worksheets[$this->activeWorksheet]['locations'][$row];
            $locationIdKey = ($context == 'school') ? 'schoolId' : 'districtId';

            /* Skip rows with data but no location ID (like the non-district called "Independent Non-Public Schools"
             * that sometimes gets included in lists of districts) */
            if (!isset($location[$locationIdKey])) {
                break;
            }

            $locationId = $location[$locationIdKey];
            for ($col = $ws['firstDataCol']; $col <= $ws['totalCols']; $col++) {
                $progress->increment(1);
                $progress->draw();

                $value = $this->getProcessedValue($col, $row);
                $metricId = $this->worksheets[$this->activeWorksheet]['dataColumns'][$col]['metricId'];
                $existingStat = $this->statisticsTable->getStatistic($context, $metricId, $locationId, $year);

                // Value is ignorable and won't be imported
                if ($datum->isIgnorable($value)) {
                    // This value was recorded to the statistics table and shouldn't have been
                    if ($existingStat) {
                        $statsToDelete[] = [
                            'record' => $existingStat,
                            'cellContents' => $value === null ? 'null' : "\"$value\""
                        ];
                    // Ignore this value
                    } else {
                        $this->counts['ignored']++;
                    }

                    continue;
                }

                // Add
                if (!$existingStat) {
                    $locationIdField = ($context == 'school') ? 'school_id' : 'school_district_id';
                    $statistic = $this->statisticsTable->newEntity([
                        'metric_id' => $metricId,
                        $locationIdField => $locationId,
                        'value' => $value,
                        'year' => $this->year,
                        'file' => $this->filename,
                        'contiguous' => true
                    ]);
                    unset($locationIdField, $metricId);
                    if ($statistic->getErrors()) {
                        $errors = print_r($statistic->getErrors(), true);
                        $this->shell_io->error("Error adding statistic. Details: \n" . $errors);
                        throw new Exception();
                    } else {
                        $this->statisticsTable->save($statistic);
                    }
                    $this->counts['added']++;
                    unset($existingStat, $statistic, $value);
                    continue;
                }
                unset($metricId, $statistic);

                // Ignore, same value
                if ((string)$existingStat->value == (string)$value) {
                    $this->counts['ignored']++;
                    unset($existingStat, $value);
                    continue;
                }

                // Update
                if ($this->getOverwrite()) {
                    $this->statisticsTable->patchEntity($existingStat, ['value' => $value]);
                    if ($existingStat->getErrors() || !$this->statisticsTable->save($existingStat)) {
                        $errors = print_r($existingStat->getErrors(), true);
                        $this->shell_io->error("Error details: \n" . $errors);
                        throw new Exception('Failed to update statistic');
                    }
                    $this->counts['updated']++;
                }

                unset($existingStat, $value);
            }
        }

        $this->shell_io->overwrite(' - Done');

        if ($statsToDelete) {
            $this->processDeletableRecords($statsToDelete);
        }

        foreach ($this->counts as $action => $count) {
            if (!$count) {
                continue;
            }
            $msg = " - $count " . __n('stat ', 'stats ', $count) . $action;
            $this->shell_io->out($msg);
        }

        unset(
            $context,
            $this->counts,
            $datum,
            $msg,
            $progress,
            $ws,
            $year
        );
    }

    /**
     * Returns a stat value, handling formula evaluation, percent formatting, trimming, and rounding
     *
     * @param int $col Column number
     * @param int $row Column number
     * @throws PhpOfficeException
     * @return mixed
     */
    private function getProcessedValue($col, $row)
    {
        $cell = $this->getCell($col, $row);
        if ($cell->isFormula()) {
            $value = $cell->getCalculatedValue();
        } else {
            $value = $cell->getValue();
            $value = is_string($value) ? trim($value) : $value;
        }
        $value = Statistic::roundValue($value);
        $metricId = $this->worksheets[$this->activeWorksheet]['dataColumns'][$col]['metricId'];
        if (is_numeric($value) && $this->isPercentMetric($metricId)) {
            $value = Statistic::convertValueToPercent($value);
        }
        unset($cell, $metricId);

        return $value;
    }

    /**
     * Returns a string description of a ZipArchive error
     *
     * @param int $code ZipArchive error code
     * @return string
     */
    private function getZipArchiveErrorMsg($code)
    {
        switch ($code) {
            case ZipArchive::ER_INCONS:
                return 'Zip archive inconsistent';
        }

        return 'ZipArchive error code: ' . $code;
    }

    /**
     * Returns a suggested metric name, given information about an import spreadsheet column
     *
     * @param string $filename Import file name
     * @param string $worksheetName Name of active worksheet in import file
     * @param array $unknownMetric Array of information about the name and group of a column
     * @return string
     */
    private function getSuggestedMetricName($filename, $worksheetName, $unknownMetric)
    {
        // Start with filename
        $suggestedNameParts = [explode('.', $filename)[0]];

        // Add worksheet name (unless if it's a year)
        if (!Utility::isYear($worksheetName)) {
            $suggestedNameParts[] = trim($worksheetName);
        }

        // Clean up and add the column name
        $columnName = $unknownMetric['name'];
        $cleanColumnName = $columnName;
        while (strpos($cleanColumnName, '  ') !== false) {
            $cleanColumnName = str_replace("\n", ' ', $cleanColumnName);
            $cleanColumnName = str_replace('  ', ' ', $cleanColumnName);
        }
        $suggestedNameParts[] = trim($cleanColumnName);

        // Add group, if applicable
        if ($unknownMetric['group']) {
            $suggestedNameParts[] = $unknownMetric['group'];
        }

        // Remove blank and repeated parts
        foreach ($suggestedNameParts as $i => $namePart) {
            if ($namePart == '') {
                unset($suggestedNameParts[$i]);
                continue;
            }
            if ($i > 0 && $suggestedNameParts[$i - 1] == $namePart) {
                unset($suggestedNameParts[$i]);
                continue;
            }
        }

        unset(
            $cleanColumnName,
            $columnName,
            $filename,
            $i,
            $namePart,
            $unknownMetric,
            $worksheetName
        );
        $nameDelimiter = ' > ';

        return implode($nameDelimiter, $suggestedNameParts);
    }

    /**
     * Adds one or more worksheets to the ignore list
     *
     * @param string|string[] $worksheets One or multiple worksheet names
     * @return void
     */
    public function ignoreWorksheets($worksheets)
    {
        if (is_string($worksheets)) {
            $worksheets = [$worksheets];
        }

        $this->ignoredWorksheets = array_merge($this->ignoredWorksheets, $worksheets);
    }

    /**
     * Returns an array of all column names (ignoring groupings) for the selected worksheet
     *
     * @return array
     * @throws PhpOfficeException
     * @throws Exception
     */
    public function getColumnNames()
    {
        $row = $this->hasGroupingRow() ? 2 : 1;
        $columnNames = [];
        $lastDataCol = $this->getActiveWorksheetProperty('totalCols');
        for ($col = 1; $col <= $lastDataCol; $col++) {
            $columnNames[$col] = $this->getValue($col, $row);
        }
        unset($col, $lastDataCol, $row);

        return $columnNames;
    }

    /**
     * Returns TRUE if the metric with the specified ID or name should have its statistics formatted as percents
     *
     * Caches results locally in the isPercentMetric property
     *
     * @param int $metricId ID of metric record
     * @return bool
     */
    private function isPercentMetric($metricId)
    {
        if (!isset($this->isPercentMetric[$metricId])) {
            $this->isPercentMetric[$metricId] = $this->metricsTable->isPercentMetric($metricId);
        }

        return $this->isPercentMetric[$metricId];
    }

    /**
     * Returns TRUE if the active worksheet has a "groupings" row, which groups together sets of columns
     *
     * @return bool
     */
    private function hasGroupingRow()
    {
        return !empty($this->getActiveWorksheetProperty('groupings'));
    }

    /**
     * Checks the name of a school to be added and displays a confirmation dialogue if it looks like a district name
     *
     * Implemented after it was discovered that the Indiana Department of Education occasionally mislabels districts as
     * schools and vice-versa.
     *
     * @param string $schoolName School name
     * @return void
     */
    private function checkNewSchoolName($schoolName)
    {
        $districtKeywords = [
            ' Schools',
            ' Schs',
            ' Corp'
        ];
        foreach ($districtKeywords as $districtKeyword) {
            if (stripos($schoolName, $districtKeyword) === false) {
                continue;
            }

            $this->shell_io->warning(sprintf(
                'The school "%s" has a name that looks like a district',
                $schoolName
            ));
            $choice = $this->shell_io->askChoice('Add it to the database as a school anyway?', ['y', 'n'], 'n');
            if ($choice == 'n') {
                exit;
            }

            return;
        }
    }

    /**
     * Displays a message about records that appear to need to be deleted and returns the user's response
     *
     * @param int $count Count of records that would be deleted
     * @return string
     */
    private function getDeleteAllResponse(int $count)
    {
        $this->shell_io->warning(sprintf(
            '%s found in the database that should not have been imported, ' .
            'since %s to a blank cell or a cell with an ignorable value like "N/A". Delete %s?',
            __n('One record was', "$count records were", $count),
            __n('it corresponds', 'each correspond', $count),
            __n('this record', 'these records', $count)
        ));
        $this->shell_io->out(' - [n] Don\'t delete any');
        $this->shell_io->out(' - [a] Yes, delete all');
        $this->shell_io->out(' - [e] Ask me for each record');
        $this->shell_io->out(' - [s] Show all details');

        return $this->shell_io->askChoice(
            'Selection:',
            ['n', 'a', 'e', 's'],
            's'
        );
    }

    /**
     * Informs the user about records that can be deleted and either deletes or ignores them, according to user input
     *
     * @param array $statsToDelete Each member has a Statistics record and a string containing the actual (non)value
     * @return void
     */
    private function processDeletableRecords(array $statsToDelete)
    {
        $count = count($statsToDelete);
        $response = $this->getDeleteAllResponse($count);

        if ($response == 'n') {
            $this->counts['ignored'] += $count;

            return;
        }

        if ($response == 'a' || $response == 'e') {
            $deleteAll = $response == 'a';
            $this->deleteRecords($statsToDelete, $deleteAll);

            return;
        }

        // Show user a table of information about why each record should be deleted and reiterate original prompt
        if ($response == 's') {
            $this->shell_io->out();
            $table = [['Statistic ID', 'Saved value', 'Value in spreadsheet']];
            foreach ($statsToDelete as $stat) {
                $record = $stat['record'];
                $cellContents = $stat['cellContents'];
                $table[] = [$record->id, $record->value, $cellContents];
            }
            $this->shell_io->helper('Table')->output($table);

            $this->processDeletableRecords($statsToDelete);
        }
    }

    /**
     * Either deletes all provided records or prompts the user to review each record and delete or ignore
     *
     * @param array $statsToDelete Each member has a Statistics record and a string containing the actual (non)value
     * @param bool $deleteAll TRUE if all records should be deleted, FALSE if each record should be confirmed by user
     * @return void
     */
    private function deleteRecords(array $statsToDelete, bool $deleteAll)
    {
        $ignoreAll = false;
        foreach ($statsToDelete as $stat) {
            if ($ignoreAll) {
                $this->counts['ignored']++;
                continue;
            }

            $record = $stat['record'];
            if ($deleteAll) {
                $this->statisticsTable->delete($record);
                $this->counts['deleted']++;
                continue;
            }

            $response = $this->getIndividualDeleteResponse($record, $stat['cellContents']);
            // Delete this and all subsequent records
            if ($response == 'ya') {
                $deleteAll = true;
                $this->statisticsTable->delete($record);
                $this->counts['deleted']++;

            // Ignore this and all subsequent records
            } elseif ($response == 'na') {
                $this->shell_io->out();
                $ignoreAll = true;
                $this->counts['ignored']++;

            // Delete this record
            } elseif ($response == 'y') {
                $this->statisticsTable->delete($record);
                $this->counts['deleted']++;

            // Ignore this record
            } elseif ($response == 'n') {
                $this->counts['ignored']++;
            }
        }
    }

    /**
     * Returns the user's response to a prompt asking whether or not to delete a particular record
     *
     * @param Statistic $record Statistic record
     * @param string $cellContents The actual contents of the spreadsheet cell associated with this record
     * @return string
     */
    private function getIndividualDeleteResponse($record, $cellContents)
    {
        $this->shell_io->out();
        $this->shell_io->out(sprintf(
            'Statistic #%s was saved with value %s, but its spreadsheet cell contains %s. Delete?',
            $record->id,
            $record->value,
            $cellContents
        ));
        $this->shell_io->out(' - [y] yes');
        $this->shell_io->out(' - [n] no');
        $this->shell_io->out(' - [ya] yes and delete the rest');
        $this->shell_io->out(' - [na] no and skip the rest');

        return $this->shell_io->askChoice(
            'Selection:',
            ['y', 'n', 'ya', 'na'],
            'n'
        );
    }
}
