<?php
namespace App\Model\Entity;

/**
 * SchoolMetric Entity
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property string $type
 * @property int $parent_id
 * @property int $lft
 * @property int $rght
 * @property bool $selectable
 * @property bool $visible
 * @property \Cake\I18n\FrozenTime $created
 *
 * @property \App\Model\Entity\SchoolMetric $parent_school_metric
 * @property \App\Model\Entity\SchoolMetric[] $child_school_metrics
 */
class SchoolMetric extends Metric
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
        '*' => true
    ];
}
