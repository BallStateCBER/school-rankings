<?php
namespace App\Command;

use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Shell\Helper\ProgressHelper;
use Exception;

/**
 * Class CommonCommand
 * @package App\Command
 * @property ProgressHelper $progress
 * @property ConsoleIo $io
 */
class CommonCommand extends Command
{
    protected $progress;
    protected $io;

    /**
     * Sets class properties
     *
     * @param Arguments $args Arguments
     * @param ConsoleIo $io Console IO object
     * @return void
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->io = $io;
        $this->progress = $this->io->helper('Progress');
    }

    /**
     * Creates a progress bar, draws it, and returns it
     *
     * @param int $total Total number of items to be processed
     * @return void
     */
    protected function makeProgressBar($total)
    {
        /** @var ProgressHelper $progress */
        $this->progress = $this->io->helper('Progress');
        $this->progress->init([
            'total' => $total,
            'width' => 60,
        ]);
        $this->progress->draw();
    }

    /**
     * Takes a string of IDs and returns an array
     *
     * @param string $string String of IDs and ID ranges (e.g. 1,2,3,5-7)
     * @throws Exception
     * @return int[]
     */
    protected function parseMultipleIdString($string)
    {
        $ids = [];

        foreach (explode(',', $string) as $range) {
            $dashCount = substr_count($range, '-');

            // Single ID
            if (!$dashCount) {
                if (!is_numeric($range)) {
                    throw new Exception('Invalid ID: ' . $range);
                }
                $ids[] = (int)$range;
                continue;
            }

            // Range of IDs
            if ($dashCount == 1) {
                list($rangeStart, $rangeEnd) = explode('-', $range);
                foreach ([$rangeStart, $rangeEnd] as $id) {
                    if (!is_numeric($id)) {
                        throw new Exception('Invalid ID: ' . $id);
                    }
                }
                $ids = array_merge($ids, range((int)$rangeStart, (int)$rangeEnd));
                continue;
            }

            throw new Exception('Invalid range: ' . $range);
        }

        return $ids;
    }

    /**
     * Strips out leading zeros from a string
     *
     * @param string $string String to remove leading zeros from
     * @return string
     */
    protected function removeLeadingZeros($string)
    {
        return ltrim($string, '0');
    }

    /**
     * Displays a message and a prompt for a 'y' or 'n' response and returns TRUE if response is 'y'
     *
     * @param string $msg Message to display
     * @param string $default Default selection (leave blank for 'y')
     * @return bool
     */
    protected function getConfirmation($msg, $default = 'y')
    {
        return $this->io->askChoice(
            $msg,
            ['y', 'n'],
            $default
        ) == 'y';
    }
}
