<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\SchoolDistrictMetricsTable;
use App\Model\Table\SchoolMetricsTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\SchoolMetricsTable Test Case
 */
class MetricsTableTest extends TestCase
{
    /**
     * School metrics table
     *
     * @var \App\Model\Table\SchoolMetricsTable
     */
    public $SchoolMetrics;

    /**
     * School district metrics table
     *
     * @var \App\Model\Table\SchoolDistrictMetricsTable
     */
    public $SchoolDistrictMetrics;

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
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->SchoolMetrics = TableRegistry::getTableLocator()->get(
            'SchoolMetrics',
            ['className' => SchoolMetricsTable::class]
        );
        $this->SchoolDistrictMetrics = TableRegistry::getTableLocator()->get(
            'SchoolDistrictMetrics',
            ['className' => SchoolDistrictMetricsTable::class]
        );
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->SchoolMetrics);
        unset($this->SchoolDistrictMetrics);

        parent::tearDown();
    }

    /**
     * Test that a metric can't be moved underneath a parent that doesn't exist
     *
     * @return void
     */
    public function testReparentFailUnknownParent()
    {
        foreach ([$this->SchoolMetrics, $this->SchoolDistrictMetrics] as $table) {
            $metricId = 4;
            $newParentId = 999;
            $metric = $table->get($metricId);
            $table->patchEntity($metric, [
                'parent_id' => $newParentId
            ]);
            $result = $table->save($metric);
            $this->assertFalse($result);
        }
    }

    /**
     * Test that a metric can't be created underneath a parent that doesn't exist
     *
     * @return void
     */
    public function testCreateFailUnknownParent()
    {
        foreach ([$this->SchoolMetrics, $this->SchoolDistrictMetrics] as $table) {
            $metric = $table->newEntity([
                'name' => 'Metric name',
                'parent_id' => 999,
                'type' => 'numeric',
                'selectable' => true
            ]);
            $result = $table->save($metric);
            $this->assertFalse($result);
        }
    }

    /**
     * Test enforcement of locally-unique metric names
     *
     * @return void
     */
    public function testReparentFailNonuniqueName()
    {
        foreach ([$this->SchoolMetrics, $this->SchoolDistrictMetrics] as $table) {
            $metricId = 5;
            $newParentId = 1;
            $metric = $table->get($metricId);
            $table->patchEntity($metric, [
                'parent_id' => $newParentId
            ]);
            $result = $table->save($metric);
            $this->assertFalse($result);
        }
    }

    /**
     * Test enforcement of locally-unique metric names
     *
     * @return void
     */
    public function testCreateFailNonuniqueName()
    {
        foreach ([$this->SchoolMetrics, $this->SchoolDistrictMetrics] as $table) {
            $metric = $table->newEntity([
                'name' => 'Identical name',
                'parent_id' => 1,
                'type' => 'numeric',
                'selectable' => true
            ]);
            $result = $table->save($metric);
            $this->assertFalse($result);
        }
    }

    /**
     * Test that parent metrics cannot be deleted
     *
     * @return void
     */
    public function testDeleteFailHasChildren()
    {
        foreach ([$this->SchoolMetrics, $this->SchoolDistrictMetrics] as $table) {
            $metricId = 1;
            $metric = $table->get($metricId);
            $result = $table->delete($metric);
            $this->assertFalse($result, 'Metric with children can be deleted');
        }
    }
}
