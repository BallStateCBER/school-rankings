<?php
namespace App\Model\Table;

use App\Model\Entity\County;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Counties Model
 *
 * @property StatesTable|BelongsTo $States
 * @property CitiesTable|BelongsToMany $Cities
 * @property RankingsTable|BelongsToMany $Rankings
 * @property SchoolDistrictsTable|BelongsToMany $SchoolDistricts
 * @property SchoolsTable|BelongsToMany $Schools
 *
 * @method County get($primaryKey, $options = [])
 * @method County newEntity($data = null, array $options = [])
 * @method County[] newEntities(array $data, array $options = [])
 * @method County|bool save(EntityInterface $entity, $options = [])
 * @method County patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method County[] patchEntities($entities, array $data, array $options = [])
 * @method County findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin TimestampBehavior
 */
class CountiesTable extends Table
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

        $this->setTable('counties');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('States', [
            'foreignKey' => 'state_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsToMany('Cities', [
            'foreignKey' => 'county_id',
            'targetForeignKey' => 'city_id',
            'joinTable' => 'cities_counties',
        ]);
        $this->belongsToMany('Rankings', [
            'foreignKey' => 'county_id',
            'targetForeignKey' => 'ranking_id',
            'joinTable' => 'rankings_counties',
        ]);
        $this->belongsToMany('SchoolDistricts', [
            'foreignKey' => 'county_id',
            'targetForeignKey' => 'school_district_id',
            'joinTable' => 'school_districts_counties',
        ]);
        $this->belongsToMany('Schools', [
            'foreignKey' => 'county_id',
            'targetForeignKey' => 'school_id',
            'joinTable' => 'schools_counties',
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
