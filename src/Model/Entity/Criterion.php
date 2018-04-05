<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Criterion Entity
 *
 * @property int $id
 * @property int $metric_id
 * @property int $weight
 * @property string $preference
 *
 * @property \App\Model\Entity\Metric $metric
 * @property \App\Model\Entity\Formula[] $formulas
 */
class Criterion extends Entity
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
        'metric_id' => true,
        'weight' => true,
        'preference' => true,
        'metric' => true,
        'formulas' => true
    ];
}
