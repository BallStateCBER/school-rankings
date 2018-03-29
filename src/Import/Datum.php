<?php
namespace App\Import;

class Datum
{
    /**
     * Returns true if the value is ignorable or conforms to expected formats
     *
     * @param mixed $value Value to check
     * @return bool
     */
    public function isValid($value)
    {
        if ($this->isIgnorable($value)) {
            return true;
        }

        if (is_numeric($value)) {
            return true;
        }

        if ($this->isPercentage($value)) {
            return true;
        }

        return false;
    }

    /**
     * Returns true if the value is a number, followed by a percent sign
     *
     * @param mixed $value Value to check
     * @return bool
     */
    public function isPercentage($value)
    {
        if (substr($value, -1) != '%') {
            return false;
        }

        if (!is_numeric(substr($value, 0, -1))) {
            return false;
        }

        return true;
    }

    /**
     * Returns true if the value represents a letter grade
     *
     * Assumes that grades will be capitalized
     *
     * @param mixed $value Value to check
     * @return bool
     */
    public function isGrade($value)
    {
        $grades = ['A', 'B', 'C', 'D', 'F'];

        return in_array($value, $grades);
    }

    /**
     * Returns true if the value is ignorable
     *
     * @param mixed $value Value to check
     * @return bool
     */
    public function isIgnorable($value)
    {
        $ignorableValues = [
            '',
            null,
            'No Grade',
            '***',
            '*',
            'Appeal Pending',
            '#N/A',
            'NA',
            'NULL',
            '#VALUE!'
        ];

        return in_array($value, $ignorableValues);
    }
}
