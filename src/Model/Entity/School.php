<?php
namespace App\Model\Entity;

use Cake\I18n\FrozenTime;
use Cake\ORM\Entity;

/**
 * School Entity
 *
 * @property int $id
 * @property int $school_district_id
 * @property int $school_type_id
 * @property string $name
 * @property string $address
 * @property string $url
 * @property string $phone
 * @property string $code
 * @property FrozenTime $created
 * @property FrozenTime $modified
 *
 * @property SchoolDistrict $school_district
 * @property SchoolType $school_type
 * @property Statistic[] $statistics
 * @property City[] $cities
 * @property County[] $counties
 * @property Grade[] $grades
 * @property State[] $states
 */
class School extends Entity
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
        'school_district_id' => true,
        'school_type_id' => true,
        'name' => true,
        'address' => true,
        'url' => true,
        'phone' => true,
        'code' => true,
        'created' => true,
        'modified' => true,
        'school_district' => true,
        'school_type' => true,
        'school_statistics' => true,
        'cities' => true,
        'counties' => true,
        'grades' => true,
        'states' => true
    ];
}
