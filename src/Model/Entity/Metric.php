<?php
namespace App\Model\Entity;

use Cake\I18n\FrozenTime;
use Cake\ORM\Entity;

/**
 * Metric parent entity
 *
 * @property int $id
 * @property string $context
 * @property string $name
 * @property string $description
 * @property string $type
 * @property int $parent_id
 * @property int $lft
 * @property int $rght
 * @property bool $selectable
 * @property bool $visible
 * @property bool|null $is_percent
 * @property FrozenTime $created
 *
 * @property Metric $parent_metric
 * @property Metric[] $children
 */
class Metric extends Entity
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
        '*' => true,
    ];

    /**
     * Takes an array of metrics and returns an array with all visible = false metrics and their descendants removed
     *
     * @param array|Metric[] $metrics Collection of metrics
     * @return array|Metric[]
     */
    public static function removeNotVisible($metrics)
    {
        foreach ($metrics as $i => $metric) {
            if (!$metric['visible']) {
                unset($metrics[$i]);
                continue;
            }

            $metrics[$i]['children'] = self::removeNotVisible($metrics[$i]['children']);
        }

        return $metrics;
    }
}
