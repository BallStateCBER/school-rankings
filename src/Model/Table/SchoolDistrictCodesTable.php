<?php
namespace App\Model\Table;

use App\Model\Entity\SchoolDistrictCode;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * SchoolDistrictCodes Model
 *
 * @property SchoolDistrictsTable|BelongsTo $SchoolDistricts
 *
 * @method SchoolDistrictCode get($primaryKey, $options = [])
 * @method SchoolDistrictCode newEntity($data = null, array $options = [])
 * @method SchoolDistrictCode[] newEntities(array $data, array $options = [])
 * @method SchoolDistrictCode|bool save(EntityInterface $entity, $options = [])
 * @method SchoolDistrictCode|bool saveOrFail(EntityInterface $entity, $options = [])
 * @method SchoolDistrictCode patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method SchoolDistrictCode[] patchEntities($entities, array $data, array $options = [])
 * @method SchoolDistrictCode findOrCreate($search, callable $callback = null, $options = [])
 */
class SchoolDistrictCodesTable extends Table
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

        $this->setTable('school_district_codes');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('SchoolDistricts', [
            'foreignKey' => 'school_district_id',
            'joinType' => 'INNER',
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
            ->allowEmptyString('id', null, 'create');

        $validator
            ->scalar('code')
            ->maxLength('code', 255)
            ->requirePresence('code', 'create')
            ->notEmptyString('code');

        $validator
            ->scalar('year')
            ->maxLength('year', 4)
            ->requirePresence('year', 'create')
            ->notEmptyString('year');

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
        $rules->add($rules->existsIn(['school_district_id'], 'SchoolDistricts'));
        $rules->add($rules->isUnique(['code']));

        return $rules;
    }
}
