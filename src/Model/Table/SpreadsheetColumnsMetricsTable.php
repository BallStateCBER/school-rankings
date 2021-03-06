<?php
namespace App\Model\Table;

use App\Import\ImportFile;
use App\Model\Entity\SpreadsheetColumnsMetric;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\EntityInterface;
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Exception;

/**
 * SpreadsheetColumnsMetrics Model
 *
 * @method SpreadsheetColumnsMetric get($primaryKey, $options = [])
 * @method SpreadsheetColumnsMetric newEntity($data = null, array $options = [])
 * @method SpreadsheetColumnsMetric[] newEntities(array $data, array $options = [])
 * @method SpreadsheetColumnsMetric|bool save(EntityInterface $entity, $options = [])
 * @method SpreadsheetColumnsMetric patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method SpreadsheetColumnsMetric[] patchEntities($entities, array $data, array $options = [])
 * @method SpreadsheetColumnsMetric findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin TimestampBehavior
 */
class SpreadsheetColumnsMetricsTable extends Table
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

        $this->setTable('spreadsheet_columns_metrics');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Metrics', [
            'foreignKey' => 'metric_id',
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
            ->scalar('year')
            ->maxLength('year', 255)
            ->requirePresence('year', 'create')
            ->notEmptyString('year');

        $validator
            ->scalar('filename')
            ->maxLength('filename', 255)
            ->requirePresence('filename', 'create')
            ->notEmptyString('filename');

        $validator
            ->scalar('context')
            ->maxLength('context', 255)
            ->requirePresence('context', 'create')
            ->notEmptyString('context');

        $validator
            ->scalar('worksheet')
            ->maxLength('worksheet', 255)
            ->requirePresence('worksheet', 'create')
            ->notEmptyString('worksheet');

        $validator
            ->scalar('group_name')
            ->maxLength('group_name', 255)
            ->allowEmptyString('group_name');

        $validator
            ->scalar('column_name')
            ->maxLength('column_name', 255)
            ->requirePresence('column_name', 'create')
            ->notEmptyString('column_name');

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
        $rules->add(function ($entity, $options) use ($rules) {
            return $rules->existsIn(['metric_id'], 'Metrics')($entity, $options);
        }, 'metricExists');

        return $rules;
    }

    /**
     * Returns the corresponding Metric ID, or NULL if no result is found
     *
     * @param array $conditions Array of parameters for find()->where()
     * @return int|null
     * @throws InternalErrorException
     */
    public function getMetricId($conditions)
    {
        $expectedKeys = [
            'year',
            'filename',
            'context',
            'worksheet',
            'group_name',
            'column_name',
        ];
        foreach ($expectedKeys as $key) {
            if (!array_key_exists($key, $conditions)) {
                throw new InternalErrorException('Cannot find metric. Missing parameter: ' . $key);
            }
            if ($conditions[$key] === null) {
                unset($conditions[$key]);
                $conditions[] = function (QueryExpression $exp) use ($key) {
                    return $exp->isNull($key);
                };
            }
        }
        if (count($conditions) != count($expectedKeys)) {
            throw new InternalErrorException('Cannot find metric. Invalid parameters given.');
        }

        /** @var SpreadsheetColumnsMetric $result */
        $result = $this->find()
            ->select(['metric_id'])
            ->where($conditions)
            ->orderDesc('created')
            ->first();

        return $result ? $result->metric_id : null;
    }

    /**
     * Adds a record to the database table
     *
     * @param ImportFile $importFile Current ImportFile object
     * @param array $colInfo Array of info about the name and group of the current column
     * @param int $metricId Metric ID
     * @return bool
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws Exception
     */
    public function add($importFile, $colInfo, $metricId)
    {
        $record = $this->newEntity([
            'year' => $importFile->getYear(),
            'filename' => $importFile->getFilename(),
            'context' => $importFile->getContext(),
            'worksheet' => $importFile->activeWorksheet,
            'group_name' => $colInfo['group'],
            'column_name' => $colInfo['name'],
            'metric_id' => $metricId,
        ]);

        if ($this->save($record)) {
            return true;
        }

        $msg = "Cannot add column->metric shortcut to spreadsheet_columns_metrics.\nDetails: " .
            print_r($record, true);
        throw new Exception($msg);
    }
}
