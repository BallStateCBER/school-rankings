<?php
namespace App\Shell;

use App\Import\Datum;
use App\Import\Import;
use App\Import\ImportFile;
use Cake\Console\Shell;
use Cake\Shell\Helper\ProgressHelper;
use PhpOffice\PhpSpreadsheet\Exception;

/**
 * Class ImportShell
 * @package App\Shell
 * @property Import $import
 */
class ImportShell extends Shell
{
    private $import;

    /**
     * Initializes the Shell
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();
        $this->import = new Import();
    }

    /**
     * Display help for this console.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser()
    {
        $parser = parent::getOptionParser();
        $parser->addSubcommand('run', [
            'help' => 'Process file(s) and import data',
        ])->addSubcommand('status', [
            'help' => 'Show what files are available and which have been imported',
        ])->addArguments([
            'year' => ['help' => 'The specific year to look up'],
            'fileKey' => ['help' => 'The numeric key for the specific file to process']
        ]);

        return $parser;
    }

    /**
     * Shows what files are available in /data and which have been imported
     *
     * @param string|null $year Specific year to look up
     * @return void
     */
    public function status($year = null)
    {
        $files = $this->import->getFiles();

        if ($year) {
            if (isset($files[$year])) {
                $files = [$year => $files[$year]];
            } else {
                $this->out('No import files found in data' . DS . $year);
                return;
            }
        }

        foreach ($files as $year => $yearFiles) {
            $this->info($year);
            array_unshift($yearFiles, ['File', 'Imported']);
            $this->helper('Table')->output($yearFiles);
            $this->out('');
        }
    }

    /**
     * Collects a year/subdirectory
     *
     * @return int
     */
    private function selectYear()
    {
        $availableYears = $this->import->getYears();
        $year = null;
        while (!in_array($year, $availableYears)) {
            $year = $this->in('Select a year (' . min($availableYears) . '-' . max($availableYears) . '):');
        }

        return (int)$year;
    }

    /**
     * Collects a file key for the specified key from the user
     *
     * @param int $year The year/subdirectory to select a file from
     * @return int
     */
    private function selectFileKey($year)
    {
        $files = $this->import->getFiles();
        if (!isset($files[$year])) {
            $this->abort('No import files found in data' . DS . $year);
        }

        $fileKeys = range(1, count($files[$year]));
        $fileKey = null;
        while (!is_numeric($fileKey) || !in_array($fileKey - 1, $fileKeys)) {
            $this->out('Import files for year ' . $year . ':');
            $tableData = $files[$year];
            foreach ($tableData as $key => &$file) {
                array_unshift($file, $key + 1);
            }
            array_unshift($tableData, ['Key', 'File', 'Imported']);
            $this->helper('Table')->output($tableData);
            $fileKey = $this->in('Select a file (' . min($fileKeys) . '-' . max($fileKeys) . '):');
        }

        return (int)$fileKey;
    }

    /**
     * @param null|string $year Year/subdirectory to read from
     * @param null|string $fileKey Key of the file to read from
     * @return void
     */
    public function run($year = null, $fileKey = null)
    {
        // Gather parameters
        if (!$year) {
            $year = $this->selectYear();
        }
        if (!$fileKey) {
            $fileKey = $this->selectFileKey($year);
        }

        // Open file
        $files = $this->import->getFiles();
        $file = $files[$year][$fileKey - 1];
        $this->out('Opening ' . $file['filename'] . '...');
        $importFile = new ImportFile($year, $file['filename']);
        if ($importFile->getError()) {
            $this->abort($importFile->getError());
        }

        // Read in worksheet info and validate data
        $this->out('Analyzing worksheets...');
        foreach ($importFile->getWorksheets() as $worksheetName => $worksheetInfo) {
            $this->info("\n" . $worksheetName);
            $this->out('Context: ' . ucwords($worksheetInfo['context']));
            $this->validateData($importFile, $worksheetName);
        }
    }

    /**
     * Loops through all data cells and aborts script if any are invalid
     *
     * @param ImportFile $importFile Current spreadsheet file
     * @param string $worksheetName Name of current worksheet
     * @return void
     */
    private function validateData($importFile, $worksheetName)
    {
        $this->out('Validating data...');

        try {
            $importFile->selectWorksheet($worksheetName);
        } catch (Exception $e) {
            $this->abort('Error selecting worksheet ' . $worksheetName);
        }

        $ws = $importFile->getWorksheets()[$worksheetName];
        $dataRowCount = $ws['totalRows'] - ($ws['firstDataRow'] - 1);
        $dataColCount = $ws['totalCols'] - ($ws['firstDataCol'] - 1);

        /** @var ProgressHelper $progress */
        $progress = $this->helper('Progress');
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
                    $value = $importFile->getValue($col, $row);
                    if (!$datum->isValid($value)) {
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
            $this->_io->overwrite('Data errors:');
            array_unshift($invalidData, ['Col', 'Row', 'Invalid value']);
            $this->helper('Table')->output($invalidData);
            $this->abort('Cannot continue. Invalid data found.');

            return;
        }

        $this->_io->overwrite('All data valid');
    }
}
