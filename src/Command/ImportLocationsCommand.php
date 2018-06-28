<?php
namespace App\Command;

use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Filesystem\Folder;

/**
 * Class ImportLocationsCommand
 * @package App\Command
 * @property ConsoleIo $io
 * @property string[] $files
 */
class ImportLocationsCommand extends Command
{
    private $io;
    private $files;

    /**
     * Processes location info file and updates the database
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return int|null|void
     * @throws \Exception
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->io = $io;

        $dataPath = ROOT . DS . 'data-location';
        $this->files = (new Folder($dataPath))->find();
        if (!$this->files) {
            $io->out('No files found in ' . $dataPath);

            return;
        }

        $file = $this->selectFile();
        $io->out($file . ' selected');
    }

    /**
     * Asks the user for input and returns a filename
     *
     * @return string
     */
    private function selectFile()
    {
        $this->io->out('Available files:');

        $tableData = [];
        foreach ($this->files as $key => $file) {
            $tableData[] = [$key + 1, $file];
        }
        array_unshift($tableData, ['Key', 'File']);
        $this->io->helper('Table')->output($tableData);

        $maxKey = (count($tableData) - 1);
        $fileKey = $this->io->ask('Select a file (1-' . $maxKey . '):');

        return $this->files[$fileKey - 1];
    }
}
