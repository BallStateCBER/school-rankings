<?php
namespace App\Model\Table;

use App\Model\Entity\City;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Cities Model
 *
 * @property StatesTable|BelongsTo $States
 * @property CountiesTable|BelongsToMany $Counties
 * @property RankingsTable|BelongsToMany $Rankings
 * @property SchoolDistrictsTable|BelongsToMany $SchoolDistricts
 * @property SchoolsTable|BelongsToMany $Schools
 *
 * @method City get($primaryKey, $options = [])
 * @method City newEntity($data = null, array $options = [])
 * @method City[] newEntities(array $data, array $options = [])
 * @method City|bool save(EntityInterface $entity, $options = [])
 * @method City patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method City[] patchEntities($entities, array $data, array $options = [])
 * @method City findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin TimestampBehavior
 */
class CitiesTable extends Table
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

        $this->setTable('cities');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('States', [
            'foreignKey' => 'state_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsToMany('Counties', [
            'foreignKey' => 'city_id',
            'targetForeignKey' => 'county_id',
            'joinTable' => 'cities_counties',
        ]);
        $this->belongsToMany('Rankings', [
            'foreignKey' => 'city_id',
            'targetForeignKey' => 'ranking_id',
            'joinTable' => 'rankings_cities',
        ]);
        $this->belongsToMany('SchoolDistricts', [
            'foreignKey' => 'city_id',
            'targetForeignKey' => 'school_district_id',
            'joinTable' => 'school_districts_cities',
        ]);
        $this->belongsToMany('Schools', [
            'foreignKey' => 'city_id',
            'targetForeignKey' => 'school_id',
            'joinTable' => 'schools_cities',
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
        $rules->add($rules->existsIn(['state_id'], 'States'));

        return $rules;
    }
}
