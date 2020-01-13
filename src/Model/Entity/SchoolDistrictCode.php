<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * SchoolDistrictCode Entity
 *
 * @property int $id
 * @property string $code
 * @property int $school_district_id
 * @property string $year
 *
 * @property SchoolDistrict $school_district
 */
class SchoolDistrictCode extends Entity
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
        'code' => true,
        'school_district_id' => true,
        'year' => true,
        'school_district' => true,
    ];
}
