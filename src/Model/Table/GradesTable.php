<?php
namespace App\Model\Table;

use App\Model\Entity\Grade;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Grades Model
 *
 * @property RankingsTable|HasMany $Rankings
 * @property SchoolsTable|BelongsToMany $Schools
 *
 * @method Grade get($primaryKey, $options = [])
 * @method Grade newEntity($data = null, array $options = [])
 * @method Grade[] newEntities(array $data, array $options = [])
 * @method Grade|bool save(EntityInterface $entity, $options = [])
 * @method Grade patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method Grade[] patchEntities($entities, array $data, array $options = [])
 * @method Grade findOrCreate($search, callable $callback = null, $options = [])
 */
class GradesTable extends Table
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

        $this->setTable('grades');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->belongsToMany('Rankings', [
            'foreignKey' => 'grade_id',
            'targetForeignKey' => 'school_id',
            'joinTable' => 'rankings_grades'
        ]);
        $this->belongsToMany('Schools', [
            'foreignKey' => 'grade_id',
            'targetForeignKey' => 'school_id',
            'joinTable' => 'schools_grades'
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
