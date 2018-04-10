<?php
namespace App\Shell;

use App\Import\Import;
use App\Import\ImportFile;
use Cake\Console\Shell;
use Exception;

/**
 * Class ImportShell
 * @package App\Shell
 * @property Import $import
 * @property ImportFile $importFile
 */
class ImportShell extends Shell
{
    private $import;
    private $importFile;

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
            $this->out();
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
        do {
            $this->out('Import files for year ' . $year . ':');
            $tableData = $files[$year];
            foreach ($tableData as $key => &$file) {
                array_unshift($file, $key + 1);
            }
            array_unshift($tableData, ['Key', 'File', 'Imported']);
            $this->helper('Table')->output($tableData);
            $fileKey = $this->in('Select a file (' . min($fileKeys) . '-' . max($fileKeys) . ') or enter "all":');
            $validKey = $fileKey == 'all' || (is_numeric($fileKey) && in_array($fileKey, $fileKeys));
        } while (!$validKey);

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

        // Validate parameters
        $files = $this->import->getFiles();
        if (!isset($files[$year])) {
            $this->abort('No import files found in data' . DS . $year);
        }
        if ($fileKey != 'all') {
            if (!is_numeric($fileKey) || !isset($files[$year][$fileKey - 1])) {
                $this->abort('Invalid file key');
            }
        }

        // Loop through files
        $selectedFiles = $fileKey == 'all' ?
            $files[$year] :
            [$files[$year][$fileKey - 1]];
        foreach ($selectedFiles as $file) {
            $this->out('Opening ' . $file['filename'] . '...');
            $this->importFile = new ImportFile($year, $file['filename'], $this);
            if ($this->importFile->getError()) {
                $this->abort($this->importFile->getError());
            }

            // Read in worksheet info and validate data
            $this->out('Analyzing worksheets...');
            $this->out();
            foreach ($this->importFile->getWorksheets() as $worksheetName => $worksheetInfo) {
                $this->info('Worksheet: ' . $worksheetName);
                $this->out('Context: ' . ucwords($worksheetInfo['context']));
                try {
                    $this->importFile->selectWorksheet($worksheetName);
                    $this->importFile->validateData();
                    $this->importFile->identifyMetrics();
                    $this->importFile->identifyLocations();
                    $this->importFile->recordData();
                } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                    $this->abort($e->getMessage());
                } catch (Exception $e) {
                    $this->abort($e->getMessage());
                }
                $this->out();
            }

            // Free up memory
            $this->importFile->spreadsheet->disconnectWorksheets();
            unset($this->importFile);
        }

        $this->out('Import complete');
    }
}
