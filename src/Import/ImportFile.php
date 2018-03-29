<?php
namespace App\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;

class ImportFile
{
    private $spreadsheet;
    private $error;

    public function __construct($year, $filename)
    {
        try {
            $path = ROOT . DS . 'data' . DS . $year . DS . $filename;
            $this->spreadsheet = IOFactory::load($path);
        } catch (Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    public function getError()
    {
        return $this->error;
    }
}
