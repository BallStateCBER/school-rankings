<?php
namespace App\Command;

use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Class ImportStatsStatusCommand
 * @package App\Command
 */
class ImportStatsStatusCommand extends Command
{
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
            'year' => ['help' => 'The specific year to look up']
        ]);

        return $parser;
    }

    /**
     * Shows what files are available in /data and which have been imported
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return int|null|void
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $year = $args->hasArgument('year') ? $args->getArgument('year') : null;

        $files = ImportStatsCommand::getFiles();

        if ($year) {
            if (isset($files[$year])) {
                $files = [$year => $files[$year]];
            } else {
                $io->out('No import files found in data' . DS . 'statistics' . DS . $year);

                return;
            }
        }

        foreach ($files as $year => $yearFiles) {
            $io->info($year);
            array_unshift($yearFiles, ['File', 'Imported']);
            $io->helper('Table')->output($yearFiles);
            $io->out();
        }
    }
}
