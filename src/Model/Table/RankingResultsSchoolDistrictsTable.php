<?php
namespace App\Model\Table;

use App\Model\Entity\RankingResultsSchoolDistrict;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * RankingResultsSchoolDistricts Model
 *
 * @property RankingsTable|BelongsTo $Rankings
 * @property SchoolDistrictsTable|BelongsTo $SchoolDistricts
 *
 * @method RankingResultsSchoolDistrict get($primaryKey, $options = [])
 * @method RankingResultsSchoolDistrict newEntity($data = null, array $options = [])
 * @method RankingResultsSchoolDistrict[] newEntities(array $data, array $options = [])
 * @method RankingResultsSchoolDistrict|bool save(EntityInterface $entity, $options = [])
 * @method RankingResultsSchoolDistrict|bool saveOrFail(EntityInterface $entity, $options = [])
 * @method RankingResultsSchoolDistrict patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method RankingResultsSchoolDistrict[] patchEntities($entities, array $data, array $options = [])
 * @method RankingResultsSchoolDistrict findOrCreate($search, callable $callback = null, $options = [])
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
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('SchoolDistricts', [
            'foreignKey' => 'school_district_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsToMany('Statistics', [
            'foreignKey' => 'ranking_results_school_district_id',
            'targetForeignKey' => 'statistic_id',
            'joinTable' => 'ranking_results_school_districts_statistics',
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param Validator $validator Validator instance.
     * @return Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->integer('rank')
            ->requirePresence('rank', 'create')
            ->greaterThan('rank', 0);

        $validator
            ->scalar('data_completeness')
            ->maxLength('data_completeness', 10)
            ->requirePresence('data_completeness', 'create')
            ->notEmptyString('data_completeness');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param RulesChecker $rules The rules object to be modified.
     * @return RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        $rules->add($rules->existsIn(['ranking_id'], 'Rankings'));
        $rules->add($rules->existsIn(['school_district_id'], 'SchoolDistricts'));

        return $rules;
    }
}
