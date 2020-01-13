<?php
namespace App\Model\Table;

use App\Model\Entity\RankingResultsSchool;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * RankingResultsSchools Model
 *
 * @property RankingsTable|BelongsTo $Rankings
 * @property SchoolsTable|BelongsTo $Schools
 *
 * @method RankingResultsSchool get($primaryKey, $options = [])
 * @method RankingResultsSchool newEntity($data = null, array $options = [])
 * @method RankingResultsSchool[] newEntities(array $data, array $options = [])
 * @method RankingResultsSchool|bool save(EntityInterface $entity, $options = [])
 * @method RankingResultsSchool|bool saveOrFail(EntityInterface $entity, $options = [])
 * @method RankingResultsSchool patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method RankingResultsSchool[] patchEntities($entities, array $data, array $options = [])
 * @method RankingResultsSchool findOrCreate($search, callable $callback = null, $options = [])
 */
class RankingResultsSchoolsTable extends Table
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

        $this->setTable('ranking_results_schools');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Rankings', [
            'foreignKey' => 'ranking_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Schools', [
            'foreignKey' => 'school_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsToMany('Statistics', [
            'foreignKey' => 'ranking_results_school_id',
            'targetForeignKey' => 'statistic_id',
            'joinTable' => 'ranking_results_schools_statistics',
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
            ->allowEmptyString('id', null, 'create');

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
        $rules->add($rules->existsIn(['school_id'], 'Schools'));

        return $rules;
    }
}
