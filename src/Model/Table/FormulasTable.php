<?php
namespace App\Model\Table;

use App\Model\Context\Context;
use App\Model\Entity\Formula;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Utility\Security;
use Cake\Validation\Validator;

/**
 * Formulas Model
 *
 * @property UsersTable|BelongsTo $Users
 * @property RankingsTable|HasMany $Rankings
 * @property SharedFormulasTable|HasMany $SharedFormulas
 * @property CriteriaTable|BelongsToMany $Criteria
 *
 * @method Formula get($primaryKey, $options = [])
 * @method Formula newEntity($data = null, array $options = [])
 * @method Formula[] newEntities(array $data, array $options = [])
 * @method Formula|bool save(EntityInterface $entity, $options = [])
 * @method Formula patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method Formula[] patchEntities($entities, array $data, array $options = [])
 * @method Formula findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin TimestampBehavior
 */
class FormulasTable extends Table
{

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->setTable('formulas');
        $this->setDisplayField('title');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
        ]);
        $this->hasMany('Rankings', [
            'foreignKey' => 'formula_id',
        ])->setDependent(true);
        $this->hasMany('SharedFormulas', [
            'foreignKey' => 'formula_id',
        ])->setDependent(true);
        $this->hasMany('Criteria', [
            'foreignKey' => 'formula_id',
        ])->setDependent(true);
    }

    /**
     * Default validation rules.
     *
     * @param Validator $validator Validator instance.
     * @return Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->boolean('is_example')
            ->requirePresence('is_example', 'create');

        $validator
            ->scalar('title')
            ->maxLength('title', 255)
            ->allowEmptyString('title');

        $validator
            ->scalar('notes')
            ->allowEmptyString('notes');

        $validator
            ->scalar('context')
            ->maxLength('context', 255)
            ->requirePresence('context', 'create')
            ->notEmptyString('context')
            ->inList('context', Context::getContexts());

        $validator
            ->scalar('hash')
            ->minLength('hash', 8)
            ->maxLength('hash', 8)
            ->requirePresence('hash', 'create')
            ->notEmptyString('hash');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param RulesChecker $rules The rules object to be modified.
     * @return RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        $rules->add($rules->existsIn(['user_id'], 'Users'));
        $rules->add($rules->isUnique(['hash']));

        return $rules;
    }

    /**
     * Returns a random string to be used as a unique identifier for a formula
     *
     * @return string
     */
    public function generateHash()
    {
        do {
            $hash = Security::randomString(8);
        } while ($this->exists(['hash' => $hash]));

        return $hash;
    }
}
