<?php
namespace App\Model\Table;

use App\Model\Entity\SchoolCode;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * SchoolCodes Model
 *
 * @property SchoolsTable|BelongsTo $Schools
 *
 * @method SchoolCode get($primaryKey, $options = [])
 * @method SchoolCode newEntity($data = null, array $options = [])
 * @method SchoolCode[] newEntities(array $data, array $options = [])
 * @method SchoolCode|bool save(EntityInterface $entity, $options = [])
 * @method SchoolCode|bool saveOrFail(EntityInterface $entity, $options = [])
 * @method SchoolCode patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method SchoolCode[] patchEntities($entities, array $data, array $options = [])
 * @method SchoolCode findOrCreate($search, callable $callback = null, $options = [])
 */
class SchoolCodesTable extends Table
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

        $this->setTable('school_codes');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Schools', [
            'foreignKey' => 'school_id',
            'joinType' => 'INNER'
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param Validator $validator Validator instance.
     * @return Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->integer('id')
            ->allowEmpty('id', 'create');

        $validator
            ->scalar('code')
            ->maxLength('code', 255)
            ->requirePresence('code', 'create')
            ->notEmpty('code');

        $validator
            ->scalar('year')
            ->maxLength('year', 4)
            ->requirePresence('year', 'create')
            ->notEmpty('year');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param RulesChecker $rules The rules object to be modified.
     * @return RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        $rules->add($rules->existsIn(['school_id'], 'Schools'));
        $rules->add($rules->isUnique(['code']));

        return $rules;
    }
}
