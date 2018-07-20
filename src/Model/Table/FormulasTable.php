<?php
namespace App\Model\Table;

use App\Model\Entity\Formula;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
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
            'foreignKey' => 'user_id'
        ]);
        $this->hasMany('Rankings', [
            'foreignKey' => 'formula_id'
        ])->setDependent(true);
        $this->hasMany('SharedFormulas', [
            'foreignKey' => 'formula_id'
        ])->setDependent(true);
        $this->hasMany('Criteria', [
            'foreignKey' => 'formula_id'
        ])->setDependent(true);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->integer('id')
            ->allowEmpty('id', 'create');

        $validator
            ->boolean('is_example')
            ->requirePresence('is_example', 'create')
            ->notEmpty('is_example');

        $validator
            ->scalar('title')
            ->maxLength('title', 255)
            ->allowEmpty('title');

        $validator
            ->scalar('notes')
            ->allowEmpty('notes');

        $validator
            ->scalar('context')
            ->maxLength('context', 255)
            ->requirePresence('context', 'create')
            ->notEmpty('context');

        $validator
            ->scalar('hash')
            ->maxLength('hash', 255)
            ->requirePresence('hash', 'create')
            ->notEmpty('hash');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        $rules->add($rules->existsIn(['user_id'], 'Users'));

        return $rules;
    }
}
