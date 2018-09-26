<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * RankingsSchoolType Entity
 *
 * @property int $id
 * @property int $ranking_id
 * @property int $school_type_id
 * @property \Cake\I18n\FrozenTime $created
 *
 * @property \App\Model\Entity\Ranking $ranking
 * @property \App\Model\Entity\SchoolType $school_type
 */
class RankingsSchoolType extends Entity
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
        'ranking_id' => true,
        'school_type_id' => true,
        'created' => true,
        'ranking' => true,
        'school_type' => true
    ];
}
