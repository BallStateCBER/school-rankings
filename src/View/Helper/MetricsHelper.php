<?php
namespace App\View\Helper;

use Cake\View\Helper;

class MetricsHelper extends Helper
{
    /**
     * Returns a JavaScript string for initializing jsTree
     *
     * @param string $selector CSS selector for jsTree container
     * @param array $metrics Threaded array of SchoolMetric or SchoolDistrictMetric records
     * @return string
     */
    public function initJsTree($selector, $metrics)
    {
        $config = [
            'core' => [
                'data' => $this->getJsTreeData($metrics)
            ],
            'plugins' => [
                'state',
                'wholerow'
            ]
        ];

        return "$('$selector').jstree(" . json_encode($config) . ');';
    }

    /**
     * Takes a threaded array of records and returns an array formatted for jsTree
     *
     * @param array $metrics Threaded array of SchoolMetric or SchoolDistrictMetric records
     * @return array
     */
    private function getJsTreeData($metrics)
    {
        $retval = [];

        foreach ($metrics as $metric) {
            $jTreeData = [
                'text' => $metric['name'],
                'a_attr' => [
                    'selectable' => $metric['selectable'] ? 1 : 0,
                    'type' => $metric['type']
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
