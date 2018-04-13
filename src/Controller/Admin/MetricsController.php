<?php
namespace App\Controller\Admin;

use App\Controller\AppController;
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
     * Displays the metrics manager, used for adding, editing, and removing metrics
     *
     * @return void
     */
    public function index()
    {
        $schoolMetricsTable = TableRegistry::get('SchoolMetrics');
        $districtMetricsTable = TableRegistry::get('SchoolDistrictMetrics');
        $this->set([
            'metricGroups' => [
                [
                    'header' => 'School Metrics',
                    'context' => 'school',
                    'containerId' => 'school-metrics-tree',
                    'metrics' => $schoolMetricsTable->find('threaded')->toArray()
                ],
                [
                    'header' => 'School District Metrics',
                    'context' => 'district',
                    'containerId' => 'districts-metrics-tree',
                    'metrics' => $districtMetricsTable->find('threaded')->toArray()
                ]
            ],
            'titleForLayout' => 'Metrics'
        ]);
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

        $result = $table->delete($metric);
        $this->set([
            '_jsonOptions' => JSON_FORCE_OBJECT,
            '_serialize' => ['result'],
            'result' => $result,
        ]);
    }
}
