<?php
namespace App\Model\Table;

use App\Model\Entity\SpreadsheetColumnsMetric;
use Cake\Datasource\EntityInterface;
use Cake\Network\Exception\InternalErrorException;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Exception;

/**
 * SpreadsheetColumnsMetrics Model
 *
 * @method SpreadsheetColumnsMetric get($primaryKey, $options = [])
 * @method SpreadsheetColumnsMetric newEntity($data = null, array $options = [])
 * @method SpreadsheetColumnsMetric[] newEntities(array $data, array $options = [])
 * @method SpreadsheetColumnsMetric|bool save(EntityInterface $entity, $options = [])
 * @method SpreadsheetColumnsMetric patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method SpreadsheetColumnsMetric[] patchEntities($entities, array $data, array $options = [])
 * @method SpreadsheetColumnsMetric findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class SpreadsheetColumnsMetricsTable extends Table
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

        $this->setTable('spreadsheet_columns_metrics');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('SchoolMetrics', [
            'foreignKey' => 'metric_id',
            'joinType' => 'INNER'
        ]);

        $this->belongsTo('SchoolDistrictMetrics', [
            'foreignKey' => 'metric_id',
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
            ->scalar('year')
            ->maxLength('year', 255)
            ->requirePresence('year', 'create')
            ->notEmpty('year');

        $validator
            ->scalar('filename')
            ->maxLength('filename', 255)
            ->requirePresence('filename', 'create')
            ->notEmpty('filename');

        $validator
            ->scalar('context')
            ->maxLength('context', 255)
            ->requirePresence('context', 'create')
            ->notEmpty('context');

        $validator
            ->scalar('worksheet')
            ->maxLength('worksheet', 255)
            ->requirePresence('worksheet', 'create')
            ->notEmpty('worksheet');

        $validator
            ->scalar('group_name')
            ->maxLength('group_name', 255)
            ->allowEmpty('group_name');

        $validator
            ->scalar('column_name')
            ->maxLength('column_name', 255)
            ->requirePresence('column_name', 'create')
            ->notEmpty('column_name');

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
        $rules->add(function ($entity, $options) use ($rules) {
            switch ($entity->context) {
                case 'school':
                    return $rules->existsIn(['metric_id'], 'SchoolMetrics')($entity, $options);
                case 'district':
                    return $rules->existsIn(['metric_id'], 'SchoolDistrictMetrics')($entity, $options);
                default:
                    throw new Exception('Unrecognized metric context: ' . $entity->context);
            }
        }, 'metricExists');

        return $rules;
    }

    /**
     * Returns the corresponding SchoolMetric ID or SchoolDistrictMetric ID, or NULL if no result is found
     *
     * @param array $conditions Array of parameters for find()->where()
     * @return int|null
     * @throws InternalErrorException
     */
    public function getMetricId($conditions)
    {
        $expectedKeys = [
            'year',
            'filename',
            'context',
            'worksheet',
            'group_name',
            'column_name'
        ];
        foreach ($expectedKeys as $key) {
            if (!array_key_exists($key, $conditions)) {
                throw new InternalErrorException('Cannot find metric. Missing parameter: ' . $key);
            }
        }
        if (count($conditions) != count($expectedKeys)) {
            throw new InternalErrorException('Cannot find metric. Invalid parameters given.');
        }

        /** @var SpreadsheetColumnsMetric $result */
        $result = $this->find()
            ->select(['metric_id'])
            ->where(compact($conditions))
            ->orderDesc('created')
            ->first();

        return $result ? $result->metric_id : null;
    }
}
