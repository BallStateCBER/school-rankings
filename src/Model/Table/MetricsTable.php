<?php
namespace App\Model\Table;

use App\Model\Context\Context;
use App\Model\Entity\Metric;
use ArrayObject;
use Cake\Cache\Cache;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use Exception;

/**
 * Metrics Model
 *
 * @property \App\Model\Table\MetricsTable|\Cake\ORM\Association\BelongsTo $ParentMetrics
 * @property \App\Model\Table\MetricsTable|\Cake\ORM\Association\HasMany $ChildMetrics
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

    const PERCENT_KEYWORDS = ['%', 'percent', 'rate'];

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

        $this->hasMany('Criteria', [
            'foreignKey' => 'metric_id'
        ]);

        $this->hasMany('Statistics', [
            'className' => 'Statistics',
            'foreignKey' => 'metric_id'
        ])->setDependent(true);

        $this->belongsTo('ParentMetrics', [
            'className' => 'Metrics',
            'foreignKey' => 'parent_id'
        ]);

        $this->hasMany('ChildMetrics', [
            'className' => 'Metrics',
            'foreignKey' => 'parent_id'
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
            ->scalar('context')
            ->requirePresence('context', 'create')
            ->notEmpty('context')
            ->add('context', 'isValidContext', [
                'rule' => function ($value) {
                    return Context::isValid($value);
                },
                'message' => 'The title is not valid'
            ]);

        $validator
            ->integer('parent_id')
            ->allowEmpty('parent_id')
            ->add('parent_id', 'isValidParent', [
                'rule' => 'validateParent',
                'message' => 'Another metric with the same parent has the same name',
                'provider' => 'table'
            ]);

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmpty('name')
            ->add('name', 'unique', [
                'rule' => 'validateName',
                'message' => 'Another metric with the same parent has the same name',
                'provider' => 'table'
            ]);

        $validator
            ->scalar('description')
            ->allowEmpty('description');

        $validator
            ->scalar('type')
            ->maxLength('type', 255)
            ->requirePresence('type', 'create')
            ->notEmpty('type')
            ->inList('type', $this->getMetricTypes());

        $validator
            ->boolean('selectable')
            ->requirePresence('selectable', 'create')
            ->notEmpty('selectable');

        $validator
            ->boolean('visible')
            ->requirePresence('visible', 'create')
            ->notEmpty('visible');

        $validator
            ->boolean('is_percent')
            ->requirePresence('is_percent', 'create')
            ->allowEmpty('is_percent');

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
        $rules->add($rules->existsIn(['parent_id'], 'ParentMetrics'));

        $rules->addDelete(function ($entity) {
            return $this->childCount($entity, true) === 0;
        }, 'cantDeleteParentMetric', [
            'message' => 'Cannot delete a metric with child-records',
            'errorField' => 'children'
        ]);

        $rules->addUpdate(function ($entity) {
            return !$this->hasIncompatibleStatistics($entity->type, $entity->id);
        }, 'cantChangeMetricContext', [
            'message' => 'Cannot change metric type. Existing statistics are incompatible with new type.',
            'errorField' => 'statistics'
        ]);

        return $rules;
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
    public function addRecord($context, $metricName, $type = 'numeric')
    {
        $metric = $this->newEntity([
            'context' => $context,
            'name' => $metricName,
            'description' => '',
            'selectable' => true,
            'visible' => true,
            'type' => $type,
            'is_percent' => null
        ]);

        if ($this->save($metric)) {
            return $metric;
        }

        $msg = 'Cannot add metric ' . $metricName . "\nDetails: " . print_r($metric->getErrors(), true);
        throw new Exception($msg);
    }

    /**
     * Returns true if the specified metric does (or would have) the same name as another metric with the same parent_id
     *
     * @param int $metricId ID of a metric record
     * @param int|null $parentId Metric parent_id
     * @param string $name Metric name being validated
     * @param string $metricContext Either 'school' or 'district'
     * @return bool
     */
    public function hasNameConflict($metricId, $parentId, $name, $metricContext)
    {
        $conditions = ['name' => $name];
        if ($parentId) {
            $conditions['parent_id'] = $parentId;
        } else {
            $conditions[] = function (QueryExpression $exp) {
                return $exp->isNull('parent_id');
            };
            $conditions['context'] = $metricContext;
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
     * @param string $metricType Either 'numeric' or 'boolean'
     * @param int $metricId Metric record ID
     * @return bool
     */
    public function hasIncompatibleStatistics($metricType, $metricId)
    {
        // All statistic values (including the boolean 1/0 and letter grades A-F) can be of the "numeric" type
        if ($metricType == 'numeric') {
            return false;
        } elseif ($metricType != 'boolean') {
            throw new InternalErrorException('Unrecognized metric type: ' . $metricType);
        }

        // Boolean values can only be 1 and 0
        return TableRegistry::getTableLocator()
            ->get('Statistics')
            ->find()
            ->where([
                'metric_id' => $metricId,
                function (QueryExpression $exp) {
                    return $exp->notIn('value', ['0', '1']);
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
     * @param string|null $context Either 'school' or 'district'
     * @return bool
     */
    public static function mergeHasStatConflict($metricIds, $context = null)
    {
        if (count($metricIds) != 2) {
            $msg = 'Merge must be between 2 metrics. Cannot merge ' . count($metricIds) . '.';
            throw new InternalErrorException($msg);
        }

        $statisticsTable = TableRegistry::getTableLocator()->get('Statistics');

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
        $locationField = Context::getLocationField($context);
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

    /**
     * Returns an array of metrics that traverses from a root-level
     * metric through its descendants to the selected metric
     *
     * @param int $metricId ID of a metric record
     * @return array
     * @throws InternalErrorException
     */
    public function getMetricTreePath($metricId)
    {
        if (get_class($this) == 'MetricsTable') {
            throw new InternalErrorException(
                'getMetricTreePath() must be called on a context-specific metric table object'
            );
        }
        $options = ['for' => $metricId];
        $results = $this->find('path', $options)
            ->select(['id', 'name'])
            ->enableHydration(false)
            ->toArray();

        return $results;
    }

    /**
     * Sets the scope for tree operations
     *
     * @param string $context 'school' or 'district'
     * @throws Exception
     * @return void
     */
    public function setScope($context)
    {
        if (Context::isValidOrFail($context)) {
            $this->behaviors()->Tree->setConfig('scope', ['context' => $context]);
        }
    }

    /**
     * Returns whether or not the metric specified in $validationContext can have $parentId
     *
     * @param int $parentId ID of parent metric
     * @param array $validationContext Validation context array
     * @return bool
     */
    public function validateParent($parentId, array $validationContext)
    {
        $metricId = $validationContext['data']['id'] ?? null;

        if (isset($validationContext['data']['name'])) {
            $name = $validationContext['data']['name'];
        } elseif ($metricId) {
            $name = $this->get($metricId)->name;
        } else {
            throw new BadRequestException('Either metric ID or name are required');
        }

        if (isset($validationContext['data']['context'])) {
            $metricContext = $validationContext['data']['context'];
        } elseif ($metricId) {
            $metricContext = $this->get($metricId)->context;
        } else {
            throw new BadRequestException('Metric context not provided');
        }

        return !$this->hasNameConflict($metricId, $parentId, $name, $metricContext);
    }

    /**
     * Returns whether or not the metric specified in $context can have $name
     *
     * @param string $name Metric name
     * @param array $validationContext Validation context array
     * @return bool
     */
    public function validateName($name, array $validationContext)
    {
        $metricId = $validationContext['data']['id'] ?? null;
        if ($metricId) {
            $parentId = $validationContext['data']['parent_id'] ?? $this->get($metricId)->parent_id;
        } else {
            $parentId = null;
        }

        if (isset($validationContext['data']['context'])) {
            $metricContext = $validationContext['data']['context'];
        } elseif ($metricId) {
            $metricContext = $this->get($metricId)->context;
        } else {
            throw new BadRequestException('Metric context not provided');
        }

        return !$this->hasNameConflict($metricId, $parentId, $name, $metricContext);
    }

    /**
     * Returns an array of valid metric types
     *
     * @return array
     */
    public function getMetricTypes()
    {
        return ['numeric', 'boolean'];
    }

    /**
     * Returns TRUE if the metric with the specified ID or name should have its statistics formatted as percents,
     * e.g. "95.5%"
     *
     * @param int|string $metric ID of metric record or record name
     * @return bool
     */
    public function isPercentMetric($metric)
    {
        if (is_string($metric)) {
            $metricName = $metric;
            foreach (self::PERCENT_KEYWORDS as $keyword) {
                if (stripos($metricName, $keyword) !== false) {
                    return true;
                }
            }

            return false;
        }

        if (is_int($metric)) {
            $metricId = $metric;

            /** @var Metric $metric */
            $metric = $this->find()
                ->select(['name'])
                ->where(['id' => $metricId])
                ->firstOrFail();

            return $this->isPercentMetric($metric->name);
        }

        throw new InternalErrorException(
            'Metric parameter must be string or int. Provided: ' . print_r($metric, true)
        );
    }

    /**
     * afterSave callback
     *
     * @param Event $event Event object
     * @param EntityInterface $entity Metric entity
     * @param ArrayObject $options Save options
     * @return void
     */
    public function afterSave(Event $event, EntityInterface $entity, ArrayObject $options)
    {
        $metricId = $entity->id;
        Cache::delete("metric-$metricId-isPercent");
    }

    /**
     * Custom finder for retrieving percent-style metrics
     *
     * @param Query $query Cake ORM query
     * @return Query
     */
    public function findPercents(Query $query)
    {
        $conditions = [];
        foreach (self::PERCENT_KEYWORDS as $keyword) {
            $keyword = str_replace('%', '\\%', $keyword);
            $conditions[] = function (QueryExpression $exp) use ($keyword) {
                return $exp->like('name', "%$keyword%");
            };
        }

        return $query->where(['OR' => $conditions]);
    }
}
