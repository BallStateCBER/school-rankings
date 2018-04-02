<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * SpreadsheetColumnsMetric Entity
 *
 * @property int $id
 * @property string $year
 * @property string $filename
 * @property string $context
 * @property string $worksheet
 * @property string $group_name
 * @property string $column_name
 * @property int $metric_id
 * @property \Cake\I18n\FrozenTime $created
 */
class SpreadsheetColumnsMetric extends Entity
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
        'year' => true,
        'filename' => true,
        'context' => true,
        'worksheet' => true,
        'group_name' => true,
        'column_name' => true,
        'metric_id' => true,
        'created' => true,
        'metric' => true
    ];
}
