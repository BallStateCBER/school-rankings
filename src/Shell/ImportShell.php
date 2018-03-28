<?php
namespace App\Shell;

use App\Import\Import;
use Cake\Console\Shell;

class ImportShell extends Shell
{

    /**
     * Display help for this console.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser()
    {
        $parser = parent::getOptionParser();
        $parser->addSubcommand('status', [
            'help' => 'Show what files are available and which have been imported',
        ])->addOption('year', [
            'help' => 'The specific year to look up',
            'default' => null
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
        $import = new Import();
        $files = $import->getFiles();

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
}
