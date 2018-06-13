<?php
namespace App\Command;

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
}
