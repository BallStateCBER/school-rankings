<?php
namespace App\Controller\Api;

use App\Controller\AppController;
use App\Model\Context\Context;
use App\Model\Entity\Metric;
use App\Model\Table\MetricsTable;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\MethodNotAllowedException;
use Cake\Http\Exception\NotFoundException;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;

class MetricsController extends AppController
{
    /**
     * Returns a tree-structured list of school metrics
     *
     * @return void
     * @throws \Exception
     */
    public function schools()
    {
        $metricsTable = TableRegistry::getTableLocator()->get('Metrics');
        $metrics = $metricsTable->find('threaded')
            ->where(['context' => 'school'])
            ->toArray();

        if ($this->request->getQuery('only-visible-metrics')) {
            $metrics = Metric::removeNotVisible($metrics);
        }

        $this->set([
            '_serialize' => ['metrics'],
            'metrics' => $metrics
        ]);
    }

    /**
     * Returns a tree-structured list of school district metrics
     *
     * @return void
     * @throws \Exception
     */
    public function districts()
    {
        $metricsTable = TableRegistry::getTableLocator()->get('Metrics');
        $metrics = $metricsTable->find('threaded')
            ->where(['context' => 'district'])
            ->toArray();

        if ($this->request->getQuery('only-visible-metrics')) {
            $metrics = Metric::removeNotVisible($metrics);
        }

        $this->set([
            '_serialize' => ['metrics'],
            'metrics' => $metrics
        ]);
    }

    /**
     * If the operation failed, throw an exception that includes error details
     *
     * @param bool $success Boolean indicating operation success
     * @param Metric $metric Metric entity
     * @return void
     */
    private function throwExceptionOnFail($success, $metric)
    {
        if ($success) {
            return;
        }

        $msg = 'There was an error processing that request.';
        if ($metric->getErrors()) {
            $msg .= "\nDetails...";
            foreach (Hash::flatten($metric->getErrors()) as $field => $errorMsg) {
                $msg .= "\n - $errorMsg ($field)";
            }
        }
        throw new BadRequestException($msg);
    }

    /**
     * Deletes a metric
     *
     * @param string $metricId ID of metric record
     * @return void
     */
    public function delete($metricId)
    {
        if (!$this->request->is('delete')) {
            throw new MethodNotAllowedException('Request is not DELETE');
        }

        /** @var MetricsTable $metricsTable */
        $metricsTable = TableRegistry::getTableLocator()->get('Metrics');

        /** @var Metric $metric */
        $metric = $metricsTable->get($metricId);

        if ($metricsTable->childCount($metric, true) > 0) {
            throw new BadRequestException('Remove all child metrics before removing this metric');
        }

        $result = (bool)$metricsTable->delete($metric);

        $this->throwExceptionOnFail($result, $metric);

        $this->set([
            '_jsonOptions' => JSON_FORCE_OBJECT,
            '_serialize' => ['result'],
            'result' => $result,
        ]);
    }

    /**
     * Adds a metric
     *
     * @return void
     * @throws \Exception
     */
    public function add()
    {
        if (!$this->request->is('post')) {
            throw new MethodNotAllowedException('Request is not POST');
        }

        $metricsTable = TableRegistry::getTableLocator()->get('Metrics');

        /** @var Metric $metric */
        $selectable = $this->request->getData('selectable');
        $metric = $metricsTable->newEntity([
            'context' => $this->request->getData('context'),
            'name' => $this->request->getData('name'),
            'parent_id' => $this->request->getData('parentId'),
            'description' => $this->request->getData('description'),
            'type' => $this->request->getData('type'),
            'selectable' => (bool)$selectable,
            'visible' => (bool)$selectable
        ]);
        $result = (bool)$metricsTable->save($metric);

        $this->throwExceptionOnFail($result, $metric);

        $this->set([
            '_serialize' => ['message', 'result'],
            'message' => $metric->getErrors() ?
                implode("\n", Hash::flatten($metric->getErrors())) :
                'Success',
            'result' => $result,
        ]);
    }

    /**
     * Reparents a metric
     *
     * @return void
     */
    public function reparent()
    {
        if (!$this->request->is('patch')) {
            throw new MethodNotAllowedException('Request is not PATCH');
        }

        $metricId = $this->request->getData('metricId');
        $newParentId = $this->request->getData('newParentId');
        $metricsTable = TableRegistry::getTableLocator()->get('Metrics');

        /** @var Metric $metric */
        $metric = $metricsTable->get($metricId);
        $metric = $metricsTable->patchEntity($metric, [
            'parent_id' => $newParentId,
        ]);
        $result = (bool)$metricsTable->save($metric);

        $this->throwExceptionOnFail($result, $metric);

        $this->set([
            '_jsonOptions' => JSON_FORCE_OBJECT,
            '_serialize' => ['message', 'result'],
            'message' => $metric->getErrors() ?
                implode("\n", Hash::flatten($metric->getErrors())) :
                'Success',
            'result' => $result,
        ]);
    }

    /**
     * Updates a metric
     *
     * @param int $metricId ID of metric record
     * @return void
     */
    public function edit($metricId)
    {
        if (!$this->request->is(['put', 'patch'])) {
            throw new MethodNotAllowedException('Request is not PUT or PATCH');
        }

        $metricsTable = TableRegistry::getTableLocator()->get('Metrics');

        /** @var Metric $metric */
        $metric = $metricsTable->get($metricId);
        foreach (['selectable', 'visible'] as $field) {
            $value = $this->request->getData($field);
            if (isset($value)) {
                $metricsTable->patchEntity($metric, [
                    $field => ($value == 'false') ? false : (bool)$value
                ]);
            }
        }
        foreach (['name', 'description', 'type'] as $field) {
            $value = $this->request->getData($field);
            if (isset($value)) {
                $metricsTable->patchEntity($metric, [$field => $value]);
            }
        }

        $result = (bool)$metricsTable->save($metric);

        $this->throwExceptionOnFail($result, $metric);

        $this->set([
            '_serialize' => ['message', 'result'],
            'message' => $metric->getErrors() ?
                implode("\n", Hash::flatten($metric->getErrors())) :
                'Success',
            'result' => $result,
        ]);
    }

    /**
     * API endpoint that returns a result indicating whether or not the two metrics have associated stats that share
     * the same year and location (and thus would need one or the other to take precedence when merging)
     *
     * @return void
     * @throws \Exception
     */
    public function metricsHaveStatConflict()
    {
        if (!$this->request->is('get')) {
            throw new MethodNotAllowedException('Request is not GET');
        }

        $context = $this->getContext();
        $metricIdA = $this->request->getData('metricIdA');
        $metricIdB = $this->request->getData('metricIdB');
        $metricsTable = TableRegistry::getTableLocator()->get('Metrics');
        foreach (['A', 'B'] as $letter) {
            $metricId = ${'metricId' . $letter};
            if ($metricsTable->exists(['id' => $metricId])) {
                throw new NotFoundException("Metric $letter (#$metricId) not found");
            }
        }

        $hasStatConflict = MetricsTable::mergeHasStatConflict([$metricIdA, $metricIdB], $context);

        $this->set([
            '_serialize' => ['result'],
            'result' => $hasStatConflict
        ]);
    }

    /**
     * Returns the 'context' value from request data
     *
     * @return string|null
     * @throws BadRequestException
     * @throws \Exception
     */
    private function getContext()
    {
        $context = $this->request->getData('context');

        return Context::isValidOrFail($context) ? $context : null;
    }
}
