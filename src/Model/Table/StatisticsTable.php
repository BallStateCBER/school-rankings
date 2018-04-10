<?php
namespace App\Model\Table;

use Cake\Network\Exception\InternalErrorException;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
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

    /**
     * Returns a SchoolMetricsTable or SchoolDistrictMetricsTable
     *
     * @param string $context Either 'school' or 'district'
     * @return \Cake\ORM\Table
     * @throws InternalErrorException
     */
    public static function getContextTable($context)
    {
        switch ($context) {
            case 'school':
                return TableRegistry::get('SchoolStatistics');
            case 'district':
                return TableRegistry::get('SchoolDistrictStatistics');
            default:
                throw new InternalErrorException('Statistics context "' .  $context . '" not recognized');
        }
    }

    /**
     * Returns the value of a specified statistic
     *
     * @param string $context Either 'school' or 'district'
     * @param int $metricId SchoolMetric ID or SchoolDistrictMetric ID
     * @param int $locationId School ID or SchoolDistrict ID
     * @param int $year Year to look up data for
     * @return string|null
     * @throws InternalErrorException
     */
    public static function getValue($context, $metricId, $locationId, $year)
    {
        $conditions = [
            'metric_id' => $metricId,
            'year' => $year
        ];

        switch ($context) {
            case 'school':
                $conditions['school_id'] = $locationId;
                break;
            case 'district':
                $conditions['school_district_id'] = $locationId;
                break;
            default:
                throw new InternalErrorException('Statistics context "' .  $context . '" not recognized');
        }

        $result = self::getContextTable($context)->find()
            ->select(['value'])
            ->where($conditions)
            ->orderDesc('created')
            ->enableHydration(false)
            ->first();

        return $result ? $result['value'] : null;
    }
}
