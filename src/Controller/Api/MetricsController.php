<?php
namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\ORM\TableRegistry;

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
        $districtMetricsTable = TableRegistry::get('SchoolDistrictMetrics');;
        $this->set([
            '_serialize' => ['metrics'],
            'metrics' => $districtMetricsTable->find('threaded')->toArray()
        ]);
    }
}
