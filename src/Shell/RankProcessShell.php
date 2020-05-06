<?php
namespace App\Shell;

use App\Shell\Task\RankTask;
use Cake\Console\ConsoleOptionParser;
use Cake\Console\Shell;
use Exception;

/**
 * Class RankProcessShell
 *
 * This script (re)processes the ranking job specified by the provided ranking ID
 *
 * @package App\Shell
 * @property RankTask $Rank
 */
class RankProcessShell extends Shell
{
    public $tasks = ['Rank'];

    /**
     * Defines this shell's arguments/options
     *
     * @return ConsoleOptionParser
     */
    public function getOptionParser()
    {
        $parser = parent::getOptionParser();
        $parser->addArgument('ranking id', [
            'help' => 'The ID of the ranking record',
            'required' => true,
        ]);

        return $parser;
    }

    /**
     * Processes the ranking job with the provided ranking ID
     *
     * @throws Exception
     * @return void
     */
    public function main()
    {
        $this->getIo()->info('This script (re)processes the ranking job specified by the provided ranking ID.');
        $rankingId = $this->args[0];
        $this->Rank->process($rankingId);
    }
}
