<?php
namespace App\View\Helper;

use Cake\View\Helper;

class MetricsHelper extends Helper
{
    /**
     * Takes a threaded array of records and returns an array formatted for jsTree
     *
     * @param array $metrics Threaded array of Metric records
     * @return array
     */
    public function getJsTreeData($metrics)
    {
        $retval = [];

        foreach ($metrics as $metric) {
            $jTreeData = [
                'text' => $metric['name'],
                'a_attr' => [
                    'selectable' => $metric['selectable'] ? 1 : 0,
                    'type' => $metric['type']
                ],
                'data' => [
                    'selectable' => (bool)$metric['selectable'],
                    'type' => $metric['type'],
                    'metricId' => $metric['id']
                ]
            ];
            if ($metric['children']) {
                $jTreeData['children'] = $this->getJsTreeData($metric['children']);
            }
            $retval[] = $jTreeData;
        }

        return $retval;
    }
}
