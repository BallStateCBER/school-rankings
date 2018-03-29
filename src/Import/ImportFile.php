<?php
namespace App\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\BaseReader;
use PhpOffice\PhpSpreadsheet\Reader\Exception;

class ImportFile
{
    private $spreadsheet;
    private $error;

    public function __construct($year, $filename)
    {
        try {
            $type = 'Xlsx';
            $path = ROOT . DS . 'data' . DS . $year . DS . $filename;
            /** @var BaseReader $reader */
            $reader = IOFactory::createReader($type);
            $reader->setReadDataOnly(true);
            $this->spreadsheet = $reader->load($path);
        } catch (Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    public function getError()
    {
        return $this->error;
    }
}
