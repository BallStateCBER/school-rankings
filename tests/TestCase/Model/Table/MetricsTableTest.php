<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\MetricsTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\MetricsTable Test Case
 */
class MetricsTableTest extends TestCase
{
    /**
     * Metrics table
     *
     * @var \App\Model\Table\MetricsTable
     */
    public $Metrics;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.Metrics',
        'app.Statistics'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->Metrics = TableRegistry::getTableLocator()->get(
            'Metrics',
            ['className' => MetricsTable::class]
        );
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Metrics);

        parent::tearDown();
    }

    /**
     * Test that a metric can't be moved underneath a parent that doesn't exist
     *
     * @return void
     */
    public function testReparentFailUnknownParent()
    {
        $metricId = 4;
        $newParentId = 999;
        $metric = $this->Metrics->get($metricId);
        $this->Metrics->patchEntity($metric, [
            'parent_id' => $newParentId
        ]);
        $result = $this->Metrics->save($metric);
        $this->assertFalse($result);
    }

    /**
     * Test that a metric can't be created underneath a parent that doesn't exist
     *
     * @return void
     */
    public function testCreateFailUnknownParent()
    {
        $metric = $this->Metrics->newEntity([
            'name' => 'Metric name',
            'context' => 'school',
            'parent_id' => 999,
            'type' => 'numeric',
            'selectable' => true
        ]);
        $result = $this->Metrics->save($metric);
        $this->assertFalse($result);
    }

    /**
     * Test enforcement of locally-unique metric names
     *
     * @return void
     */
    public function testReparentFailNonuniqueName()
    {
        $metricId = 5;
        $newParentId = 1;
        $metric = $this->Metrics->get($metricId);
        $this->Metrics->patchEntity($metric, [
            'parent_id' => $newParentId
        ]);
        $result = $this->Metrics->save($metric);
        $this->assertFalse($result);
    }

    /**
     * Test enforcement of locally-unique metric names
     *
     * @return void
     */
    public function testCreateFailNonuniqueName()
    {
        $metric = $this->Metrics->newEntity([
            'name' => 'Identical name',
            'context' => 'school',
            'parent_id' => 1,
            'type' => 'numeric',
            'selectable' => true
        ]);
        $result = $this->Metrics->save($metric);
        $this->assertFalse($result);
    }

    /**
     * Test that parent metrics cannot be deleted
     *
     * @return void
     */
    public function testDeleteFailHasChildren()
    {
        $metricId = 1;
        $metric = $this->Metrics->get($metricId);
        $result = $this->Metrics->delete($metric);
        $this->assertFalse($result, 'Metric with children can be deleted');
    }
}
