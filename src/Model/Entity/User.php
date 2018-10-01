<?php
namespace App\Model\Entity;

/**
 * User Entity
 *
 * @property int $id
 * @property string $email
 * @property string $password
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime $modified
 *
 * @property \App\Model\Entity\Formula[] $formulas
 * @property \App\Model\Entity\Ranking[] $rankings
 * @property \App\Model\Entity\SharedFormula[] $shared_formulas
 */
class User extends \CakeDC\Users\Model\Entity\User
{

    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * These values are set in \CakeDC\Users\Model\Entity\User
     *
     * @var array
     */
    protected $_accessible;

    /**
     * Fields that are excluded from JSON versions of the entity.
     *
     * These values are set in \CakeDC\Users\Model\Entity\User
     *
     * @var array
     */
    protected $_hidden;
}
