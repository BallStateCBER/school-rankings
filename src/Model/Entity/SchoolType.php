<?php
namespace App\Model\Entity;

use Cake\I18n\FrozenTime;
use Cake\ORM\Entity;

/**
 * SchoolType Entity
 *
 * @property int $id
 * @property string $name
 * @property FrozenTime $created
 *
 * @property Ranking[] $rankings
 * @property School[] $schools
 */
class SchoolType extends Entity
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
        'created' => true,
        'rankings' => true,
        'schools' => true
    ];
}
