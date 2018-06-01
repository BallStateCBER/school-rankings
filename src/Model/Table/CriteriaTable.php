<?php
namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Criteria Model
 *
 * @property \App\Model\Table\MetricsTable|\Cake\ORM\Association\BelongsTo $Metrics
 * @property \App\Model\Table\FormulasTable|\Cake\ORM\Association\BelongsToMany $Formulas
 *
 * @method \App\Model\Entity\Criterion get($primaryKey, $options = [])
 * @method \App\Model\Entity\Criterion newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\Criterion[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Criterion|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Criterion patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Criterion[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\Criterion findOrCreate($search, callable $callback = null, $options = [])
 */
class CriteriaTable extends Table
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

        $this->setTable('criteria');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Metrics', [
            'foreignKey' => 'metric_id',
            'joinType' => 'INNER'
        ]);
        $this->belongsTo('Formulas', [
            'foreignKey' => 'formula_id',
            'joinType' => 'INNER'
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
            ->allowEmpty('id', 'create');

        $validator
            ->integer('weight')
            ->requirePresence('weight', 'create')
            ->notEmpty('weight');

        $validator
            ->scalar('preference')
            ->maxLength('preference', 255)
            ->requirePresence('preference', 'create')
            ->notEmpty('preference');

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
        $rules->add($rules->existsIn(['metric_id'], 'Metrics'));

        return $rules;
    }
}
