<?php
namespace App\Command;

use App\Import\ImportFile;
use App\Model\Table\SchoolDistrictsTable;
use App\Model\Table\SchoolsTable;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;
use Exception;
use PhpOffice\PhpSpreadsheet\Exception as PhpOfficeException;

/**
 * ImportClosures command.
 *
 * @property ConsoleIo $io
 * @property ImportFile $importFile
 * @property SchoolDistrictsTable $districtsTable
 * @property SchoolsTable $schoolsTable
 * @property string $directory
 */
class ImportClosuresCommand extends Command
{
    private $directory = ROOT . DS . 'data' . DS . 'locations' . DS . 'closed';
    private $districtsTable;
    private $importFile;
    private $io;
    private $schoolsTable;

    /**
     * Initialization method
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();

        $this->districtsTable = TableRegistry::getTableLocator()->get('SchoolDistricts');
        $this->schoolsTable = TableRegistry::getTableLocator()->get('Schools');
    }

    /**
     * Hook method for defining this command's option parser.
     *
     * @param ConsoleOptionParser $parser The parser to be defined
     * @return ConsoleOptionParser The built parser.
     */
    public function buildOptionParser(ConsoleOptionParser $parser)
    {
        $parser = parent::buildOptionParser($parser);
        $parser->setDescription('Imports information about school/district closures from spreadsheets');

        return $parser;
    }

    /**
     * Implement this method with your command's logic.
     *
     * @param Arguments $args The command arguments.
     * @param ConsoleIo $io The console io
     * @return void
     * @throws Exception
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->io = $io;

        $file = Utility::selectFile($this->directory, $this->io);
        if (!$file) {
            return;
        }
        $io->out($file . ' selected');

        $year = Utility::getYearFromFilename($file, $this->io);
        if (!$year) {
            return;
        }

        $io->out("Opening $file...");
        $this->importFile = new ImportFile($year, $this->directory, $file, $io);
        $this->importFile->ignoreWorksheets(['closed']);
        if ($this->importFile->getError()) {
            $io->error($this->importFile->getError());

            return;
        }

        // Read in worksheet info and validate data
        $io->out('Analyzing worksheets...');
        $io->out();
        $this->importFile->read();
        foreach ($this->importFile->getWorksheets() as $worksheetName => $worksheetInfo) {
            $context = $worksheetInfo['context'];
            $io->out('Processing ' . ucwords($context) . 's');

            try {
                $this->importFile->selectWorksheet($worksheetName);
                $this->importFile->identifyLocations();
            } catch (PhpOfficeException $e) {
                $io->nl();
                $io->error($e->getMessage());

                return;
            } catch (Exception $e) {
                $io->nl();
                $io->error($e->getMessage());

                return;
            }

            // Prepare updates
            $locations = [];
            $table = ($context == 'district') ? $this->districtsTable : $this->schoolsTable;
            $locationCells = $this->importFile->getActiveWorksheetProperty('locations');
            foreach ($locationCells as $locationCell) {
                $locationId = $locationCell[$context . 'Id'];
                $location = $table->get($locationId);
                $location = $table->patchEntity($location, ['closed' => true]);
                if ($location->getErrors()) {
                    $this->io->out("The $context with ID " . $locationId . ' cannot be deleted. Details:');
                    print_r($location->getErrors());

                    return;
                }

                $locations[] = $location;
            }

            $count = count($locations);
            if ($count == 0) {
                continue;
            }

            // Get confirmation from user
            $response = $this->io->askChoice(
                sprintf(
                    "Mark %s %s as being closed?",
                    $count,
                    __n($context, $context . 's', $count)
                ),
                ['y', 'n'],
                'n'
            );
            if ($response != 'y') {
                continue;
            }

            // Update the database
            $progress = Utility::makeProgressBar($count, $this->io);
            foreach ($locations as $location) {
                if (!$table->save($location)) {
                    $this->io->out();
                    $this->io->error(sprintf(
                        "There was an error saving the %s with ID %s",
                        $context,
                        $location->id
                    ));

                    return;
                }
                $progress->increment(1);
                $progress->draw();
            }
            $this->io->out();
        }
    }
}
