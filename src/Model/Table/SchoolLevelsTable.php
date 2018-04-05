<?php
namespace App\Model\Table;

use App\Model\Entity\SchoolLevel;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * SchoolLevels Model
 *
 * @property RankingsTable|HasMany $Rankings
 * @property SchoolsTable|BelongsToMany $Schools
 *
 * @method SchoolLevel get($primaryKey, $options = [])
 * @method SchoolLevel newEntity($data = null, array $options = [])
 * @method SchoolLevel[] newEntities(array $data, array $options = [])
 * @method SchoolLevel|bool save(EntityInterface $entity, $options = [])
 * @method SchoolLevel patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method SchoolLevel[] patchEntities($entities, array $data, array $options = [])
 * @method SchoolLevel findOrCreate($search, callable $callback = null, $options = [])
 */
class SchoolLevelsTable extends Table
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

        $this->setTable('school_levels');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->hasMany('Rankings', [
            'foreignKey' => 'school_level_id'
        ]);
        $this->belongsToMany('Schools', [
            'foreignKey' => 'school_level_id',
            'targetForeignKey' => 'school_id',
            'joinTable' => 'schools_school_levels'
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
