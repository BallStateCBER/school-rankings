<?php
namespace App\Model\Table;

use App\Model\Entity\SchoolStatistic;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\RulesChecker;

/**
 * SchoolStatistics Model
 *
 * @property MetricsTable|BelongsTo $Metrics
 * @property SchoolsTable|BelongsTo $Schools
 *
 * @method SchoolStatistic get($primaryKey, $options = [])
 * @method SchoolStatistic newEntity($data = null, array $options = [])
 * @method SchoolStatistic[] newEntities(array $data, array $options = [])
 * @method SchoolStatistic|bool save(EntityInterface $entity, $options = [])
 * @method SchoolStatistic patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method SchoolStatistic[] patchEntities($entities, array $data, array $options = [])
 * @method SchoolStatistic findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin TimestampBehavior
 */
class SchoolStatisticsTable extends StatisticsTable
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

        $this->setTable('school_statistics');

        $this->belongsTo('SchoolMetrics', [
            'foreignKey' => 'metric_id',
            'joinType' => 'INNER'
        ]);
        $this->belongsTo('Schools', [
            'foreignKey' => 'school_id',
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
        $rules->add($rules->existsIn(['metric_id'], 'SchoolMetrics'));
        $rules->add($rules->existsIn(['school_id'], 'Schools'));

        return $rules;
    }
}
