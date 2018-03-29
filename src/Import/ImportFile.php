<?php
namespace App\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Class ImportFile
 * @package App\Import
 * @property array $worksheets
 * @property Spreadsheet $spreadsheet
 * @property string|null $activeWorksheet
 * @property string|null $error
 */
class ImportFile
{
    public $activeWorksheet;
    private $error;
    private $worksheets;
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

        try {
            // Read spreadsheet
            /** @var Xlsx $reader */
            $reader = IOFactory::createReader($type);
            $reader->setReadDataOnly(true);
            $this->spreadsheet = $reader->load($path);

            // Analyze each worksheet
            foreach ($reader->listWorksheetInfo($path) as $worksheet) {
                $this->selectWorksheet($worksheet['worksheetName']);
                $this->worksheets[$worksheet['worksheetName']] = [
                    'context' => $this->getContext(),
                    'firstDataRow' => $this->getFirstDataRow(),
                    'firstDataCol' => $this->getFirstDataCol(),
                    'totalRows' => $worksheet['totalRows'],
                    'totalCols' => $worksheet['totalColumns']
                ];
            }
        } catch (\Exception $e) {
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
     * Sets the specified worksheet as the currently active worksheet
     *
     * @param string $worksheet Worksheet name
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @return void
     */
    public function selectWorksheet($worksheet)
    {
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
        return $this->spreadsheet->getActiveSheet()->getCellByColumnAndRow($col, $row)->getValue();
    }

    /**
     * Returns the index of the first row on which statistical data will be read
     *
     * @return int
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \Exception
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

        throw new \Exception('First data row could not be found');
    }

    /**
     * Returns the index of the first column on which statistical data will be read
     *
     * Assumed to be the first column after the school/district identifier columns
     *
     * @return int
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \Exception
     */
    private function getFirstDataCol()
    {
        for ($row = 1; $row <= 3; $row++) {
            $isLocationHeaderRow = false;
            for ($col = 1; $col <= 4; $col++) {
                $isLocationHeader = $this->isLocationHeader($col, $row);
                if ($isLocationHeader) {
                    $isLocationHeaderRow = true;
                } elseif ($isLocationHeaderRow) {
                    return $col;
                }
            }
        }

        throw new \Exception('First data row could not be found');
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
     * Returns the active worksheet's context (school or district)
     *
     * @return string
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \Exception
     */
    public function getContext()
    {
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

        throw new \Exception('Cannot determine school/district context of worksheet ' . $this->activeWorksheet);
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

        return in_array($value, ['schoolname', 'schlname']);
    }
}
