<?php
namespace App\Model\Entity;

use Cake\I18n\FrozenTime;
use Cake\ORM\Entity;

/**
 * County Entity
 *
 * @property int $id
 * @property string $name
 * @property int $state_id
 * @property FrozenTime $created
 *
 * @property State $state
 * @property City[] $cities
 * @property Ranking[] $rankings
 * @property SchoolDistrict[] $school_districts
 * @property School[] $schools
 */
class County extends Entity
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
        'state_id' => true,
        'created' => true,
        'state' => true,
        'cities' => true,
        'rankings' => true,
        'school_districts' => true,
        'schools' => true,
    ];
}
