<?php
namespace App\Model\Entity;

use App\Model\Entity\Traits\RankableTrait;
use Cake\I18n\FrozenTime;
use Cake\ORM\Entity;

/**
 * SchoolDistrict Entity
 *
 * @property int $id
 * @property string $name
 * @property string $url
 * @property string $phone
 * @property string $origin_file
 * @property bool $closed
 * @property FrozenTime $created
 * @property FrozenTime $modified
 *
 * @property City[] $cities
 * @property County[] $counties
 * @property Ranking[] $rankings
 * @property School[] $schools
 * @property SchoolDistrictCode[] $school_district_codes
 * @property State[] $states
 * @property Statistic[] $statistics
 */
class SchoolDistrict extends Entity
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
        'name' => true,
        'url' => true,
        'origin_file' => true,
        'closed' => true,
        'phone' => true,
        'created' => true,
        'modified' => true,
        'statistics' => true,
        'schools' => true,
        'rankings' => true,
        'cities' => true,
        'counties' => true,
        'states' => true,
        'school_district_codes' => true,
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
            'N/A',
        ]);
    }

    /**
     * Returns the names of fields (or associations) that can be written to when merging two school districts
     *
     * @return array
     */
    public function getMergeableFields()
    {
        return [
            'name',
            'url',
            'origin_file',
            'closed',
            'phone',
            'statistics',
            'schools',
            'rankings',
            'cities',
            'counties',
            'states',
            'school_district_codes',
        ];
    }
}
