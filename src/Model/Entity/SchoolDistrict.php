<?php
namespace App\Model\Entity;

use Cake\I18n\FrozenTime;
use Cake\ORM\Entity;

/**
 * SchoolDistrict Entity
 *
 * @property int $id
 * @property string $name
 * @property string $url
 * @property string $phone
 * @property string $code
 * @property FrozenTime $created
 * @property FrozenTime $modified
 *
 * @property Statistic[] $statistics
 * @property School[] $schools
 * @property Ranking[] $rankings
 * @property City[] $cities
 * @property County[] $counties
 * @property State[] $states
 */
class SchoolDistrict extends Entity
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
        'name' => true,
        'url' => true,
        'code' => true,
        'phone' => true,
        'created' => true,
        'modified' => true,
        'school_district_statistics' => true,
        'schools' => true,
        'rankings' => true,
        'cities' => true,
        'counties' => true,
        'states' => true
    ];

    /**
     * Returns true or false, indicating whether or not the provided code represents something
     * other than an actual district (like an "Independent Non-Public Schools" entry)
     *
     * @param string $code School district code
     * @return bool
     */
    public static function isDummyCode($code)
    {
        return in_array($code, [
            '-0999',
            '-999',
            'N/A'
        ]);
    }
}
