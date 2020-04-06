<?php
namespace App\Test\TestCase\Controller\Api;

use App\Model\Entity\Metric;
use App\Model\Table\MetricsTable;
use App\Test\Fixture\MetricsFixture;
use Cake\Cache\Cache;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Exception;
use PHPUnit\Exception as PhpUnitException;

/**
 * MetricsControllerTest class
 *
 * @property MetricsTable $Metrics
 */
class MetricsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    private $Metrics;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.Metrics',
        'app.Statistics',
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
        '_ext' => 'json',
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
        '_ext' => 'json',
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
     * @throws PhpUnitException
     */
    private function _testReparentSuccess($metricId, $newParentId)
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
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
            'result' => true,
        ]);

        // Normalize whitespace in response JSON
        $actual = (string)$this->_response->getBody();
        $actual = json_encode(json_decode($actual));

        $this->assertEquals($expected, $actual);
    }

    /**
     * Tests that a metric can be moved TO the root of the metrics tree
     *
     * @return void
     * @throws PhpUnitException
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
     * @throws PhpUnitException
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
     * @return void
     * @throws Exception
     * @throws PhpUnitException
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
     * @throws PhpUnitException
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
     * @throws PhpUnitException
     */
    public function testDeleteSuccess()
    {
        $metricId = 4;
        $url = [
            'prefix' => 'api',
            'controller' => 'Metrics',
            'action' => 'delete',
            $metricId,
            '_ext' => 'json',
        ];
        $this->delete($url);
        $this->assertResponseOk();
    }

    /**
     * Tests prevention of deleting parent metrics
     *
     * @return void
     * @throws PhpUnitException
     */
    public function testDeleteFailHasChildren()
    {
        $metricId = 1;
        $url = [
            'prefix' => 'api',
            'controller' => 'Metrics',
            'action' => 'delete',
            $metricId,
            '_ext' => 'json',
        ];
        $this->delete($url);
        $this->assertResponseError();
    }

    /**
     * Tests successful creation of a metric
     *
     * @return void
     * @throws Exception
     * @throws PhpUnitException
     */
    public function testCreateSuccess()
    {
        $metricName = 'New metric';
        $data = [
            'name' => $metricName,
            'context' => 'school',
            'description' => 'Metric description',
            'selectable' => 'true',
            'visible' => 'true',
            'parentId' => 1,
            'type' => 'numeric',
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
     * Tests successful creation of a hidden and unselectable metric
     *
     * @return void
     * @throws Exception
     * @throws PhpUnitException
     */
    public function testCreateHiddenSuccess()
    {
        $metricName = 'New hidden metric';
        $data = [
            'name' => $metricName,
            'context' => 'school',
            'description' => 'Metric description',
            'selectable' => 'false',
            'visible' => 'false',
            'parentId' => 1,
            'type' => 'numeric',
        ];
        $this->post($this->addUrl, $data);
        $this->assertResponseSuccess();

        /** @var Metric $record */
        $record = $this->Metrics->find()
            ->where(['name' => $metricName])
            ->first();
        $this->assertInstanceOf('App\\Model\\Entity\\Metric', $record);
        $this->assertEquals(false, $record->selectable);
        $this->assertEquals(false, $record->visible);
    }

    /**
     * Tests failing to create a metric with an invalid parent
     *
     * @return void
     * @throws Exception
     * @throws PhpUnitException
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
            'type' => 'numeric',
        ];
        $this->post($this->addUrl, $data);
        $this->assertResponseError();
    }

    /**
     * Tests failing to create a metric with missing required data
     *
     * @return void
     * @throws PhpUnitException
     */
    public function testCreateFailMissingData()
    {
        $data = [
            'name' => 'New metric',
            'context' => 'school',
            'description' => 'Metric description',
            'selectable' => true,
            'visible' => true,
            'parentId' => 1,
            'type' => 'numeric',
        ];
        $requiredData = [
            'name',
            'type',
            'context',
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
     * @throws PhpUnitException
     */
    public function testCreateFailNameConflict()
    {
        $data = [
            'name' => 'Identical name',
            'context' => 'school',
            'description' => 'Metric description',
            'selectable' => true,
            'parentId' => 1,
            'type' => 'numeric',
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
            '_ext' => 'json',
        ];
    }

    /**
     * Tests successfully editing a metric
     *
     * @return void
     * @throws PhpUnitException
     * @throws Exception
     */
    public function testEditSuccess()
    {
        $metricId = 1;
        $data = [
            'name' => 'Renamed',
            'description' => 'New description',
            'type' => 'boolean',
            'selectable' => false,
            'visible' => false,
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
            $data + ['metricId' => $metricId]
        );
        $this->assertResponseSuccess();

        // Assert data was updated
        $updatedMetric = $this->Metrics->get($metricId);
        foreach ($data as $field => $value) {
            $this->assertEquals($value, $updatedMetric->$field);
        }
    }

    /**
     * Tests the successful response from the /api/metrics/districts.json?no-hidden=1 endpoint
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testDistrictsGetNoHidden()
    {
        Cache::disable();
        $this->get([
            'prefix' => 'api',
            'controller' => 'Metrics',
            'action' => 'districts',
            '_ext' => 'json',
            '?' => ['no-hidden' => 1],
        ]);
        Cache::enable();
        $this->assertResponseOk();
        $responseString = (string)$this->_response->getBody();
        $this->assertJson($responseString);
        $responseObject = json_decode($responseString);
        $this->assertObjectHasAttribute('metrics', $responseObject);
        $this->assertResponseNotContains(sprintf('"id":%s', MetricsFixture::HIDDEN_DISTRICT_METRIC));
    }

    /**
     * Tests the successful response from the /api/metrics/districts.json endpoint without the no-hidden flag
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testDistrictsGetIncludingHidden()
    {
        Cache::disable();
        $this->get([
            'prefix' => 'api',
            'controller' => 'Metrics',
            'action' => 'districts',
            '_ext' => 'json',
        ]);
        Cache::enable();
        $this->assertResponseOk();
        $responseString = (string)$this->_response->getBody();
        $this->assertJson($responseString);
        $this->assertResponseContains(sprintf('"id":%s', MetricsFixture::HIDDEN_DISTRICT_METRIC));
    }

    /**
     * Tests the successful response from the /api/metrics/schools.json?no-hidden=1 endpoint
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testSchoolsGetNoHidden()
    {
        Cache::disable();
        $this->get([
            'prefix' => 'api',
            'controller' => 'Metrics',
            'action' => 'schools',
            '_ext' => 'json',
            '?' => ['no-hidden' => 1],
        ]);
        Cache::enable();
        $this->assertResponseOk();
        $responseString = (string)$this->_response->getBody();
        $this->assertJson($responseString);
        $responseObject = json_decode($responseString);
        $this->assertObjectHasAttribute('metrics', $responseObject);
        $this->assertResponseNotContains(sprintf('"id":%s', MetricsFixture::HIDDEN_SCHOOL_METRIC));
    }

    /**
     * Tests the successful response from the /api/metrics/schools.json endpoint without the no-hidden flag
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testSchoolsGetIncludingHidden()
    {
        Cache::disable();
        $this->get([
            'prefix' => 'api',
            'controller' => 'Metrics',
            'action' => 'schools',
            '_ext' => 'json',
        ]);
        Cache::enable();
        $this->assertResponseOk();
        $responseString = (string)$this->_response->getBody();
        $this->assertJson($responseString);
        $this->assertResponseContains(sprintf('"id":%s', MetricsFixture::HIDDEN_SCHOOL_METRIC));
    }
}
