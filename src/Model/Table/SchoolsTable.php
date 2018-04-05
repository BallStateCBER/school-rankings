<?php
namespace App\Model\Table;

use App\Model\Entity\School;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Schools Model
 *
 * @property SchoolDistrictsTable|BelongsTo $SchoolDistricts
 * @property SchoolTypesTable|BelongsTo $SchoolTypes
 * @property SchoolStatisticsTable|HasMany $SchoolStatistics
 * @property CitiesTable|BelongsToMany $Cities
 * @property CountiesTable|BelongsToMany $Counties
 * @property SchoolLevelsTable|BelongsToMany $SchoolLevels
 * @property StatesTable|BelongsToMany $States
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
            'foreignKey' => 'school_district_id'
        ]);
        $this->belongsTo('SchoolTypes', [
            'foreignKey' => 'school_type_id'
        ]);
        $this->hasMany('SchoolStatistics', [
            'foreignKey' => 'school_id'
        ]);
        $this->belongsToMany('Cities', [
            'foreignKey' => 'school_id',
            'targetForeignKey' => 'city_id',
            'joinTable' => 'schools_cities'
        ]);
        $this->belongsToMany('Counties', [
            'foreignKey' => 'school_id',
            'targetForeignKey' => 'county_id',
            'joinTable' => 'schools_counties'
        ]);
        $this->belongsToMany('SchoolLevels', [
            'foreignKey' => 'school_id',
            'targetForeignKey' => 'school_level_id',
            'joinTable' => 'schools_school_levels'
        ]);
        $this->belongsToMany('States', [
            'foreignKey' => 'school_id',
            'targetForeignKey' => 'state_id',
            'joinTable' => 'schools_states'
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
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmpty('name');

        $validator
            ->scalar('address')
            ->requirePresence('address', 'create')
            ->notEmpty('address');

        $validator
            ->scalar('url')
            ->maxLength('url', 255)
            ->requirePresence('url', 'create')
            ->notEmpty('url');

        $validator
            ->scalar('code')
            ->maxLength('code', 255)
            ->requirePresence('code', 'create')
            ->notEmpty('code');

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
        $rules->add($rules->existsIn(['school_district_id'], 'SchoolDistricts'));
        $rules->add($rules->existsIn(['school_type_id'], 'SchoolTypes'));

        return $rules;
    }
}
