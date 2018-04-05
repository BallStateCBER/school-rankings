<?php
namespace App\Model\Entity;

use Cake\I18n\FrozenTime;
use Cake\ORM\Entity;

/**
 * Formula Entity
 *
 * @property int $id
 * @property int $user_id
 * @property bool $is_example
 * @property string $title
 * @property string $notes
 * @property string $context
 * @property string $hash
 * @property FrozenTime $created
 * @property FrozenTime $modified
 *
 * @property User $user
 * @property Ranking[] $rankings
 * @property SharedFormula[] $shared_formulas
 * @property Criterion[] $criteria
 */
class Formula extends Entity
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
        'user_id' => true,
        'is_example' => true,
        'title' => true,
        'notes' => true,
        'context' => true,
        'hash' => true,
        'created' => true,
        'modified' => true,
        'user' => true,
        'rankings' => true,
        'shared_formulas' => true,
        'criteria' => true
    ];
}
