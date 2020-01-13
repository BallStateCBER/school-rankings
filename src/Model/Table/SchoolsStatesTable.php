<?php
namespace App\Model\Table;

use App\Model\Entity\SchoolsState;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * SchoolsStates Model
 *
 * @property SchoolsTable|BelongsTo $Schools
 * @property StatesTable|BelongsTo $States
 *
 * @method SchoolsState get($primaryKey, $options = [])
 * @method SchoolsState newEntity($data = null, array $options = [])
 * @method SchoolsState[] newEntities(array $data, array $options = [])
 * @method SchoolsState|bool save(EntityInterface $entity, $options = [])
 * @method SchoolsState patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method SchoolsState[] patchEntities($entities, array $data, array $options = [])
 * @method SchoolsState findOrCreate($search, callable $callback = null, $options = [])
 */
class SchoolsStatesTable extends Table
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

        $this->setTable('schools_states');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Schools', [
            'foreignKey' => 'school_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('States', [
            'foreignKey' => 'state_id',
            'joinType' => 'INNER',
        ]);
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
            ->allowEmptyString('id', null, 'create');

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
        $rules->add($rules->existsIn(['school_id'], 'Schools'));
        $rules->add($rules->existsIn(['state_id'], 'States'));

        return $rules;
    }
}
