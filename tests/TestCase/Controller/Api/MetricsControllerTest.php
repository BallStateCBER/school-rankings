<?php
namespace App\Test\TestCase\Controller\Api;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestCase;
use Exception;

/**
 * MetricsControllerTest class
 */
class MetricsControllerTest extends IntegrationTestCase
{
    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.school_district_metrics',
        'app.school_metrics'
    ];

    /**
     * Returns an array of metric context information
     *
     * @return array
     */
    private function getContexts()
    {
        return [
            [
                'name' => 'school',
                'table' => 'SchoolMetrics'
            ],
            [
                'name' => 'district',
                'table' => 'SchoolDistrictMetrics'
            ]
        ];
    }

    /**
     * Tests a successful metric reparenting
     *
     * @param int $metricId ID of metric being moved
     * @param int $newParentId ID of new parent metric
     * @param array $context Metric context information
     * @return void
     * @throws Exception
     */
    private function _testReparentSuccess($metricId, $newParentId, $context)
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json']
        ]);
        $url = [
            'prefix' => 'api',
            'controller' => 'Metrics',
            'action' => 'reparent',
            '_ext' => 'json'
        ];
        $table = TableRegistry::get($context['table']);
        $metric = $table->get($metricId);
        if ($newParentId == $metric->parent_id) {
            throw new Exception("Invalid {$context['name']} metric chosen");
        }

        $data = [
            'metricId' => $metricId,
            'context' => $context['name'],
            'newParentId' => $newParentId,
        ];
        $this->patch($url, $data);
        $this->assertResponseOk();

        $metric = $table->get($metricId);
        $this->assertEquals($newParentId, $metric->parent_id, "{$context['name']} metric not moved to root");

        $expected = json_encode([
            'message' => 'Success',
            'result' => true
        ], JSON_PRETTY_PRINT);
        $this->assertEquals($expected, (string)$this->_response->getBody());
    }

    /**
     * Tests that a metric can be moved TO the root of the metrics tree
     *
     * @return void
     * @throws Exception
     */
    public function testReparentToRootSuccess()
    {
        $metricId = 2;
        $newParentId = null;

        foreach ($this->getContexts() as $context) {
            $this->_testReparentSuccess($metricId, $newParentId, $context);
        }
    }

    /**
     * Tests that a metric can be moved FROM the root of the metrics tree
     *
     * @return void
     * @throws Exception
     */
    public function testReparentFromRootSuccess()
    {
        $metricId = 4;
        $newParentId = 1;

        foreach ($this->getContexts() as $context) {
            $this->_testReparentSuccess($metricId, $newParentId, $context);
        }
    }

    /**
     * Tests that moving a metric in a way that creates two siblings with the same name fails
     *
     * @throws Exception
     * @return void
     */
    public function testReparentFailNonunique()
    {
        $url = [
            'prefix' => 'api',
            'controller' => 'Metrics',
            'action' => 'reparent',
            '_ext' => 'json'
        ];
        $metricId = 5;
        $newParentId = 1;
        foreach ($this->getContexts() as $context) {
            $table = TableRegistry::get($context['table']);
            $metric = $table->get($metricId);
            $originalParentId = $metric->parent_id;
            if ($newParentId == $metric->parent_id) {
                throw new Exception("Invalid {$context['name']} metric chosen");
            }

            $data = [
                'metricId' => $metricId,
                'context' => $context['name'],
                'newParentId' => $newParentId,
            ];
            $this->patch($url, $data);
            $this->assertResponseError();

            $metric = $table->get($metricId);
            $this->assertEquals(
                $originalParentId,
                $metric->parent_id,
                "{$context['name']} metric parent changed"
            );
        }
    }

    /**
     * Tests moving a metric under a nonexistent parent
     *
     * @throws Exception
     * @return void
     */
    public function testReparentFailUnknownParent()
    {
        $url = [
            'prefix' => 'api',
            'controller' => 'Metrics',
            'action' => 'reparent',
            '_ext' => 'json'
        ];
        $metricId = 4;
        $newParentId = 999;
        foreach ($this->getContexts() as $context) {
            $data = [
                'metricId' => $metricId,
                'context' => $context['name'],
                'newParentId' => $newParentId,
            ];
            $this->patch($url, $data);
            $this->assertResponseError();
        }
    }
}
