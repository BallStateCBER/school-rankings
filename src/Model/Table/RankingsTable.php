<?php
namespace App\Model\Table;

use App\Model\Entity\Ranking;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Utility\Security;
use Cake\Validation\Validator;

/**
 * Rankings Model
 *
 * @property UsersTable|BelongsTo $Users
 * @property FormulasTable|BelongsTo $Formulas
 * @property SchoolTypesTable|BelongsTo $SchoolTypes
 * @property GradesTable|BelongsTo $Grades
 * @property CitiesTable|BelongsToMany $Cities
 * @property CountiesTable|BelongsToMany $Counties
 * @property RangesTable|BelongsToMany $Ranges
 * @property SchoolDistrictsTable|BelongsToMany $SchoolDistricts
 * @property StatesTable|BelongsToMany $States
 *
 * @method Ranking get($primaryKey, $options = [])
 * @method Ranking newEntity($data = null, array $options = [])
 * @method Ranking[] newEntities(array $data, array $options = [])
 * @method Ranking|bool save(EntityInterface $entity, $options = [])
 * @method Ranking patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method Ranking[] patchEntities($entities, array $data, array $options = [])
 * @method Ranking findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin TimestampBehavior
 */
class RankingsTable extends Table
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

        $this->setTable('rankings');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
        ]);
        $this->belongsTo('Formulas', [
            'foreignKey' => 'formula_id',
            'joinType' => 'INNER',
        ]);

        // If a ranking has no associated grades, it's assumed that schools teaching ALL grades are being ranked
        $this->belongsToMany('Grades', [
            'foreignKey' => 'ranking_id',
            'targetForeignKey' => 'grade_id',
            'joinTable' => 'rankings_grades',
        ]);

        $this->belongsToMany('Cities', [
            'foreignKey' => 'ranking_id',
            'targetForeignKey' => 'city_id',
            'joinTable' => 'rankings_cities',
        ]);
        $this->belongsToMany('Counties', [
            'foreignKey' => 'ranking_id',
            'targetForeignKey' => 'county_id',
            'joinTable' => 'rankings_counties',
        ]);
        $this->belongsToMany('Ranges', [
            'foreignKey' => 'ranking_id',
            'targetForeignKey' => 'range_id',
            'joinTable' => 'rankings_ranges',
        ]);
        $this->belongsToMany('SchoolDistricts', [
            'foreignKey' => 'ranking_id',
            'targetForeignKey' => 'school_district_id',
            'joinTable' => 'rankings_school_districts',
        ]);
        $this->belongsToMany('SchoolTypes', [
            'foreignKey' => 'ranking_id',
            'targetForeignKey' => 'school_type_id',
            'joinTable' => 'rankings_school_types',
        ]);
        $this->belongsToMany('States', [
            'foreignKey' => 'ranking_id',
            'targetForeignKey' => 'state_id',
            'joinTable' => 'rankings_states',
        ]);
        $this->hasMany('ResultsSchools', [
            'className' => 'RankingResultsSchools',
            'foreignKey' => 'ranking_id',
            'joinTable' => 'ranking_results_schools',
        ]);
        $this->hasMany('ResultsDistricts', [
            'className' => 'RankingResultsSchoolDistricts',
            'foreignKey' => 'ranking_id',
            'joinTable' => 'ranking_results_school_districts',
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
            ->boolean('for_school_districts')
            ->requirePresence('for_school_districts', 'create')
            ->notEmpty('for_school_districts');

        $validator
            ->scalar('hash')
            ->maxLength('hash', 255)
            ->requirePresence('hash', 'create')
            ->notEmpty('hash');

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
        $rules->add($rules->existsIn(['user_id'], 'Users'));
        $rules->add($rules->existsIn(['formula_id'], 'Formulas'));
        $rules->add($rules->existsIn(['school_type_id'], 'SchoolTypes'));

        return $rules;
    }

    /**
     * Returns a random string to be used as an identifier for a ranking
     *
     * @return string
     */
    public static function generateHash()
    {
        return Security::randomString(8);
    }

    /**
     * Returns an array of contain queries for use in RankingsController::get()
     *
     * @return array
     */
    private function getContainQueries()
    {
        $containStatistics = function (Query $q) {
            return $q->select([
                'id',
                'year',
                'value',
                'metric_id',
                'school_id',
                'school_district_id',
            ]);
        };
        $containCriteria = function (Query $q) {
            return $q
                ->select(['id', 'formula_id', 'weight'])
                ->contain([
                    'Metrics' => function (Query $q) {
                        return $q->select(['id', 'name']);
                    },
                ]);
        };
        $containSchools = function (Query $q) {
            return $q
                ->select([
                    'id',
                    'name',
                    'address',
                    'url',
                    'phone',
                ])
                ->contain([
                    'Grades' => function (Query $q) {
                        return $q
                            ->select(['id', 'name'])
                            ->orderAsc('Grades.id');
                    },
                    'SchoolTypes' => function (Query $q) {
                        return $q->select(['id', 'name']);
                    },
                ]);
        };
        $containDistricts = function (Query $q) {
            return $q
                ->select([
                    'id',
                    'name',
                    'url',
                    'phone',
                ]);
        };
        $containFormulas = function (Query $q) use ($containCriteria) {
            return $q
                ->select(['id'])
                ->contain([
                    'Criteria' => $containCriteria,
                ]);
        };
        $containResultsSchools = function (Query $q) use ($containSchools, $containStatistics) {
            return $q->contain([
                'Schools' => $containSchools,
                'Statistics' => $containStatistics,
            ]);
        };
        $containResultsDistricts = function (Query $q) use ($containDistricts, $containStatistics) {
            return $q->contain([
                'SchoolDistricts' => $containDistricts,
                'Statistics' => $containStatistics,
            ]);
        };

        return [
            'formulas' => $containFormulas,
            'resultsSchools' => $containResultsSchools,
            'resultsDistricts' => $containResultsDistricts,
        ];
    }

    /**
     * Custom finder for ->find('forApiGetEndpoint')
     *
     * @param \Cake\ORM\Query $query Query object
     * @param array $options Finder options
     * @return \Cake\ORM\Query
     */
    public function findForApiGetEndpoint(Query $query, $options)
    {
        $containQueries = $this->getContainQueries();

        $query
            ->select(['id', 'hash'])
            ->contain([
                'Counties',
                'Formulas' => $containQueries['formulas'],
                'Grades',
                'ResultsDistricts' => $containQueries['resultsDistricts'],
                'ResultsSchools' => $containQueries['resultsSchools'],
                'SchoolTypes',
            ]);

        return $query;
    }
}
