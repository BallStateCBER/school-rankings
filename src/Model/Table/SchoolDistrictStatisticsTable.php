<?php
namespace App\Model\Table;

use App\Model\Entity\SchoolDistrictStatistic;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * SchoolDistrictStatistics Model
 *
 * @property MetricsTable|BelongsTo $Metrics
 * @property SchoolDistrictsTable|BelongsTo $SchoolDistricts
 *
 * @method SchoolDistrictStatistic get($primaryKey, $options = [])
 * @method SchoolDistrictStatistic newEntity($data = null, array $options = [])
 * @method SchoolDistrictStatistic[] newEntities(array $data, array $options = [])
 * @method SchoolDistrictStatistic|bool save(EntityInterface $entity, $options = [])
 * @method SchoolDistrictStatistic patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method SchoolDistrictStatistic[] patchEntities($entities, array $data, array $options = [])
 * @method SchoolDistrictStatistic findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin TimestampBehavior
 */
class SchoolDistrictStatisticsTable extends Table
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

        $this->setTable('school_district_statistics');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Metrics', [
            'foreignKey' => 'metric_id',
            'joinType' => 'INNER'
        ]);
        $this->belongsTo('SchoolDistricts', [
            'foreignKey' => 'school_district_id',
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
            ->scalar('value')
            ->maxLength('value', 255)
            ->requirePresence('value', 'create')
            ->notEmpty('value');

        $validator
            ->integer('year')
            ->requirePresence('year', 'create')
            ->notEmpty('year');

        $validator
            ->boolean('contiguous')
            ->requirePresence('contiguous', 'create')
            ->notEmpty('contiguous');

        $validator
            ->scalar('file')
            ->maxLength('file', 255)
            ->requirePresence('file', 'create')
            ->notEmpty('file');

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
        $rules->add($rules->existsIn(['school_district_id'], 'SchoolDistricts'));

        return $rules;
    }
}
