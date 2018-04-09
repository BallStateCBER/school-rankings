<?php
namespace App\Model\Table;

use App\Model\Entity\SchoolDistrictStatistic;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\RulesChecker;

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
class SchoolDistrictStatisticsTable extends StatisticsTable
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

        $this->belongsTo('SchoolDistrictMetrics', [
            'foreignKey' => 'metric_id',
            'joinType' => 'INNER'
        ]);
        $this->belongsTo('SchoolDistricts', [
            'foreignKey' => 'school_district_id',
            'joinType' => 'INNER'
        ]);
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
        $rules->add($rules->existsIn(['metric_id'], 'SchoolDistrictMetrics'));
        $rules->add($rules->existsIn(['school_district_id'], 'SchoolDistricts'));

        return $rules;
    }
}
