<?php
namespace App\Model\Entity;

use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\Entity;

/**
 * Statistic Entity
 *
 * @property int $id
 * @property int $metric_id
 * @property int $school_id
 * @property int $school_district_id
 * @property string|int|float $value
 * @property int $year
 * @property bool $contiguous
 * @property string $file
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime $modified
 *
 * @property \App\Model\Entity\Metric $metric
 * @property \App\Model\Entity\School $school
 * @property \App\Model\Entity\SchoolDistrict $school_district
 * @property int|float $numeric_value
 */
class Statistic extends Entity
{

    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array
     */
    protected $_accessible = [
        '*' => true
    ];

    /**
     * Converts numeric values to integers or floats rounded to 5 decimal places
     *
     * Applies this conversion when accessing $stat->value or before saving statistics to the database
     *
     * @param string|float|int $value Statistic value
     * @return string|float|int
     */
    protected function _getValue($value)
    {
        return self::roundValue($value);
    }

    /**
     * Casts the provided value to int or float if appropriate and rounds to five decimal places if the value is a float
     *
     * @param string|int|float $value Statistic value
     * @return string|float|int
     */
    public static function roundValue($value)
    {
        // String
        if (!is_numeric($value)) {
            return $value;
        }

        // Integer
        if (is_int($value) || strpos($value, '.') === false) {
            return (int)$value;
        }

        // Float
        return round((float)$value, 5);
    }

    /**
     * Converts non-numeric values (e.g. letter grades and percent values) to ints or floats
     *
     * @return float|int
     */
    protected function _getNumericValue()
    {
        $value = $this->_properties['value'];

        // Grade values
        switch ($value) {
            case 'a':
            case 'A':
                return 4;
            case 'b':
            case 'B':
                return 3;
            case 'c':
            case 'C':
                return 2;
            case 'd':
            case 'D':
                return 1;
            case 'f':
            case 'F':
                return 0;
        }

        // Percent values
        if (self::isPercentValue($value)) {
            $value = substr($value, 0, -1);
        }

        if (!is_numeric($value)) {
            $id = $this->properties['id'];
            throw new InternalErrorException("Invalid value for statistic #$id: $value");
        }

        return (float)$value;
    }

    /**
     * Returns TRUE if the specified value is formatted as a percent, e.g. "95.5%"
     *
     * @param mixed $value Value to evaluate
     * @return bool
     */
    public static function isPercentValue($value)
    {
        if (strpos($value, '%') != strlen($value) - 1) {
            return false;
        }

        $substr = substr($value, 0, -1);
        if (!is_numeric($substr)) {
            return false;
        }

        return true;
    }

    /**
     * Converts a numeric value into a percent value, e.g. 0.95 => "95%"
     *
     * @param mixed $value Value to convert
     * @return string
     */
    public static function convertValueToPercent($value)
    {
        if (self::isPercentValue($value)) {
            return $value;
        }

        return round((float)$value * 100, 2) . '%';
    }

    /**
     * Converts a percent string value to a numeric value, e.g. "95%" => 0.95
     *
     * @param mixed $value Value to convert
     * @return string
     * @throws InternalErrorException
     */
    public static function convertValueFromPercent($value)
    {
        if (self::isPercentValue($value)) {
            $value = (float)substr($value, 0, -1);

            return $value / 100;
        }

        if (is_numeric($value)) {
            return $value;
        }

        throw new InternalErrorException('Cannot convert non-numeric value using convertValueFromPercent()');
    }

    /**
     * Returns this statistic's value formatted as a percent string, e.g. "75.3%"
     *
     * @return string
     */
    public function getPercentFormattedValue()
    {
        if (self::isPercentValue($this->value)) {
            return $this->value;
        }

        return self::convertValueToPercent($this->value);
    }
}
