<?php
namespace App\Model\Table;

use App\Model\Entity\Ranking;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Rankings Model
 *
 * @property UsersTable|BelongsTo $Users
 * @property FormulasTable|BelongsTo $Formulas
 * @property SchoolTypesTable|BelongsTo $SchoolTypes
 * @property SchoolLevelsTable|BelongsTo $SchoolLevels
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
            'foreignKey' => 'user_id'
        ]);
        $this->belongsTo('Formulas', [
            'foreignKey' => 'formula_id',
            'joinType' => 'INNER'
        ]);
        $this->belongsTo('SchoolTypes', [
            'foreignKey' => 'school_type_id'
        ]);
        $this->belongsTo('SchoolLevels', [
            'foreignKey' => 'school_level_id'
        ]);
        $this->belongsToMany('Cities', [
            'foreignKey' => 'ranking_id',
            'targetForeignKey' => 'city_id',
            'joinTable' => 'rankings_cities'
        ]);
        $this->belongsToMany('Counties', [
            'foreignKey' => 'ranking_id',
            'targetForeignKey' => 'county_id',
            'joinTable' => 'rankings_counties'
        ]);
        $this->belongsToMany('Ranges', [
            'foreignKey' => 'ranking_id',
            'targetForeignKey' => 'range_id',
            'joinTable' => 'rankings_ranges'
        ]);
        $this->belongsToMany('SchoolDistricts', [
            'foreignKey' => 'ranking_id',
            'targetForeignKey' => 'school_district_id',
            'joinTable' => 'rankings_school_districts'
        ]);
        $this->belongsToMany('States', [
            'foreignKey' => 'ranking_id',
            'targetForeignKey' => 'state_id',
            'joinTable' => 'rankings_states'
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
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        $rules->add($rules->existsIn(['user_id'], 'Users'));
        $rules->add($rules->existsIn(['formula_id'], 'Formulas'));
        $rules->add($rules->existsIn(['school_type_id'], 'SchoolTypes'));
        $rules->add($rules->existsIn(['school_level_id'], 'SchoolLevels'));

        return $rules;
    }
}