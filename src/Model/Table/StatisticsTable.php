<?php
namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class StatisticsTable extends Table
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

        $this->addBehavior('Timestamp');

        $this->belongsTo('Metrics', [
            'foreignKey' => 'metric_id',
            'joinType' => 'INNER'
        ]);
        $this->belongsTo('SchoolDistricts', [
            'foreignKey' => 'school_district_id',
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
            ->scalar('value')
            ->maxLength('value', 255)
            ->requirePresence('value', 'create')
            ->notEmpty('value');

        $validator
            ->integer('year')
            ->requirePresence('year', 'create')
            ->notEmpty('year');

        $validator
            ->boolean('contiguous')
            ->requirePresence('contiguous', 'create')
            ->notEmpty('contiguous');

        $validator
            ->scalar('file')
            ->maxLength('file', 255)
            ->requirePresence('file', 'create')
            ->notEmpty('file');

        return $validator;
    }
}
