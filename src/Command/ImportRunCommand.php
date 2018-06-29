<?php
namespace App\Command;

use App\Import\ImportFile;
use App\Model\Table\ImportedFilesTable;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Filesystem\Folder;
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\TableRegistry;
use Exception;
use InvalidArgumentException;

/**
 * Class ImportRunCommand
 * @package App\Command
 * @property ImportFile $importFile
 */
class ImportRunCommand extends Command
{
    private $importFile;

    /**
     * Initializes the command
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * Display help for this console.
     *
     * @param ConsoleOptionParser $parser Console options parser object
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser)
    {
        $parser->addArguments([
            'year' => ['help' => 'The specific year to look up or "all"'],
            'fileKey' => ['help' => 'The numeric key for the specific file to process, or "all"']
        ])->addOption(
            'auto-metrics',
            [
                'help' => 'Automatically accept all suggested metric names',
                'boolean' => true
            ]
        )->setEpilog(
            'Run "import-run all all --auto-metrics" to process all files and accept all suggested metric names'
        );

        return $parser;
    }

    /**
     * Asks the user for a year (or "all") and returns the response
     *
     * @param ConsoleIo $io Console IO object
     * @return string
     */
    private function selectYear($io)
    {
        $availableYears = $this->getYears();
        $years = null;
        while (!in_array($years, $availableYears) && $years != 'all') {
            $years = $io->ask(sprintf(
                'Select a year (%s-%s) or enter "all":',
                min($availableYears),
                max($availableYears)
            ));
        }

        return $years;
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
        $files = self::getFiles();
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
        $years = $args->hasArgument('year') ? $args->getArgument('year') : null;
        $fileKey = $args->hasArgument('fileKey') ? $args->getArgument('fileKey') : null;

        // Gather parameters
        if (!$years) {
            $years = $this->selectYear($io);
        }
        $years = ($years == 'all') ? $this->getYears() : [(int)$years];

        foreach ($years as $year) {
            if (count($years) > 1) {
                $io->info("----------\n|  $year  |\n----------");
                $io->out();
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
            $files = self::getFiles();
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
            $dir = ROOT . DS . 'data' . DS . $year . DS;
            foreach ($selectedFiles as $file) {
                $io->out('Opening ' . $file['filename'] . '...');
                $this->importFile = new ImportFile($year, $dir, $file['filename'], $io);
                $this->importFile->read();
                $this->importFile->acceptMetricSuggestions = $args->getOption('auto-metrics');
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
                        $io->nl();
                        $io->error($e->getMessage());

                        return;
                    } catch (Exception $e) {
                        $io->nl();
                        $io->error($e->getMessage());

                        return;
                    }
                    $io->out();
                }

                $this->markFileProcessed($year, $file['filename'], $io);

                // Free up memory
                $this->importFile->spreadsheet->disconnectWorksheets();
                unset($this->importFile);
            }
        }

        $io->out('Import complete');
    }

    /**
     * Records an entry in the imported_files table
     *
     * @param int|string $year The year represented by this file
     * @param string $filename Name of file that's just been processed
     * @param ConsoleIo $io Console IO object
     * @return void
     */
    public static function markFileProcessed($year, $filename, $io)
    {
        $importedFiles = TableRegistry::getTableLocator()->get('ImportedFiles');

        $record = $importedFiles->newEntity([
            'year' => (int)$year,
            'file' => $filename
        ]);
        if ($importedFiles->save($record)) {
            return;
        }

        $io->error('Error saving imported file record: ');
        $msg = $record->getErrors()
            ? "\nDetails:\n" . print_r($record->getErrors(), true)
            : ' No details available. (Check for application rule violation)';
        $io->error($msg);
    }

    /**
     * Returns an array of years corresponding to subdirectories of /data
     *
     * @return array
     */
    private function getYears()
    {
        $dataPath = ROOT . DS . 'data';
        $dir = new Folder($dataPath);
        $years = $dir->subdirectories($dir->path, false);
        sort($years);

        return $years;
    }

    /**
     * Returns an array of sets of files, grouped by year, including both filename and date of last import
     *
     * @return array
     */
    public static function getFiles()
    {
        /** @var ImportedFilesTable $importedFilesTable */
        $importedFilesTable = TableRegistry::getTableLocator()->get('ImportedFiles');
        $dataPath = ROOT . DS . 'data';
        $dir = new Folder($dataPath);
        $subdirs = $dir->subdirectories($dir->path, false);
        $retval = [];

        foreach ($subdirs as $year) {
            if (!self::isYear($year)) {
                throw new InternalErrorException('Directory ' . $dataPath . DS . $year . ' is not a year.');
            }
            $subdir = new Folder($dataPath . DS . $year);
            $files = $subdir->find('.*.xlsx');
            if (!$files) {
                continue;
            }
            foreach ($files as $file) {
                $retval[$year][] = [
                    'filename' => $file,
                    'imported' => $importedFilesTable->getImportDate($year, $file)
                ];
            }
        }

        return $retval;
    }

    /**
     * Returns true or false, indicating if $string appears to be a year
     *
     * @param string $string String to be tested
     * @return bool
     */
    public static function isYear($string)
    {
        return strlen($string) == 4 && is_numeric($string);
    }
}
