<?php
namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\ORM\TableRegistry;

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
}
