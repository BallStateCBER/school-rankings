<?php
namespace App\Model\Table;

use App\Model\Entity\RankingsSchoolType;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * RankingsSchoolTypes Model
 *
 * @property RankingsTable|BelongsTo $Rankings
 * @property SchoolTypesTable|BelongsTo $SchoolTypes
 *
 * @method RankingsSchoolType get($primaryKey, $options = [])
 * @method RankingsSchoolType newEntity($data = null, array $options = [])
 * @method RankingsSchoolType[] newEntities(array $data, array $options = [])
 * @method RankingsSchoolType|bool save(EntityInterface $entity, $options = [])
 * @method RankingsSchoolType|bool saveOrFail(EntityInterface $entity, $options = [])
 * @method RankingsSchoolType patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method RankingsSchoolType[] patchEntities($entities, array $data, array $options = [])
 * @method RankingsSchoolType findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class RankingsSchoolTypesTable extends Table
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

        $this->setTable('rankings_school_types');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Rankings', [
            'foreignKey' => 'ranking_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('SchoolTypes', [
            'foreignKey' => 'school_type_id',
            'joinType' => 'INNER',
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
            ->allowEmptyString('id', null, 'create');

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
        $rules->add($rules->existsIn(['ranking_id'], 'Rankings'));
        $rules->add($rules->existsIn(['school_type_id'], 'SchoolTypes'));

        return $rules;
    }
}
