<?php
namespace App\Controller\Api;

use App\Controller\AppController;
use App\Model\Entity\Metric;
use App\Model\Entity\SchoolDistrictMetric;
use App\Model\Entity\SchoolMetric;
use App\Model\Table\MetricsTable;
use App\Model\Table\SchoolDistrictMetricsTable;
use App\Model\Table\SchoolMetricsTable;
use Cake\Network\Exception\BadRequestException;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;

class MetricsController extends AppController
{
    /**
     * Returns a tree-structured list of school metrics
     *
     * @return void
     */
    public function schools()
    {
        $schoolMetricsTable = TableRegistry::get('SchoolMetrics');
        $this->set([
            '_serialize' => ['metrics'],
            'metrics' => $schoolMetricsTable->find('threaded')->toArray()
        ]);
    }

    /**
     * Returns a tree-structured list of school district metrics
     *
     * @return void
     */
    public function districts()
    {
        $districtMetricsTable = TableRegistry::get('SchoolDistrictMetrics');
        $this->set([
            '_serialize' => ['metrics'],
            'metrics' => $districtMetricsTable->find('threaded')->toArray()
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

        $msg = 'There was an error adding that metric.';
        if ($metric->getErrors()) {
            $msg .= ' Details: ' . print_r($metric->getErrors(), true);
        }
        throw new BadRequestException($msg);
    }

    /**
     * Renames a metric
     *
     * @return void
     */
    public function rename()
    {
        if (!$this->request->is('patch')) {
            throw new MethodNotAllowedException('Request is not PATCH');
        }

        $metricId = $this->request->getData('metricId');
        $newName = $this->request->getData('newName');
        $context = $this->request->getData('context');
        $table = MetricsTable::getContextTable($context);

        /** @var SchoolMetric|SchoolDistrictMetric $metric */
        $metric = $table->get($metricId);
        $metric = $table->patchEntity($metric, ['name' => $newName]);
        $result = (bool)$table->save($metric);

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
     * Renames a metric
     *
     * @return void
     */
    public function delete()
    {
        if (!$this->request->is('delete')) {
            throw new MethodNotAllowedException('Request is not DELETE');
        }

        $metricId = $this->request->getData('metricId');
        $context = $this->request->getData('context');

        /** @var SchoolMetricsTable|SchoolDistrictMetricsTable $table */
        $table = MetricsTable::getContextTable($context);

        /** @var SchoolMetric|SchoolDistrictMetric $metric */
        $metric = $table->get($metricId);

        if ($table->childCount($metric, true) > 0) {
            throw new BadRequestException('Remove all child metrics before removing this metric');
        }

        $result = (bool)$table->delete($metric);

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
     */
    public function add()
    {
        if (!$this->request->is('post')) {
            throw new MethodNotAllowedException('Request is not POST');
        }

        $context = $this->request->getData('context');
        $table = MetricsTable::getContextTable($context);

        /** @var SchoolMetric|SchoolDistrictMetric $metric */
        $selectable = $this->request->getData('selectable');
        $metric = $table->newEntity([
            'name' => $this->request->getData('name'),
            'parent_id' => $this->request->getData('parentId'),
            'description' => $this->request->getData('description'),
            'type' => $this->request->getData('type'),
            'selectable' => $selectable == 'false' ? false : (bool)$selectable
        ]);
        $result = (bool)$table->save($metric);

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
        $context = $this->request->getData('context');
        $table = MetricsTable::getContextTable($context);

        /** @var SchoolMetric|SchoolDistrictMetric $metric */
        $metric = $table->get($metricId);
        $metric = $table->patchEntity($metric, [
            'parent_id' => $newParentId,
        ]);
        $result = (bool)$table->save($metric);

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
}
