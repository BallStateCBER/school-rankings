<?php
namespace App\Command;

use Cake\Console\ConsoleIo;
use Cake\Filesystem\Folder;
use Exception;

class Utility
{
    /**
     * Takes a string of IDs and returns an array
     *
     * @param string $string String of IDs and ID ranges (e.g. 1,2,3,5-7)
     * @throws Exception
     * @return int[]
     */
    public static function parseMultipleIdString($string)
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
    public static function removeLeadingZeros($string)
    {
        return ltrim($string, '0');
    }

    /**
     * Asks the user for input and returns a filename, or FALSE if no files are available
     *
     * @param string $directory Directory in which to search for files
     * @param ConsoleIo $io ConsoleIo object
     * @return string|bool
     */
    public static function selectFile($directory, $io)
    {
        $files = (new Folder($directory))->find();
        if (!$files) {
            $io->out('No files found in ' . $directory);

            return false;
        }

        $io->out('Available files:');

        $tableData = [];
        foreach ($files as $key => $file) {
            $tableData[] = [$key + 1, $file];
        }
        array_unshift($tableData, ['Key', 'File']);
        $io->helper('Table')->output($tableData);

        $maxKey = (count($tableData) - 1);
        $fileKey = $io->ask('Select a file (1-' . $maxKey . '):');

        return $files[$fileKey - 1];
    }
}
