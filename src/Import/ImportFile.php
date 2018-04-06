<?php
namespace App\Import;

use App\Model\Entity\SchoolDistrict;
use App\Model\Table\MetricsTable;
use App\Model\Table\SpreadsheetColumnsMetricsTable;
use Cake\ORM\TableRegistry;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Class ImportFile
 * @package App\Import
 * @property array $worksheets
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
    private $worksheets;
    private $year;
    public $activeWorksheet;
    public $spreadsheet;

    /**
     * ImportFile constructor
     *
     * @param string $year Year (subdirectory of /data)
     * @param string $filename Filename of spreadsheet to import
     */
    public function __construct($year, $filename)
    {
        $type = 'Xlsx';
        $path = ROOT . DS . 'data' . DS . $year . DS . $filename;
        $this->year = $year;
        $this->filename = $filename;

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
        return $this->isDistrictIdHeader($col, $row)
            || $this->isDistrictNameHeader($col, $row)
            || $this->isSchoolIdHeader($col, $row)
            || $this->isSchoolNameHeader($col, $row);
    }

    /**
     * Returns a string indicating if the column is districtId, districtName, schoolId, or schoolName
     *
     * @param int $col Column number
     * @return string
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws Exception
     */
    private function getLocationColumnType($col)
    {
        $headerRow = $this->getActiveWorksheetProperty('firstDataRow') - 1;

        if ($this->isDistrictIdHeader($col, $headerRow)) {
            return 'districtId';
        }

        if ($this->isDistrictNameHeader($col, $headerRow)) {
            return 'districtName';
        }

        if ($this->isSchoolIdHeader($col, $headerRow)) {
            return 'schoolId';
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
                    $this->isSchoolIdHeader(1, $row)
                    && $this->isSchoolNameHeader(2, $row)
                ) || (
                    $this->isDistrictIdHeader(1, $row)
                    && $this->isDistrictNameHeader(2, $row)
                    && $this->isSchoolIdHeader(3, $row)
                    && $this->isSchoolNameHeader(4, $row)
                );
            if ($isSchoolContext) {
                return 'school';
            }

            $isDistrictContext = $this->isDistrictIdHeader(1, $row)
                && $this->isDistrictNameHeader(2, $row)
                && !$this->isSchoolIdHeader(3, $row)
                && !$this->isSchoolNameHeader(4, $row);
            if ($isDistrictContext) {
                return 'district';
            }
        }

        throw new Exception('Cannot determine school/district context of worksheet ' . $this->activeWorksheet);
    }

    /**
     * Returns true if the given cell contains a header for a district ID column
     *
     * Known values:
     * Corp / Corp ID / IDOE_CORPORATION_ID / CORP ID / Corporation Id / Corp. Id / Corp. ID
     *
     * @param int $col Column index (starting at one)
     * @param int $row Row index (starting at one)
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @return bool
     */
    private function isDistrictIdHeader($col, $row)
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
     * Returns true if the given cell contains a header for a school ID column
     *
     * Known values:
     * School / School ID / Sch ID / IDOE_SCHOOL_ID / SCH ID / Schl. Id
     *
     * @param int $col Column index (starting at one)
     * @param int $row Row index (starting at one)
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @return bool
     */
    private function isSchoolIdHeader($col, $row)
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
     * Returns an array of $row => $location, with $location keys districtId, districtName, schoolId, and schoolName
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
                if ($type == 'districtId' || $type == 'schoolId') {
                    $value = $this->removeLeadingZeros($value);
                }
                if ($type == 'districtId' && SchoolDistrict::isDummyCode($value)) {
                    continue;
                }

                $location[$type] = $value;
            }
            $locations[$row] = $location;
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
}
