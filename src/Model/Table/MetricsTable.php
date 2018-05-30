<?php
namespace App\Model\Table;

use App\Model\Entity\Metric;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\EntityInterface;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use Exception;

/**
 * SchoolMetrics Model
 *
 * @method Metric get($primaryKey, $options = [])
 * @method Metric newEntity($data = null, array $options = [])
 * @method Metric[] newEntities(array $data, array $options = [])
 * @method Metric|bool save(EntityInterface $entity, $options = [])
 * @method Metric patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method Metric[] patchEntities($entities, array $data, array $options = [])
 * @method Metric findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 * @mixin \Cake\ORM\Behavior\TreeBehavior
 */
class MetricsTable extends Table
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

        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Tree');
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

        $table = $this;
        $validator
            ->integer('parent_id')
            ->allowEmpty('parent_id')
            ->add('parent_id', 'unique', [
                'rule' => function ($value, $context) use ($table) {
                    $metricId = $context['data']['id'] ?? null;
                    $parentId = $value;

                    if (isset($context['data']['name'])) {
                        $name = $context['data']['name'];
                    } elseif ($metricId) {
                        $metric = $table->get($metricId);
                        $name = $metric->name;
                    } else {
                        throw new BadRequestException('Either metric ID or name are required');
                    }

                    return !$table->hasNameConflict($metricId, $parentId, $name);
                },
                'message' => 'Another metric with the same parent has the same name'
            ]);

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmpty('name')
            ->add('name', 'unique', [
                'rule' => function ($value, $context) use ($table) {
                    $metricId = $context['data']['id'] ?? null;
                    if ($metricId) {
                        $parentId = $context['data']['parent_id'] ?? $this->get($metricId)->parent_id;
                    } else {
                        $parentId = null;
                    }

                    return !$table->hasNameConflict($metricId, $parentId, $value);
                },
                'message' => 'Another metric with the same parent has the same name'
            ]);

        $validator
            ->scalar('description')
            ->allowEmpty('description');

        $validator
            ->scalar('type')
            ->maxLength('type', 255)
            ->requirePresence('type', 'create')
            ->notEmpty('type')
            ->inList('type', ['numeric', 'boolean']);

        $validator
            ->boolean('selectable')
            ->requirePresence('selectable', 'create')
            ->notEmpty('selectable');

        $validator
            ->boolean('visible')
            ->requirePresence('visible', 'create')
            ->notEmpty('visible');

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
        $rules->addDelete(function ($entity) {
            return $this->childCount($entity, true) === 0;
        }, 'cantDeleteParentMetric', [
            'message' => 'Cannot delete a metric with child-records',
            'errorField' => 'children'
        ]);

        $rules->addUpdate(function ($entity) {
            $context = $this->getCurrentContext();

            return !$this->hasIncompatibleStatistics($context, $entity->type, $entity->id);
        }, 'cantChangeMetricContext', [
            'message' => 'Cannot change metric type. Existing statistics are incompatible with new type.',
            'errorField' => 'statistics'
        ]);

        return $rules;
    }

    /**
     * Returns either 'school' or 'district' depending on what the current subclass is
     *
     * @return string
     * @throws InternalErrorException
     */
    public function getCurrentContext()
    {
        $className = explode('\\', get_class($this));

        switch (end($className)) {
            case 'SchoolMetricsTable':
                return 'school';
            case 'SchoolDistrictMetricsTable':
                return 'district';
        }

        throw new InternalErrorException('Can\'t get context for ' . get_class($this) . ' class');
    }

    /**
     * Returns TRUE if the metric is found in the database
     *
     * @param string $context Either 'school' or 'district'
     * @param int $metricId SchoolMetric ID or SchoolDistrictMetric ID
     * @return bool
     * @throws InternalErrorException
     */
    public static function recordExists($context, $metricId)
    {
        $count = self::getContextTable($context)->find()
            ->where(['id' => $metricId])
            ->count();

        return $count > 0;
    }

    /**
     * Adds a metric record to the appropriate table
     *
     * @param string $context Either 'school' or 'district'
     * @param string $metricName The name of the new metric
     * @param string $type Either 'numeric' or 'boolean'
     * @return \Cake\Datasource\EntityInterface
     * @throws Exception
     */
    public static function addRecord($context, $metricName, $type = 'numeric')
    {
        $table = self::getContextTable($context);
        $metric = $table->newEntity([
            'name' => $metricName,
            'description' => '',
            'selectable' => true,
            'visible' => true,
            'type' => $type
        ]);

        if ($table->save($metric)) {
            return $metric;
        }

        $msg = 'Cannot add metric ' . $metricName . "\nDetails: " . print_r($metric->getErrors(), true);
        throw new Exception($msg);
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
                return TableRegistry::getTableLocator()->get('SchoolMetrics');
            case 'district':
                return TableRegistry::getTableLocator()->get('SchoolDistrictMetrics');
            default:
                throw new InternalErrorException('Metric context "' . $context . '" not recognized');
        }
    }

    /**
     * Returns true if the specified metric does (or would have) the same name as another metric with the same parent_id
     *
     * @param int $metricId SchoolMetric ID or SchoolDistrictMetric ID
     * @param int|null $parentId Metric parent_id
     * @param string $name Metric name being validated
     * @return bool
     */
    public function hasNameConflict($metricId, $parentId, $name)
    {
        $conditions = ['name' => $name];
        if ($parentId) {
            $conditions['parent_id'] = $parentId;
        } else {
            $conditions[] = function (QueryExpression $exp) {
                return $exp->isNull('parent_id');
            };
        }
        if ($metricId) {
            $conditions[] = function (QueryExpression $exp) use ($metricId) {
                return $exp->notEq('id', $metricId);
            };
        }

        return $this->find()
            ->where($conditions)
            ->count() > 0;
    }

    /**
     * Returns TRUE if the specified metric should NOT be changed to the provided type because existing statistics
     * have incompatible values
     *
     * @param string $context Either 'school' or 'district'
     * @param string $metricType Either 'numeric' or 'boolean'
     * @param int $metricId Metric record ID
     * @return bool
     */
    public function hasIncompatibleStatistics($context, $metricType, $metricId)
    {
        // All statistic values (including 1 and 0) can be of the "numeric" type
        if ($metricType == 'numeric') {
            return false;
        } elseif ($metricType != 'boolean') {
            throw new InternalErrorException('Unrecognized metric type: ' . $metricType);
        }

        // Boolean values can only be 1 and 0
        return StatisticsTable::getContextTable($context)->find()
            ->where([
                'metric_id' => $metricId,
                function (QueryExpression $exp) {
                    return $exp->notEq('value', '1');
                },
                function (QueryExpression $exp) {
                    return $exp->notEq('value', '0');
                }
            ])
            ->count() > 0;
    }

    /**
     * Returns TRUE if the specified metrics have overlapping statistics
     *
     * "Overlapping" meaning statistics that represent the same metric, location, and date
     *
     * @param int[] $metricIds IDs of metric records to be merged
     * @return bool
     */
    public function mergeHasStatConflict($metricIds)
    {
        if (count($metricIds) != 2) {
            $msg = 'Merge must be between 2 metrics. Cannot merge ' . count($metricIds) . '.';
            throw new InternalErrorException($msg);
        }

        $context = $this->getCurrentContext();
        $statisticsTable = StatisticsTable::getContextTable($context);

        // Collect the years of all statistics associated with each metric
        $years = [];
        foreach ($metricIds as $metricId) {
            $results = $statisticsTable->find()
                ->select(['year'])
                ->distinct(['year'])
                ->where(['metric_id' => $metricId])
                ->all()
                ->extract('{n}.year');

            // If either metric has no associated statistics, then no conflict is possible
            if (empty($results)) {
                return false;
            }

            $years[] = $results;
        }

        // Check for year overlaps
        $sharedYears = [];
        foreach ($years[0] as $yearA) {
            foreach ($years[1] as $yearB) {
                if ($yearA == $yearB) {
                    $sharedYears[] = $yearA;
                }
            }
        }
        if (! $sharedYears) {
            return false;
        }
        $sharedYears = array_unique($sharedYears);

        // Collect locations in those years
        $locations = [];
        $locationField = $context == 'school' ? 'school_id' : 'school_district_id';
        foreach ($metricIds as $metricId) {
            $locations[] = $statisticsTable->find()
                ->select([$locationField])
                ->distinct([$locationField])
                ->where([
                    'metric_id' => $metricId,
                ])
                ->where(function (QueryExpression $exp) use ($sharedYears) {
                    return $exp->in('year', $sharedYears);
                })
                ->all()
                ->extract("{n}.$locationField");
        }

        // Return true if there is an overlapping location in the range of overlapping years
        foreach ($locations[0] as $locationA) {
            foreach ($locations[1] as $locationB) {
                if ($locationA == $locationB) {
                    return true;
                }
            }
        }

        return false;
    }
}
