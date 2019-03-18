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
 * @property array $files
 * @property ConsoleIo $io
 * @property ImportFile $importFile
 * @property SchoolDistrictsTable $schoolDistrictsTable
 * @property SchoolsTable $schoolsTable
 * @property string $currentFile
 */
class PopulateLocationOriginCommand extends Command
{
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

        $years = $this->getYears();

        foreach ($years as $year) {
            if (count($years) > 1) {
                $io->info("----------\n|  $year  |\n----------");
                $io->out();
            }

            $files = $this->getAllFiles();

            // Loop through the files in the selected year
            $dir = ROOT . DS . 'data' . DS . 'statistics' . DS . $year . DS;
            foreach ($files[$year] as $file) {
                $this->currentFile = $file['filename'];
                $io->out('Opening ' . $file['filename'] . '...');
                $this->importFile = new ImportFile($year, $dir, $file['filename'], $io);
                $this->importFile->read();
                if ($this->importFile->getError()) {
                    $io->error($this->importFile->getError());

                    return;
                }

                // Read in worksheet info and validate data
                $io->out('Analyzing worksheets...');
                $io->out();
                foreach ($this->importFile->getWorksheets() as $worksheetName => $worksheetInfo) {
                    $io->info('Worksheet: ' . $worksheetName);
                    try {
                        $this->importFile->selectWorksheet($worksheetName);
                        $this->processLocations();
                    } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                        $io->nl();
                        $io->error($e->getMessage());

                        return;
                    } catch (Exception $e) {
                        $io->nl();
                        $io->error($e->getMessage());

                        return;
                    }
                }

                // Free up memory
                $this->importFile->spreadsheet->disconnectWorksheets();
                unset($this->importFile);

                $io->out();
            }
        }

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
                /** @var SchoolDistrict $district */
                $district = $this->schoolDistrictsTable->find()
                    ->select(['id', 'name'])
                    ->where([
                        'code' => $location['districtCode'],
                        'origin_file' => ''
                    ])
                    ->first();
                if ($district) {
                    $this->setDistrictOriginFile($district);
                    $updatedDistrictCount++;
                }
            }

            // Identify school
            if (isset($location['schoolCode'])) {
                /** @var School $school */
                $school = $this->schoolsTable->find()
                    ->select(['id', 'name'])
                    ->where([
                        'code' => $location['schoolCode'],
                        'origin_file' => ''
                    ])
                    ->first();
                if ($school) {
                    $this->setSchoolOriginFile($school);
                    $updatedSchoolCount++;
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
        //$this->io->out(' - Would have set district ' . $district->id . ' origin to ' . $this->currentFile);
        return;
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
        //$this->io->out(' - Would have set school ' . $school->id . ' origin to ' . $this->currentFile);
        return;
        if (!$this->schoolsTable->save($school)) {
            throw new Exception("Error updating school: \n" . print_r($school->getErrors(), true));
        }
    }
}
