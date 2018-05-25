<?php
namespace App\Model\Table;

use App\Model\Entity\Metric;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\EntityInterface;
use Cake\Network\Exception\BadRequestException;
use Cake\Network\Exception\InternalErrorException;
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
                    $parentId = $context['data']['parent_id'] ?? $this->get($metricId)->parent_id;

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
            'message' => 'Cannot delete a metric with child-records'
        ]);

        return $rules;
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
                return TableRegistry::get('SchoolMetrics');
            case 'district':
                return TableRegistry::get('SchoolDistrictMetrics');
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
}
