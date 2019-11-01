<?php
namespace App\Command;

use App\Import\ImportFile;
use App\Model\Entity\School;
use App\Model\Entity\SchoolDistrict;
use App\Model\Table\SchoolDistrictsTable;
use App\Model\Table\SchoolsTable;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Filesystem\Folder;
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\TableRegistry;
use Cake\Shell\Helper\ProgressHelper;
use Exception;

/**
 * Class PopulateLocationOriginCommand
 * @package App\Command
 * @property array $codesAlreadyProcessed
 * @property array $files
 * @property ConsoleIo $io
 * @property ImportFile $importFile
 * @property SchoolDistrictsTable $schoolDistrictsTable
 * @property SchoolsTable $schoolsTable
 * @property string $currentFile
 */
class PopulateLocationOriginCommand extends Command
{
    private $processedCodes = [
        'district' => [],
        'school' => []
    ];
    private $currentFile;
    private $files;
    private $importFile;
    private $io;
    private $schoolDistrictsTable;
    private $schoolsTable;

    /**
     * Initializes the command
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();
        $this->schoolDistrictsTable = TableRegistry::getTableLocator()->get('SchoolDistricts');
        $this->schoolsTable = TableRegistry::getTableLocator()->get('Schools');
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
        $this->io = $io;
        $io->info(
            'This script will read through all stat import files and populate any missing origin_file fields ' .
            'in school and school_district records. Unknown schools and districts will be ignored.'
        );
        $continue = $io->askChoice('Continue?', ['y', 'n'], 'y') == 'y';
        if (!$continue) {
            return;
        }

        $this->processStatImportFiles();

        $this->processLocationImportFiles();

        $io->success('Finished');
    }

    /**
     * Returns an array of years corresponding to subdirectories of /data
     *
     * @return array
     */
    private function getYears()
    {
        $dataPath = ROOT . DS . 'data' . DS . 'statistics';
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
    public function getAllFiles()
    {
        if ($this->files) {
            return $this->files;
        }

        $dataPath = ROOT . DS . 'data' . DS . 'statistics';
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
                $retval[$year][] = ['filename' => $file];
            }
        }

        $this->files = $retval;

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

    /**
     * Reads the current worksheet's location info and populates the origin_file field for schools and districts
     *
     * @return void
     * @throws Exception
     */
    private function processLocations()
    {
        $locations = $this->importFile->getActiveWorksheetProperty('locations');

        $updatedDistrictCount = 0;
        $updatedSchoolCount = 0;

        /** @var ProgressHelper $progress */
        $progress = $this->io->helper('Progress');
        $progress->init([
            'total' => count($locations),
            'width' => 40,
        ]);
        $progress->draw();

        foreach ($locations as $rowNum => $location) {
            // Identify district
            if (isset($location['districtCode'])) {
                $code = $location['districtCode'];
                if (!in_array($code, $this->processedCodes['district'])) {
                    /** @var SchoolDistrict $district */
                    $district = $this->schoolDistrictsTable
                        ->find('byCode', ['code' => $code])
                        ->select(['id', 'name'])
                        ->where(['origin_file' => ''])
                        ->first();
                    if ($district) {
                        $this->setDistrictOriginFile($district);
                        $updatedDistrictCount++;
                    }
                    $this->processedCodes['district'][] = $code;
                }
            }

            // Identify school
            if (isset($location['schoolCode'])) {
                $code = $location['schoolCode'];
                if (!in_array($code, $this->processedCodes['school'])) {
                    /** @var School $school */
                    $school = $this->schoolsTable
                        ->find('byCode', ['code' => $code])
                        ->select(['id', 'name'])
                        ->where(['origin_file' => ''])
                        ->first();
                    if ($school) {
                        $this->setSchoolOriginFile($school);
                        $updatedSchoolCount++;
                    }
                    $this->processedCodes['school'][] = $code;
                }
            }

            unset($districtId, $district, $schoolId, $school);

            $progress->increment(1);
            $progress->draw();
        }

        $this->io->overwrite(sprintf(
            ' - Updated %s %s',
            $updatedDistrictCount,
            __n('district', 'districts', $updatedDistrictCount)
        ));
        $this->io->out(sprintf(
            ' - Updated %s %s',
            $updatedSchoolCount,
            __n('school', 'schools', $updatedSchoolCount)
        ));
    }

