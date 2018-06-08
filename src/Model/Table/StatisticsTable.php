<?php
namespace App\Model\Table;

use App\Model\Entity\Statistic;
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\RulesChecker;
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
            ->integer('metric_id');

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
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param RulesChecker $rules The rules object to be modified.
     * @return RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        parent::buildRules($rules);

        $rules->add(function ($entity, $options) use ($rules) {
            /** @var Statistic $entity */
            $context = $entity->getCurrentContext();
            $metricTableName = $context == 'school' ? 'SchoolMetricsTable' : 'SchoolDistrictMetricsTable';
            $rule = $rules->existsIn(['metric_id'], $metricTableName);

            return $rule($entity, $options);
        });

        return $rules;
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
                return TableRegistry::getTableLocator()->get('SchoolStatistics');
            case 'district':
                return TableRegistry::getTableLocator()->get('SchoolDistrictStatistics');
            default:
                throw new InternalErrorException('Statistics context "' . $context . '" not recognized');
        }
    }

    /**
     * Returns the value of a specified statistic
     *
     * @param string $context Either 'school' or 'district'
     * @param int $metricId SchoolMetric ID or SchoolDistrictMetric ID
     * @param int $locationId School ID or SchoolDistrict ID
     * @param int $year Year to look up data for
     * @return Statistic|null
     * @throws InternalErrorException
     */
    public static function getStatistic($context, $metricId, $locationId, $year)
    {
        $locationField = self::getLocationFieldName($context);

        /** @var Statistic $statistic */
        $statistic = self::getContextTable($context)->find()
            ->select(['id', 'value'])
            ->where([
                'metric_id' => $metricId,
                'year' => $year,
                $locationField => $locationId
            ])
            ->orderDesc('created')
            ->first();

        return $statistic;
    }

    /**
     * Returns the location field name corresponding to the specified context
     *
     * @param string $context Either school or district
     * @return string
     * @throws InternalErrorException
     */
    public static function getLocationFieldName($context)
    {
        switch ($context) {
            case 'school':
                return 'school_id';
            case 'district':
                return 'school_district_id';
            default:
                throw new InternalErrorException('Statistics context "' . $context . '" not recognized');
        }
    }

    /**
     * Converts numeric values to integers or floats rounded to 5 decimal places
     *
     * Applies this conversion when accessing $stat->value or before saving statistics to the database
     *
     * @param string|float|int $value Statistic value
     * @return string|float|int
     */
    protected function _getValue($value)
    {
        // String
        if (!is_numeric($value)) {
            return $value;
        }

        // Integer
        if (is_int($value) || strpos($value, '.') === false) {
            return (int)$value;
        }

        // Float
        return round((float)$value, 5);
    }
}
