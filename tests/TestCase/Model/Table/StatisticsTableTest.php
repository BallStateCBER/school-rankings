<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\StatisticsTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\StatisticsTable Test Case
 */
class StatisticsTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\StatisticsTable
     */
    public $Statistics;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.Statistics',
        'app.Metrics',
        'app.Schools',
        'app.SchoolDistricts'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::getTableLocator()->exists('Statistics') ? [] : ['className' => StatisticsTable::class];
        $this->Statistics = TableRegistry::getTableLocator()->get('Statistics', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Statistics);

        parent::tearDown();
    }

    /**
     * Tests validation
     *
     * @return void
     */
    public function testValidation()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Tests application rules
     *
     * @return void
     */
    public function testRules()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Tests that float values are properly rounded to five decimal places when saved to the database
     *
     * @return void
     */
    public function testRoundingFloat()
    {
        $this->markTestIncomplete();
    }
}
