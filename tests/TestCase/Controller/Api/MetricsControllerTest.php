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
        'app.school_district_statistics',
        'app.school_metrics',
        'app.school_statistics'
    ];

    /**
     * A list of metric contexts and their corresponding table names
     *
     * @var array
     */
    private $contexts = [
        'school' => 'SchoolMetrics',
        'district' => 'SchoolDistrictMetrics'
    ];

    /**
     * The URL array for reparenting a metric
     *
     * @var array
     */
    private $reparentUrl = [
        'prefix' => 'api',
        'controller' => 'Metrics',
        'action' => 'reparent',
        '_ext' => 'json'
    ];

    /**
     * Tests a successful metric reparenting
     *
     * @param int $metricId ID of metric being moved
     * @param int $newParentId ID of new parent metric
     * @param string $context Metric context name
     * @return void
     * @throws Exception
     */
    private function _testReparentSuccess($metricId, $newParentId, $context)
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json']
        ]);
        $tableName = $this->contexts[$context];
        $table = TableRegistry::get($tableName);
        $metric = $table->get($metricId);
        if ($newParentId == $metric->parent_id) {
            throw new Exception("Invalid $context metric chosen");
        }

        $data = [
            'metricId' => $metricId,
            'context' => $context,
            'newParentId' => $newParentId,
        ];
        $this->patch($this->reparentUrl, $data);
        $this->assertResponseOk();

        $metric = $table->get($metricId);
        $this->assertEquals($newParentId, $metric->parent_id, "$context metric not moved to root");

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

        foreach ($this->contexts as $context => $table) {
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

        foreach ($this->contexts as $context => $table) {
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
        $metricId = 5;
        $newParentId = 1;
        foreach ($this->contexts as $context => $table) {
            $table = TableRegistry::get($table);
            $metric = $table->get($metricId);
            $originalParentId = $metric->parent_id;
            if ($newParentId == $metric->parent_id) {
                throw new Exception("Invalid $context metric chosen");
            }

            $data = [
                'metricId' => $metricId,
                'context' => $context,
                'newParentId' => $newParentId,
            ];
            $this->patch($this->reparentUrl, $data);
            $this->assertResponseError();

            $metric = $table->get($metricId);
            $this->assertEquals(
                $originalParentId,
                $metric->parent_id,
                "$context metric parent changed"
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
        $metricId = 4;
        $newParentId = 999;
        foreach ($this->contexts as $context => $table) {
            $data = [
                'metricId' => $metricId,
                'context' => $context,
                'newParentId' => $newParentId,
            ];
            $this->patch($this->reparentUrl, $data);
            $this->assertResponseError();
        }
    }

    /**
     * Tests successful deleting of a metric
     *
     * @throws Exception
     * @return void
     */
    public function testDeleteSuccess()
    {
        $metricId = 4;
        foreach ($this->contexts as $context => $table) {
            $url = [
                'prefix' => 'api',
                'controller' => 'Metrics',
                'action' => 'delete',
                $context,
                $metricId,
                '_ext' => 'json'
            ];
            $this->delete($url);
            $this->assertResponseOk();
        }
    }

    /**
     * Tests prevention of deleting parent metrics
     *
     * @throws Exception
     * @return void
     */
    public function testDeleteFailHasChildren()
    {
        $metricId = 1;
        foreach ($this->contexts as $context => $table) {
            $url = [
                'prefix' => 'api',
                'controller' => 'Metrics',
                'action' => 'delete',
                $context,
                $metricId,
                '_ext' => 'json'
            ];
            $this->delete($url);
            $this->assertResponseError();
        }
    }
}
