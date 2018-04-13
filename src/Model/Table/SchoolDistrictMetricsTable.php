<?php
namespace App\Model\Table;

use App\Model\Entity\SchoolDistrictMetric;
use Cake\Datasource\EntityInterface;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * SchoolDistrictMetrics Model
 *
 * @property \App\Model\Table\SchoolDistrictMetricsTable|\Cake\ORM\Association\BelongsTo $ParentSchoolDistrictMetrics
 * @property \App\Model\Table\SchoolDistrictMetricsTable|\Cake\ORM\Association\HasMany $ChildSchoolDistrictMetrics
 *
 * @method SchoolDistrictMetric get($primaryKey, $options = [])
 * @method SchoolDistrictMetric newEntity($data = null, array $options = [])
 * @method SchoolDistrictMetric[] newEntities(array $data, array $options = [])
 * @method SchoolDistrictMetric|bool save(EntityInterface $entity, $options = [])
 * @method SchoolDistrictMetric patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method SchoolDistrictMetric[] patchEntities($entities, array $data, array $options = [])
 * @method SchoolDistrictMetric findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 * @mixin \Cake\ORM\Behavior\TreeBehavior
 */
class SchoolDistrictMetricsTable extends MetricsTable
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

        $this->setTable('school_district_metrics');

        $this->belongsTo('ParentSchoolDistrictMetrics', [
            'className' => 'SchoolDistrictMetrics',
            'foreignKey' => 'parent_id'
        ]);
        $this->hasMany('ChildSchoolDistrictMetrics', [
            'className' => 'SchoolDistrictMetrics',
            'foreignKey' => 'parent_id'
        ]);
        $this->hasMany('SchoolDistrictStatistics', [
            'className' => 'SchoolDistrictStatistics',
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

        $rules->add($rules->existsIn(['parent_id'], 'ParentSchoolDistrictMetrics'));

        return $rules;
    }
}
