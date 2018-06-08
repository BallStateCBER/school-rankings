<?php
namespace App\Model\Entity;

use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\Entity;

/**
 * Statistic Entity
 *
 * @property int $id
 * @property int $metric_id
 * @property string|int|float $value
 * @property int $year
 * @property bool $contiguous
 * @property string $file
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime $modified
 *
 * @property \App\Model\Entity\Metric $metric
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
     * Returns either 'school' or 'district' depending on what the current subclass is
     *
     * @return string
     * @throws InternalErrorException
     */
    public function getCurrentContext()
    {
        $className = explode('\\', get_class($this));

        switch (end($className)) {
            case 'SchoolStatistic':
                return 'school';
            case 'SchoolDistrictStatistic':
                return 'district';
        }

        throw new InternalErrorException('Can\'t get context for ' . get_class($this) . ' class');
    }

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
}
