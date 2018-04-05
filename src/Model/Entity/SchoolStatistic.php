<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * SchoolStatistic Entity
 *
 * @property int $id
 * @property int $metric_id
 * @property int $school_id
 * @property string $value
 * @property int $year
 * @property bool $contiguous
 * @property string $file
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime $modified
 *
 * @property \App\Model\Entity\Metric $metric
 * @property \App\Model\Entity\School $school
 */
class SchoolStatistic extends Entity
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
        'school_id' => true,
        'value' => true,
        'year' => true,
        'contiguous' => true,
        'file' => true,
        'created' => true,
        'modified' => true,
        'metric' => true,
        'school' => true
    ];
}
