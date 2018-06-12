<?php
namespace App\Test\TestCase\Controller\Api;

use App\Model\Entity\Metric;
use App\Model\Table\MetricsTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestCase;
use Exception;

/**
 * MetricsControllerTest class
 *
 * @property MetricsTable $Metrics
 */
class MetricsControllerTest extends IntegrationTestCase
{
    private $Metrics;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.metrics',
        'app.statistics'
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
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->Metrics = TableRegistry::getTableLocator()->get('Metrics', ['className' => MetricsTable::class]);
    }

    /**
     * Tests a successful metric reparenting
     *
     * @param int $metricId ID of metric being moved
     * @param int $newParentId ID of new parent metric
     * @return void
     * @throws Exception
     * @throws \PHPUnit\Exception
     */
    private function _testReparentSuccess($metricId, $newParentId)
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json']
        ]);
        $metric = $this->Metrics->get($metricId);
        if ($newParentId == $metric->parent_id) {
            throw new Exception("Invalid metric chosen");
        }

        $data = [
            'metricId' => $metricId,
            'newParentId' => $newParentId,
        ];
        $this->patch($this->reparentUrl, $data);
        $this->assertResponseOk();

        $metric = $this->Metrics->get($metricId);
        $this->assertEquals($newParentId, $metric->parent_id, "Metric not moved to root");

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
     * @throws \PHPUnit\Exception
     */
    public function testReparentToRootSuccess()
    {
        $metricId = 2;
        $newParentId = null;
        $this->_testReparentSuccess($metricId, $newParentId);
    }

    /**
     * Tests that a metric can be moved FROM the root of the metrics tree
     *
     * @return void
     * @throws Exception
     * @throws \PHPUnit\Exception
     */
    public function testReparentFromRootSuccess()
    {
        $metricId = 4;
        $newParentId = 1;
        $this->_testReparentSuccess($metricId, $newParentId);
    }

    /**
     * Tests that moving a metric in a way that creates two siblings with the same name fails
     *
     * @throws Exception
     * @return void
     * @throws \PHPUnit\Exception
     */
    public function testReparentFailNonunique()
    {
        $metricId = 5;
        $newParentId = 1;

        $metric = $this->Metrics->get($metricId);
        $originalParentId = $metric->parent_id;
        if ($newParentId == $metric->parent_id) {
            throw new Exception("Invalid metric chosen");
        }

        $data = [
            'metricId' => $metricId,
            'newParentId' => $newParentId,
        ];
        $this->patch($this->reparentUrl, $data);
        $this->assertResponseError();

        $metric = $this->Metrics->get($metricId);
        $this->assertEquals(
            $originalParentId,
            $metric->parent_id,
            "Metric parent changed"
        );
    }

    /**
     * Tests moving a metric under a nonexistent parent
     *
     * @return void
     * @throws \PHPUnit\Exception
     */
    public function testReparentFailUnknownParent()
    {
        $metricId = 4;
        $newParentId = 999;
        $data = [
            'metricId' => $metricId,
            'newParentId' => $newParentId,
        ];
        $this->patch($this->reparentUrl, $data);
        $this->assertResponseError();
    }

    /**
     * Tests successful deleting of a metric
     *
     * @return void
     * @throws \PHPUnit\Exception
     */
    public function testDeleteSuccess()
    {
        $metricId = 4;
        $url = [
            'prefix' => 'api',
            'controller' => 'Metrics',
            'action' => 'delete',
            $metricId,
            '_ext' => 'json'
        ];
        $this->delete($url);
        $this->assertResponseOk();
    }

    /**
     * Tests prevention of deleting parent metrics
     *
     * @return void
     * @throws \PHPUnit\Exception
     */
    public function testDeleteFailHasChildren()
    {
        $metricId = 1;
        $url = [
            'prefix' => 'api',
            'controller' => 'Metrics',
            'action' => 'delete',
            $metricId,
            '_ext' => 'json'
        ];
        $this->delete($url);
        $this->assertResponseError();
    }

    /**
     * Tests successful creation of a metric
     *
     * @return void
     * @throws \Exception
     * @throws \PHPUnit\Exception
     */
    public function testCreateSuccess()
    {
        $metricName = 'New metric';
        $data = [
            'name' => $metricName,
            'context' => 'school',
            'description' => 'Metric description',
            'selectable' => true,
            'visible' => true,
            'parentId' => 1,
            'type' => 'numeric'
        ];
        $this->post($this->addUrl, $data);
        $this->assertResponseSuccess();

        /** @var Metric $record */
        $record = $this->Metrics->find()
            ->where(['name' => $metricName])
            ->first();
        $this->assertInstanceOf('App\\Model\\Entity\\Metric', $record);

        $this->assertEquals($data['name'], $record->name);
        $this->assertEquals($data['description'], $record->description);
        $this->assertEquals(true, $record->selectable);
        $this->assertEquals(true, $record->visible);
        $this->assertEquals($data['type'], $record->type);
        $this->assertEquals($data['parentId'], $record->parent_id);
    }

    /**
     * Tests failing to create a metric with an invalid parent
     *
     * @return void
     * @throws Exception
     * @throws \PHPUnit\Exception
     */
    public function testCreateFailInvalidParent()
    {
        $data = [
            'name' => 'New metric',
            'context' => 'school',
            'description' => 'Metric description',
            'selectable' => true,
            'visible' => true,
            'parentId' => 999,
            'type' => 'numeric'
        ];
        $this->post($this->addUrl, $data);
        $this->assertResponseError();
    }

    /**
     * Tests failing to create a metric with missing required data
     *
     * @return void
     * @throws \PHPUnit\Exception
     */
    public function testCreateFailMissingData()
    {
        $data = [
            'name' => 'New metric',
            'description' => 'Metric description',
            'selectable' => true,
            'visible' => true,
            'parentId' => 1,
            'type' => 'numeric'
        ];
        $requiredData = [
            'name',
            'type',
            'context'
        ];
        foreach ($requiredData as $dataKey) {
            $dataSubset = $data;
            unset($dataSubset[$dataKey]);
            $this->post($this->addUrl, $dataSubset);
            $this->assertResponseError('Error expected with missing ' . $dataKey);
        }
    }

    /**
     * Tests failing to create a metric with a name conflict
     *
     * @return void
     * @throws \PHPUnit\Exception
     */
    public function testCreateFailNameConflict()
    {
        $data = [
            'name' => 'Identical name',
            'description' => 'Metric description',
            'selectable' => true,
            'parentId' => 1,
            'type' => 'numeric'
        ];
        $this->post($this->addUrl, $data);
        $this->assertResponseError();
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
     * @return void
     * @throws \PHPUnit\Exception
     * @throws \Exception
     */
    public function testEditSuccess()
    {
        $metricId = 1;
        $data = [
            'name' => 'Renamed',
            'description' => 'New description',
            'type' => 'boolean',
            'selectable' => false,
            'visible' => false
        ];

        // Ensure that fixture data is different from $data
        $originalMetric = $this->Metrics->get($metricId);
        foreach ($data as $field => $value) {
            if ($originalMetric->$field == $value) {
                $msg = "Invalid metric chosen. Metric #$metricId's $field value is already $value";
                throw new Exception($msg);
            }
        }

        // Assert success response
        $this->put(
            $this->getEditUrl($metricId),
            $data + [
                'metricId' => $metricId
            ]
        );
        $this->assertResponseSuccess();

        // Assert data was updated
        $updatedMetric = $this->Metrics->get($metricId);
        foreach ($data as $field => $value) {
            $this->assertEquals($value, $updatedMetric->$field);
        }
    }
}
