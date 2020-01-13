<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Range Entity
 *
 * @property int $id
 * @property string $center
 * @property int $distance
 *
 * @property Ranking[] $rankings
 */
class Range extends Entity
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
        'center' => true,
        'distance' => true,
        'rankings' => true,
    ];
}