    /**
     * Updates the provided district's origin_file field
     *
     * @param SchoolDistrict $district School district entity
     * @return void
     * @throws Exception
     */
    private function setDistrictOriginFile($district)
    {
        $this->schoolDistrictsTable->patchEntity($district, ['origin_file' => $this->currentFile]);
        if (!$this->schoolDistrictsTable->save($district)) {
            throw new Exception("Error updating district: \n" . print_r($district->getErrors(), true));
        }
    }

    /**
     * Updates the provided school's origin_file field
     *
     * @param School $school School entity
     * @return void
     * @throws Exception
     */
    private function setSchoolOriginFile($school)
    {
        $this->schoolsTable->patchEntity($school, ['origin_file' => $this->currentFile]);
        if (!$this->schoolsTable->save($school)) {
            throw new Exception("Error updating school: \n" . print_r($school->getErrors(), true));
        }
    }

    /**
     * Processes all of the files under /data/locations
     *
     * @return void
     * @throws Exception
     */
    private function processLocationImportFiles()
    {
        $importLocations = new ImportLocationsCommand();
        $importLocations->io = $this->io;

        $dataPath = ImportLocationsCommand::getDirectory();
        $this->files = (new Folder($dataPath))->find();
        if (!$this->files) {
            $this->io->out('No files found in ' . $dataPath);

            return;
        }

        $this->io->out('Processing location import files...');
        $this->io->out();

        foreach ($this->files as $key => $file) {
            $this->currentFile = $file;
            $year = $importLocations->getYearFromFilename($file);
            if (!$year) {
                return;
            }

            $this->io->out("Opening $file...");
            $this->importFile = new ImportFile($year, $dataPath, $file, $this->io);
            $this->importFile->ignoreWorksheets(['closed']);
            if ($this->importFile->getError()) {
                $this->io->error($this->importFile->getError());

                return;
            }

            // Read in worksheet info and validate data
            $this->io->out('Analyzing worksheets...');
            $this->io->out();
            $this->importFile->read();
            foreach ($this->importFile->getWorksheets() as $worksheetName => $worksheetInfo) {
                $context = $worksheetInfo['context'];
                $this->io->info('Worksheet: ' . $worksheetName);
                $this->io->out('Context: ' . ucwords($context));

                try {
                    $this->importFile->selectWorksheet($worksheetName);
                    $this->processLocations();
                } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                    $this->io->out();
                    $this->io->error($e->getMessage());

                    return;
                } catch (Exception $e) {
                    $this->io->out();
                    $this->io->error($e->getMessage());

                    return;
                }
            }

            $this->io->out();

            // Free up memory
            $this->importFile->spreadsheet->disconnectWorksheets();
            unset($this->importFile);
        }
    }

    /**
     * Processes all of the files under /data/statistics/$year
     *
     * @return void
     */
    private function processStatImportFiles()
    {
        $years = $this->getYears();

        foreach ($years as $year) {
            if (count($years) > 1) {
                $this->io->info("----------\n|  $year  |\n----------");
                $this->io->out();
            }

            $files = $this->getAllFiles();

            // Loop through the files in the selected year
            $dir = ROOT . DS . 'data' . DS . 'statistics' . DS . $year . DS;
            foreach ($files[$year] as $file) {
                $this->currentFile = $file['filename'];
                $this->io->out('Opening ' . $file['filename'] . '...');
                $this->importFile = new ImportFile($year, $dir, $file['filename'], $this->io);
                $this->importFile->read();
                if ($this->importFile->getError()) {
                    $this->io->error($this->importFile->getError());

                    return;
                }

                // Read in worksheet info and validate data
                $this->io->out('Analyzing worksheets...');
                $this->io->out();
                foreach ($this->importFile->getWorksheets() as $worksheetName => $worksheetInfo) {
                    $this->io->info('Worksheet: ' . $worksheetName);
                    try {
                        $this->importFile->selectWorksheet($worksheetName);
                        $this->processLocations();
                    } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                        $this->io->out();
                        $this->io->error($e->getMessage());

                        return;
                    } catch (Exception $e) {
                        $this->io->out();
                        $this->io->error($e->getMessage());

                        return;
                    }
                }

                // Free up memory
                $this->importFile->spreadsheet->disconnectWorksheets();
                unset($this->importFile);

                $this->io->out();
            }
        }
    }
}
