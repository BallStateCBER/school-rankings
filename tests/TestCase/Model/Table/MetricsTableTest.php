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
        'app.school_metrics',
        'app.school_district_metrics',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->SchoolMetrics = TableRegistry::get(
            'SchoolMetrics',
            ['className' => SchoolMetricsTable::class]
        );
        $this->SchoolDistrictMetrics = TableRegistry::get(
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
     * Test validationDefault method
     *
     * @return void
     */
    public function testUpdateFailUnknownParent()
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
}
