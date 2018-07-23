<?php
namespace App\Shell;

use App\Shell\Task\RankTask;
use Cake\Console\Shell;

/**
 * Class RankTestShell
 * @package App\Shell
 * @property RankTask $Rank
 */
class RankTestShell extends Shell
{
    public $tasks = ['Rank'];

    /**
     * Defines this shell's arguments/options
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser()
    {
        $parser = parent::getOptionParser();
        $parser->addArgument('ranking id', [
            'help' => 'The ID of the ranking record',
            'required' => true
        ]);

        return $parser;
    }

    /**
     * Processes the ranking job with the provided ranking ID
     *
     * @throws \Exception
     * @return void
     */
    public function main()
    {
        $rankingId = $this->args[0];
        $this->Rank->process($rankingId);
    }
}
