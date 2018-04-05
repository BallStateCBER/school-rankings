<?php
namespace App\Model\Entity;

use Cake\I18n\FrozenTime;
use Cake\ORM\Entity;

/**
 * Ranking Entity
 *
 * @property int $id
 * @property int $user_id
 * @property int $formula_id
 * @property int $school_type_id
 * @property bool $for_school_districts
 * @property int $school_level_id
 * @property string $hash
 * @property FrozenTime $created
 *
 * @property User $user
 * @property Formula $formula
 * @property SchoolType $school_type
 * @property SchoolLevel $school_level
 * @property City[] $cities
 * @property County[] $counties
 * @property Range[] $ranges
 * @property SchoolDistrict[] $school_districts
 * @property State[] $states
 */
class Ranking extends Entity
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
        'user_id' => true,
        'formula_id' => true,
        'school_type_id' => true,
        'for_school_districts' => true,
        'school_level_id' => true,
        'hash' => true,
        'created' => true,
        'user' => true,
        'formula' => true,
        'school_type' => true,
        'school_level' => true,
        'cities' => true,
        'counties' => true,
        'ranges' => true,
        'school_districts' => true,
        'states' => true
    ];
}
