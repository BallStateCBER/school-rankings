<?php
namespace App\Model\Entity;

use App\Model\Entity\Traits\RankableTrait;
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
 * @property string $origin_file
 * @property bool $closed
 * @property FrozenTime $created
 * @property FrozenTime $modified
 *
 * @property City[] $cities
 * @property County[] $counties
 * @property Grade[] $grades
 * @property SchoolCode[] $school_codes
 * @property SchoolDistrict $school_district
 * @property SchoolType $school_type
 * @property State[] $states
 * @property Statistic[] $statistics
 */
class School extends Entity
{
    use RankableTrait;

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
        'closed' => true,
        'origin_file' => true,
        'created' => true,
        'modified' => true,
        'school_district' => true,
        'school_type' => true,
        'statistics' => true,
        'cities' => true,
        'counties' => true,
        'grades' => true,
        'states' => true,
        'school_codes' => true
    ];

    /**
     * Returns the names of fields (or associations) that can be written to when merging two schools
     *
     * @return array
     */
    public function getMergeableFields()
    {
        return [
            'school_district_id',
            'school_type_id',
            'name',
            'address',
            'url',
            'phone',
            'closed',
            'origin_file',
            'statistics',
            'cities',
            'counties',
            'grades',
            'states',
            'school_codes'
        ];
    }
}
