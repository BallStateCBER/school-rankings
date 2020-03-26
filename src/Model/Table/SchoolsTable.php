<?php
namespace App\Model\Table;

use App\Model\Entity\School;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Schools Model
 *
 * @property CitiesTable|BelongsToMany $Cities
 * @property CountiesTable|BelongsToMany $Counties
 * @property GradesTable|BelongsToMany $Grades
 * @property SchoolCodesTable|BelongsToMany $SchoolCodes
 * @property SchoolDistrictsTable|BelongsTo $SchoolDistricts
 * @property SchoolTypesTable|BelongsTo $SchoolTypes
 * @property StatesTable|BelongsToMany $States
 * @property StatisticsTable|HasMany $Statistics
 *
 * @method School get($primaryKey, $options = [])
 * @method School newEntity($data = null, array $options = [])
 * @method School[] newEntities(array $data, array $options = [])
 * @method School|bool save(EntityInterface $entity, $options = [])
 * @method School patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method School[] patchEntities($entities, array $data, array $options = [])
 * @method School findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin TimestampBehavior
 */
class SchoolsTable extends Table
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

        $this->setTable('schools');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('SchoolDistricts', [
            'foreignKey' => 'school_district_id',
        ]);
        $this->belongsTo('SchoolTypes', [
            'foreignKey' => 'school_type_id',
        ]);
        $this->hasMany('Statistics', [
            'foreignKey' => 'school_id',
        ]);
        $this->belongsToMany('Cities', [
            'foreignKey' => 'school_id',
            'targetForeignKey' => 'city_id',
            'joinTable' => 'schools_cities',
        ]);
        $this->belongsToMany('Counties', [
            'foreignKey' => 'school_id',
            'targetForeignKey' => 'county_id',
            'joinTable' => 'schools_counties',
        ]);
        $this->belongsToMany('Grades', [
            'foreignKey' => 'school_id',
            'targetForeignKey' => 'grade_id',
            'joinTable' => 'schools_grades',
        ]);
        $this->belongsToMany('States', [
            'foreignKey' => 'school_id',
            'targetForeignKey' => 'state_id',
            'joinTable' => 'schools_states',
        ]);
        $this->hasMany('RankingResultsSchools', [
            'dependent' => true,
        ]);
        $this->hasMany('SchoolCodes', [
            'dependent' => true,
            'foreignKey' => 'school_id',
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
            ->scalar('address')
            ->allowEmptyString('address');

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
        $rules->add($rules->existsIn(['school_district_id'], 'SchoolDistricts'));
        $rules->add($rules->existsIn(['school_type_id'], 'SchoolTypes'));

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
            ->matching('SchoolCodes', function (Query $q) use ($options) {
                return $q->where(['SchoolCodes.code' => $options['code']]);
            });
    }

    /**
     * Modifies a query by restricting results to open schools
     *
     * @param Query $query Query object
     * @param array $options Options array
     * @return Query
     */
    public function findOpen(Query $query, $options)
    {
        return $query->where(['Schools.closed' => false]);
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
