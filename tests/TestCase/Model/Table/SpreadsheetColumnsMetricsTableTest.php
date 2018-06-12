<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\SpreadsheetColumnsMetricsTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\SpreadsheetColumnsMetricsTable Test Case
 */
class SpreadsheetColumnsMetricsTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\SpreadsheetColumnsMetricsTable
     */
    public $SpreadsheetColumnsMetrics;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.spreadsheet_columns_metrics',
        'app.metrics'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::getTableLocator()->exists('SpreadsheetColumnsMetrics') ? [] : ['className' => SpreadsheetColumnsMetricsTable::class];
        $this->SpreadsheetColumnsMetrics = TableRegistry::getTableLocator()->get('SpreadsheetColumnsMetrics', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->SpreadsheetColumnsMetrics);

        parent::tearDown();
    }

    /**
     * Test initialize method
     *
     * @return void
     */
    public function testInitialize()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test validationDefault method
     *
     * @return void
     */
    public function testValidationDefault()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     */
    public function testBuildRules()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
