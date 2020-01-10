<?php
namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * RankingsSchoolTypes Model
 *
 * @property \App\Model\Table\RankingsTable|\Cake\ORM\Association\BelongsTo $Rankings
 * @property \App\Model\Table\SchoolTypesTable|\Cake\ORM\Association\BelongsTo $SchoolTypes
 *
 * @method \App\Model\Entity\RankingsSchoolType get($primaryKey, $options = [])
 * @method \App\Model\Entity\RankingsSchoolType newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\RankingsSchoolType[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\RankingsSchoolType|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\RankingsSchoolType|bool saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\RankingsSchoolType patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\RankingsSchoolType[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\RankingsSchoolType findOrCreate($search, callable $callback = null, $options = [])
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
            ->allowEmpty('id', 'create');

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
