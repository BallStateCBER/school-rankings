<?php
namespace App\Test\TestCase\Controller\Api;

use App\Model\Entity\Metric;
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
     * The URL array for creating a metric
     *
     * @var array
     */
    private $addUrl = [
        'prefix' => 'api',
        'controller' => 'Metrics',
        'action' => 'add',
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

    /**
     * Tests successful creation of a metric
     *
     * @throws Exception
     * @return void
     */
    public function testCreateSuccess()
    {
        $metricName = 'New metric';
        $data = [
            'name' => $metricName,
            'description' => 'Metric description',
            'selectable' => 'true',
            'parentId' => 1,
            'type' => 'numeric'
        ];
        foreach ($this->contexts as $context => $tableName) {
            $data['context'] = $context;
            $this->post($this->addUrl, $data);
            $this->assertResponseSuccess();

            /** @var Metric $record */
            $record = TableRegistry::get($tableName)->find()
                ->where(['name' => $metricName])
                ->first();
            $className = $context == 'school' ? 'SchoolMetric' : 'SchoolDistrictMetric';
            $this->assertInstanceOf('App\\Model\\Entity\\' . $className, $record);

            $this->assertEquals($data['name'], $record->name);
            $this->assertEquals($data['description'], $record->description);
            $this->assertEquals(true, $record->selectable);
            $this->assertEquals($data['type'], $record->type);
            $this->assertEquals($data['parentId'], $record->parent_id);
        }
    }

    /**
     * Tests failing to create a metric with an invalid parent
     *
     * @throws Exception
     * @return void
     */
    public function testCreateFailInvalidParent()
    {
        $data = [
            'name' => 'New metric',
            'description' => 'Metric description',
            'selectable' => 'true',
            'parentId' => 999,
            'type' => 'numeric'
        ];
        foreach ($this->contexts as $context => $tableName) {
            $data['context'] = $context;
            $this->post($this->addUrl, $data);
            $this->assertResponseError();
        }
    }

    /**
     * Tests failing to create a metric with missing required data
     *
     * @throws Exception
     * @return void
     */
    public function testCreateFailMissingData()
    {
        $data = [
            'name' => 'New metric',
            'description' => 'Metric description',
            'selectable' => 'true',
            'parentId' => 1,
            'type' => 'numeric'
        ];
        $requiredData = [
            'name',
            'type',
            'context'
        ];
        foreach ($this->contexts as $context => $tableName) {
            $data['context'] = $context;
            foreach ($requiredData as $dataKey) {
                $dataSubset = $data;
                unset($dataSubset[$dataKey]);
                $this->post($this->addUrl, $dataSubset);
                $this->assertResponseError('Error expected with missing ' . $dataKey);
            }
        }
    }

    /**
     * Tests failing to create a metric with a name conflict
     *
     * @throws Exception
     * @return void
     */
    public function testCreateFailNameConflict()
    {
        $data = [
            'name' => 'Identical name',
            'description' => 'Metric description',
            'selectable' => 'true',
            'parentId' => 1,
            'type' => 'numeric'
        ];
        foreach ($this->contexts as $context => $tableName) {
            $data['context'] = $context;
            $this->post($this->addUrl, $data);
            $this->assertResponseError();
        }
    }

    /**
     * Returns the URL of an 'edit' API endpoint
     *
     * @param int $metricId Metric record ID
     * @return array
     */
    private function getEditUrl($metricId)
    {
        return [
            'prefix' => 'api',
            'controller' => 'Metrics',
            'action' => 'edit',
            $metricId,
            '_ext' => 'json'
        ];
    }

    /**
     * Tests successfully editing a metric
     *
     * @throws Exception
     * @return void
     */
    public function testEditSuccess()
    {
        $metricId = 1;
        $data = [
            'name' => 'Renamed',
            'description' => 'New description',
            'type' => 'boolean',
            'selectable' => false
        ];
        foreach ($this->contexts as $context => $tableName) {
            // Ensure that fixture data is different from $data
            $table = TableRegistry::get($tableName);
            $originalMetric = $table->get($metricId);
            foreach ($data as $field => $value) {
                if ($originalMetric->$field == $value) {
                    $msg = "Invalid $context metric chosen. Metric #$metricId's $field value is already $value";
                    throw new Exception($msg);
                }
            }

            // Assert success response
            $this->put(
                $this->getEditUrl($metricId),
                $data + [
                    'metricId' => $metricId,
                    'context' => $context
                ]
            );
            $this->assertResponseSuccess();

            // Assert data was updated
            $updatedMetric = $table->get($metricId);
            foreach ($data as $field => $value) {
                $this->assertEquals($value, $updatedMetric->$field);
            }
        }
    }
}
