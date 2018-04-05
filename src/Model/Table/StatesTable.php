<?php
namespace App\Model\Table;

use App\Model\Entity\State;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * States Model
 *
 * @property CitiesTable|HasMany $Cities
 * @property CountiesTable|HasMany $Counties
 * @property RankingsTable|BelongsToMany $Rankings
 * @property SchoolDistrictsTable|BelongsToMany $SchoolDistricts
 * @property SchoolsTable|BelongsToMany $Schools
 *
 * @method State get($primaryKey, $options = [])
 * @method State newEntity($data = null, array $options = [])
 * @method State[] newEntities(array $data, array $options = [])
 * @method State|bool save(EntityInterface $entity, $options = [])
 * @method State patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method State[] patchEntities($entities, array $data, array $options = [])
 * @method State findOrCreate($search, callable $callback = null, $options = [])
 */
class StatesTable extends Table
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

        $this->setTable('states');
        $this->setDisplayField('name');

        $this->hasMany('Cities', [
            'foreignKey' => 'state_id'
        ]);
        $this->hasMany('Counties', [
            'foreignKey' => 'state_id'
        ]);
        $this->belongsToMany('Rankings', [
            'foreignKey' => 'state_id',
            'targetForeignKey' => 'ranking_id',
            'joinTable' => 'rankings_states'
        ]);
        $this->belongsToMany('SchoolDistricts', [
            'foreignKey' => 'state_id',
            'targetForeignKey' => 'school_district_id',
            'joinTable' => 'school_districts_states'
        ]);
        $this->belongsToMany('Schools', [
            'foreignKey' => 'state_id',
            'targetForeignKey' => 'school_id',
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
            ->requirePresence('id', 'create')
            ->notEmpty('id');

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmpty('name');

        $validator
            ->scalar('abbreviation')
            ->maxLength('abbreviation', 255)
            ->requirePresence('abbreviation', 'create')
            ->notEmpty('abbreviation');

        return $validator;
    }
}
