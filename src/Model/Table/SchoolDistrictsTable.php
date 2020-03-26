<?php
namespace App\Model\Table;

use App\Model\Entity\SchoolDistrict;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * SchoolDistricts Model
 *
 * @property StatisticsTable|HasMany $Statistics
 * @property SchoolsTable|HasMany $Schools
 * @property RankingsTable|BelongsToMany $Rankings
 * @property CitiesTable|BelongsToMany $Cities
 * @property CountiesTable|BelongsToMany $Counties
 * @property StatesTable|BelongsToMany $States
 *
 * @method SchoolDistrict get($primaryKey, $options = [])
 * @method SchoolDistrict newEntity($data = null, array $options = [])
 * @method SchoolDistrict[] newEntities(array $data, array $options = [])
 * @method SchoolDistrict|bool save(EntityInterface $entity, $options = [])
 * @method SchoolDistrict patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method SchoolDistrict[] patchEntities($entities, array $data, array $options = [])
 * @method SchoolDistrict findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin TimestampBehavior
 */
class SchoolDistrictsTable extends Table
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

        $this->setTable('school_districts');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('Statistics', [
            'foreignKey' => 'school_district_id',
        ]);
        $this->hasMany('Schools', [
            'foreignKey' => 'school_district_id',
        ]);
        $this->belongsToMany('Rankings', [
            'foreignKey' => 'school_district_id',
            'targetForeignKey' => 'ranking_id',
            'joinTable' => 'rankings_school_districts',
        ]);
        $this->belongsToMany('Cities', [
            'foreignKey' => 'school_district_id',
            'targetForeignKey' => 'city_id',
            'joinTable' => 'school_districts_cities',
        ]);
        $this->belongsToMany('Counties', [
            'foreignKey' => 'school_district_id',
            'targetForeignKey' => 'county_id',
            'joinTable' => 'school_districts_counties',
        ]);
        $this->belongsToMany('States', [
            'foreignKey' => 'school_district_id',
            'targetForeignKey' => 'state_id',
            'joinTable' => 'school_districts_states',
        ]);
        $this->hasMany('RankingResultsSchoolDistricts', [
            'dependent' => true,
        ]);
        $this->hasMany('SchoolDistrictCodes', [
            'dependent' => true,
            'foreignKey' => 'school_district_id',
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
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('url')
            ->maxLength('url', 255)
            ->allowEmptyString('url');

        $validator
            ->scalar('phone')
            ->maxLength('phone', 30)
            ->allowEmptyString('phone');

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
        return $rules;
    }

    /**
     * Modifies a query by restricting results to those with an association with the provided DoE code
     *
     * @param Query $query Query object
     * @param array $options Options array
     * @return Query
     */
    public function findByCode(Query $query, $options)
    {
        return $query
            ->matching('SchoolDistrictCodes', function (Query $q) use ($options) {
                return $q->where(['SchoolDistrictCodes.code' => $options['code']]);
            });
    }

    /**
     * Returns an array of school district IDOE codes that should be ignored
     *
     * These districts are outliers, e.g. don't have an associated geographic location
     *
     * @return array
     */
    public static function getIgnoredDistrictCodes()
    {
        return [
            '8801', // Community-based Preschools
            '9700', // GQE Retest Site
        ];
    }

    /**
     * Modifies a query by restricting results to open school districts
     *
     * @param Query $query Query object
     * @param array $options Options array
     * @return Query
     */
    public function findOpen(Query $query, $options)
    {
        return $query->where(['SchoolDistricts.closed' => false]);
    }

    /**
     * Returns an array of the names of all of this table's associations
     *
     * @return array
     */
    public function getAssociationNames()
    {
        $associations = [];
        foreach ($this->associations() as $association) {
            $associations[] = $association->getName();
        }

        return $associations;
    }
}
