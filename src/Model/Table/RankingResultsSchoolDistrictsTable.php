<?php
namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * RankingResultsSchoolDistricts Model
 *
 * @property \App\Model\Table\RankingsTable|\Cake\ORM\Association\BelongsTo $Rankings
 * @property \App\Model\Table\SchoolDistrictsTable|\Cake\ORM\Association\BelongsTo $SchoolDistricts
 *
 * @method \App\Model\Entity\RankingResultsSchoolDistrict get($primaryKey, $options = [])
 * @method \App\Model\Entity\RankingResultsSchoolDistrict newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\RankingResultsSchoolDistrict[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\RankingResultsSchoolDistrict|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\RankingResultsSchoolDistrict|bool saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\RankingResultsSchoolDistrict patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\RankingResultsSchoolDistrict[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\RankingResultsSchoolDistrict findOrCreate($search, callable $callback = null, $options = [])
 */
class RankingResultsSchoolDistrictsTable extends Table
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

        $this->setTable('ranking_results_school_districts');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Rankings', [
            'foreignKey' => 'ranking_id',
            'joinType' => 'INNER'
        ]);
        $this->belongsTo('SchoolDistricts', [
            'foreignKey' => 'school_district_id',
            'joinType' => 'INNER'
        ]);
        $this->belongsToMany('Statistics', [
            'foreignKey' => 'ranking_results_school_district_id',
            'targetForeignKey' => 'statistic_id',
            'joinTable' => 'ranking_results_school_districts_statistics'
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
            ->integer('rank')
            ->requirePresence('rank', 'create')
            ->notEmpty('rank');

        $validator
            ->scalar('data_completeness')
            ->maxLength('data_completeness', 10)
            ->requirePresence('data_completeness', 'create')
            ->notEmpty('data_completeness');

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
        $rules->add($rules->existsIn(['ranking_id'], 'Rankings'));
        $rules->add($rules->existsIn(['school_district_id'], 'SchoolDistricts'));

        return $rules;
    }
}
