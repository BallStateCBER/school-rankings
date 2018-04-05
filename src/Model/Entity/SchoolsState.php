<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * SchoolsState Entity
 *
 * @property int $id
 * @property int $school_id
 * @property int $state_id
 *
 * @property School $school
 * @property State $state
 */
class SchoolsState extends Entity
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
        'school_id' => true,
        'state_id' => true,
        'school' => true,
        'state' => true
    ];
}
