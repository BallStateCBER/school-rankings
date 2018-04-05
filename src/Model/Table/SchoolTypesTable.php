<?php
namespace App\Model\Table;

use App\Model\Entity\SchoolType;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * SchoolTypes Model
 *
 * @property RankingsTable|HasMany $Rankings
 * @property SchoolsTable|HasMany $Schools
 *
 * @method SchoolType get($primaryKey, $options = [])
 * @method SchoolType newEntity($data = null, array $options = [])
 * @method SchoolType[] newEntities(array $data, array $options = [])
 * @method SchoolType|bool save(EntityInterface $entity, $options = [])
 * @method SchoolType patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method SchoolType[] patchEntities($entities, array $data, array $options = [])
 * @method SchoolType findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin TimestampBehavior
 */
class SchoolTypesTable extends Table
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

        $this->setTable('school_types');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('Rankings', [
            'foreignKey' => 'school_type_id'
        ]);
        $this->hasMany('Schools', [
            'foreignKey' => 'school_type_id'
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
}
