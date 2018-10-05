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

}
