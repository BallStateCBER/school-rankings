<?php
namespace App\Command;

use App\Import\Import;
use App\Import\ImportFile;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Exception;
use InvalidArgumentException;

class ImportRunCommand extends Command
{
    private $import;
    private $importFile;

    /**
     * Initializes the command
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
     * @param ConsoleOptionParser $parser Console options parser object
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser)
    {
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
     * Collects a year/subdirectory
     *
     * @param ConsoleIo $io Console IO object
     * @return int
     */
    private function selectYear($io)
    {
        $availableYears = $this->import->getYears();
        $year = null;
        while (!in_array($year, $availableYears)) {
            $year = $io->ask('Select a year (' . min($availableYears) . '-' . max($availableYears) . '):');
        }

        return (int)$year;
    }

    /**
     * Collects a file key for the specified key from the user
     *
     * @param int $year The year/subdirectory to select a file from
     * @param ConsoleIo $io Console IO object
     * @throws InvalidArgumentException
     * @return int
     */
    private function selectFileKey($year, $io)
    {
        $files = $this->import->getFiles();
        if (!isset($files[$year])) {
            throw new InvalidArgumentException('No import files found in data' . DS . $year);
        }

        $fileKeys = range(1, count($files[$year]));
        $fileKey = null;
        do {
            $io->out('Import files for year ' . $year . ':');
            $tableData = $files[$year];
            foreach ($tableData as $key => &$file) {
                array_unshift($file, $key + 1);
            }
            array_unshift($tableData, ['Key', 'File', 'Imported']);
            $io->helper('Table')->output($tableData);
            $fileKey = $io->ask('Select a file (' . min($fileKeys) . '-' . max($fileKeys) . ') or enter "all":');
            $validKey = $fileKey == 'all' || (is_numeric($fileKey) && in_array($fileKey, $fileKeys));
        } while (!$validKey);

        return (int)$fileKey;
    }

    /**
     * Processes import files and updates the database
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return int|null|void
     * @throws \Exception
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $year = $args->hasArgument('year') ? $args->getArgument('year') : null;
        $fileKey = $args->hasArgument('fileKey') ? $args->getArgument('fileKey') : null;

        // Gather parameters
        if (!$year) {
            $year = $this->selectYear($io);
        }
        if (!$fileKey) {
            try {
                $fileKey = $this->selectFileKey($year, $io);
            } catch (InvalidArgumentException $e) {
                $io->error($e->getMessage());

                return;
            }
        }

        // Validate parameters
        $files = $this->import->getFiles();
        if (!isset($files[$year])) {
            $io->error('No import files found in data' . DS . $year);

            return;
        }
        if ($fileKey != 'all') {
            if (!is_numeric($fileKey) || !isset($files[$year][$fileKey - 1])) {
                $io->error('Invalid file key');

                return;
            }
        }

        // Loop through files
        $selectedFiles = $fileKey == 'all' ?
            $files[$year] :
            [$files[$year][$fileKey - 1]];
        foreach ($selectedFiles as $file) {
            $io->out('Opening ' . $file['filename'] . '...');
            $this->importFile = new ImportFile($year, $file['filename'], $io);
            if ($this->importFile->getError()) {
                $io->error($this->importFile->getError());

                return;
            }

            // Read in worksheet info and validate data
            $io->out('Analyzing worksheets...');
            $io->out();
            foreach ($this->importFile->getWorksheets() as $worksheetName => $worksheetInfo) {
                $io->info('Worksheet: ' . $worksheetName);
                $io->out('Context: ' . ucwords($worksheetInfo['context']));
                try {
                    $this->importFile->selectWorksheet($worksheetName);
                    $this->importFile->validateData();
                    $this->importFile->identifyMetrics();
                    $this->importFile->identifyLocations();
                    $this->importFile->recordData();
                } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                    $io->error($e->getMessage());

                    return;
                } catch (Exception $e) {
                    $io->error($e->getMessage());

                    return;
                }
                $io->out();
            }

            // Free up memory
            $this->importFile->spreadsheet->disconnectWorksheets();
            unset($this->importFile);
        }

        $io->out('Import complete');
    }
}
