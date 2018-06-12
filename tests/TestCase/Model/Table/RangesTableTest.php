<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\RangesTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\RangesTable Test Case
 */
class RangesTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\RangesTable
     */
    public $Ranges;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.ranges',
        'app.rankings',
        'app.users',
        'app.formulas',
        'app.school_types',
        'app.schools',
        'app.school_districts',
        'app.metrics',
        'app.rankings_school_districts',
        'app.cities',
        'app.states',
        'app.counties',
        'app.cities_counties',
        'app.rankings_counties',
        'app.school_districts_counties',
        'app.schools_counties',
        'app.rankings_states',
        'app.school_districts_states',
        'app.schools_states',
        'app.rankings_cities',
        'app.school_districts_cities',
        'app.schools_cities',
        'app.statistics',
        'app.school_levels',
        'app.schools_school_levels',
        'app.rankings_ranges'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::getTableLocator()->exists('Ranges') ? [] : ['className' => RangesTable::class];
        $this->Ranges = TableRegistry::getTableLocator()->get('Ranges', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Ranges);

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
}
