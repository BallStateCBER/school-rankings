<?php
namespace App\Model\Table;

use App\Model\Entity\Criterion;
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

/**
 * Criteria Model
 *
 * @property \App\Model\Table\MetricsTable|\Cake\ORM\Association\BelongsTo $Metrics
 * @property \App\Model\Table\FormulasTable|\Cake\ORM\Association\BelongsToMany $Formulas
 *
 * @method Criterion get($primaryKey, $options = [])
 * @method Criterion newEntity($data = null, array $options = [])
 * @method Criterion[] newEntities(array $data, array $options = [])
 * @method Criterion|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method Criterion patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method Criterion[] patchEntities($entities, array $data, array $options = [])
 * @method Criterion findOrCreate($search, callable $callback = null, $options = [])
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

        $validator
            ->integer('metric_id')
            ->requirePresence('metric_id', 'create')
            ->notEmpty('metric_id');

        $validator
            ->integer('formula_id')
            ->allowEmpty('formula_id', 'create');

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
        $criteriaTable = $this;

        $rules->add(
            function ($entity, $options) use ($rules, $criteriaTable) {
                $rule = $rules->existsIn(['metric_id'], 'Metrics');

                return $rule($entity, $options);
            },
            'metricNotFound',
            [
                'errorField' => 'metric_id',
                'message' => 'Associated metric not found'
            ]
        );

        return $rules;
    }

    /**
     * Returns the context (school or district) for the provided criterion
     *
     * @param Criterion $criterion Criterion entity
     * @return string
     * @throws InternalErrorException
     */
    public function getContext($criterion)
    {
        if (isset($criterion->formula->context)) {
            return $criterion->formula->context;
        }

        if (isset($criterion->formula_id)) {
            $formulasTable = TableRegistry::getTableLocator()->get('Formulas');
            $formula = $formulasTable->get($criterion->formula_id);

            return $formula->context;
        }

        throw new InternalErrorException('Cannot find context of criterion');
    }
}
