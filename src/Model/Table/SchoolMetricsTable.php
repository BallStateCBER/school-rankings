<?php
namespace App\Model\Table;

use App\Model\Entity\SchoolMetric;
use Cake\Datasource\EntityInterface;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * SchoolMetrics Model
 *
 * @property \App\Model\Table\SchoolMetricsTable|\Cake\ORM\Association\BelongsTo $ParentSchoolMetrics
 * @property \App\Model\Table\SchoolMetricsTable|\Cake\ORM\Association\HasMany $ChildSchoolMetrics
 *
 * @method SchoolMetric get($primaryKey, $options = [])
 * @method SchoolMetric newEntity($data = null, array $options = [])
 * @method SchoolMetric[] newEntities(array $data, array $options = [])
 * @method SchoolMetric|bool save(EntityInterface $entity, $options = [])
 * @method SchoolMetric patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method SchoolMetric[] patchEntities($entities, array $data, array $options = [])
 * @method SchoolMetric findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 * @mixin \Cake\ORM\Behavior\TreeBehavior
 */
class SchoolMetricsTable extends MetricsTable
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

        $this->setTable('school_metrics');

        $this->belongsTo('ParentSchoolMetrics', [
            'className' => 'SchoolMetrics',
            'foreignKey' => 'parent_id'
        ]);
        $this->hasMany('ChildSchoolMetrics', [
            'className' => 'SchoolMetrics',
            'foreignKey' => 'parent_id'
        ]);
        $this->hasMany('SchoolStatistics', [
            'className' => 'SchoolStatistics',
            'foreignKey' => 'metric_id'
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
        parent::validationDefault($validator);

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
        parent::buildRules($rules);

        $rules->add($rules->existsIn(['parent_id'], 'ParentSchoolMetrics'));

        return $rules;
    }
}
