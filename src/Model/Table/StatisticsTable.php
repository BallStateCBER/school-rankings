<?php
namespace App\Model\Table;

use App\Model\Entity\Statistic;
use Cake\Datasource\Exception\RecordNotFoundException;
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
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Schools', [
            'foreignKey' => 'school_id',
            'joinType' => 'INNER',
        ]);
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
            ->allowEmpty('id', 'create');

        $validator
            ->integer('metric_id')
            ->requirePresence('metric_id', 'create')
            ->notEmpty('metric_id');

        $validator
            ->integer('school_id')
            ->allowEmpty('school_id');

        $validator
            ->integer('school_district_id')
            ->allowEmpty('school_district_id');

        $validator
            ->scalar('value')
            ->maxLength('value', 255)
            ->requirePresence('value', 'create')
            ->notEmpty('value')
            ->add('role', 'validValue', [
                'rule' => 'isValidValue',
                'message' => 'Stat values must be numbers, percents, or capital letter grades',
                'provider' => 'table',
            ]);

        $validator
            ->integer('year')
            ->requirePresence('year', 'create')
            ->notEmpty('year')
            ->add('role', 'validYear', [
                'rule' => 'isValidYear',
                'message' => 'Invalid year',
                'provider' => 'table',
            ]);

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

        // Metric must exist
        $rules->add(
            function ($entity, $options) use ($rules) {
                $rule = $rules->existsIn(['metric_id'], 'Metrics');

                return $rule($entity, $options);
            },
            'metricExists',
            [
                'errorField' => 'metric_id',
                'message' => 'Metric not found',
            ]
        );

        // Either school or district must be specified
        $rules->add(
            function ($entity) {
                return $entity->school_id || $entity->school_district_id;
            },
            'subjectSpecified',
            [
                'errorField' => 'subject',
                'message' => 'School/district not specified',
            ]
        );

        // School must exist
        $rules->add(
            function ($entity, $options) use ($rules) {
                if (!$entity->school_id) {
                    return true;
                }
                $rule = $rules->existsIn(['school_id'], 'Schools');

                return $rule($entity, $options);
            },
            'schoolExists',
            [
                'errorField' => 'school_id',
                'message' => 'School not found',
            ]
        );

        // District must exist
        $rules->add(
            function ($entity, $options) use ($rules) {
                if (!$entity->school_district_id) {
                    return true;
                }

                $rule = $rules->existsIn(['school_district_id'], 'SchoolDistricts');

                return $rule($entity, $options);
            },
            'districtExists',
            [
                'errorField' => 'school_district_id',
                'message' => 'District not found',
            ]
        );

        // Percentages must be between 0 and 100 (or "0%" and "100%")
        /** @var MetricsTable $metricsTable */
        $metricsTable = TableRegistry::getTableLocator()->get('Metrics');
        $rules->add(
            function ($entity) use ($metricsTable, $rules) {
                /** @var Statistic $entity */
                try {
                    if (isset($entity->metric->is_percent)) {
                        $isPercent = $entity->metric->is_percent;
                    } elseif (isset($entity->metric_id)) {
                        $isPercent = $metricsTable->isPercentMetric($entity->metric_id);
                    } else {
                        throw new InternalErrorException(
                            'Statistic cannot be added or updated without its metric or metric_id property'
                        );
                    }

                // The associated metric does not exist, which is a violation that has been checked by an earlier rule
                } catch (RecordNotFoundException $e) {
                    return true;
                }

                if (!$isPercent) {
                    return true;
                }

                $value = (float)str_replace('%', '', $entity->value);

                return $value >= 0 && $value <= 100;
            },
            'percentageInBounds',
            [
                'errorField' => 'value',
                'message' => 'Percentage values must be between 0 and 100%',
            ]
        );

        return $rules;
    }

    /**
     * Returns the value of a specified statistic
     *
     * @param string $context Either 'school' or 'district'
     * @param int $metricId Metric ID
     * @param int $locationId School ID or SchoolDistrict ID
     * @param int $year Year to look up data for
     * @return Statistic|null
     * @throws InternalErrorException
     */
    public function getStatistic($context, $metricId, $locationId, $year)
    {
        $locationField = self::getLocationFieldName($context);

        /** @var Statistic $statistic */
        $statistic = $this->find()
            ->select([
                'id',
                'value',
                'school_id',
                'school_district_id',
                'metric_id',
            ])
            ->where([
                'metric_id' => $metricId,
                'year' => $year,
                $locationField => $locationId,
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
     * Returns whether or not the statistic value is valid
     *
     * @param string $value Value of statistic
     * @return bool
     */
    public function isValidValue($value)
    {
        // Grades
        if (in_array($value, ['A', 'B', 'C', 'D', 'F'])) {
            return true;
        }

        // Percentages
        if (Statistic::isPercentValue($value)) {
            $percentAmount = (float)substr($value, 0, -1);
            if ($percentAmount > 100) {
                return 'Percent values cannot exceed 100%';
            }
            if ($percentAmount < 0) {
                return 'Percent values cannot be negative';
            }

            return true;
        }

        // Numbers
        if (is_numeric($value)) {
            return true;
        }

        return false;
    }

    /**
     * Returns whether or not $year appears to be a year within a sensible range
     *
     * @param int|string $year Year value
     * @return bool|string
     */
    public function validateYear($year)
    {
        if (!is_numeric($year) || is_float($year)) {
            return "$year is not a valid year";
        }

        $earliestYear = date('Y') - 100;
        $latestYear = date('Y') + 1;
        if ((int)$year < $earliestYear || (int)$year > $latestYear) {
            return "$year is out of the acceptable range of years ($earliestYear-$latestYear)";
        }

        return true;
    }

    /**
     * Returns the most recent year for which statistics are found
     *
     * @return int
     */
    public function getMostRecentYear()
    {
        /** @var Statistic $stat */
        $stat = $this->find()
            ->select(['year'])
            ->orderDesc('year')
            ->first();

        return $stat->year;
    }
}
