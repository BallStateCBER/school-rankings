<?php
namespace App\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Class ImportFile
 * @package App\Import
 * @property Spreadsheet $spreadsheet
 * @property string|null $error
 * @property string[] $worksheets
 */
class ImportFile
{
    private $error;
    private $spreadsheet;
    private $worksheets;

    public function __construct($year, $filename)
    {
        try {
            $type = 'Xlsx';
            $path = ROOT . DS . 'data' . DS . $year . DS . $filename;
            /** @var Xlsx $reader */
            $reader = IOFactory::createReader($type);
            $reader->setReadDataOnly(true);
            $this->worksheets = $reader->listWorksheetNames($path);
            $this->spreadsheet = $reader->load($path);
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
     * @return string[]
     */
    public function getWorksheets()
    {
        return $this->worksheets;
    }
}
