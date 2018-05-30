<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\CountiesTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\CountiesTable Test Case
 */
class CountiesTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\CountiesTable
     */
    public $Counties;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.counties',
        'app.states',
        'app.cities',
        'app.cities_counties',
        'app.rankings',
        'app.users',
        'app.formulas',
        'app.school_types',
        'app.schools',
        'app.school_districts',
        'app.school_district_statistics',
        'app.rankings_school_districts',
        'app.school_districts_cities',
        'app.school_districts_counties',
        'app.school_districts_states',
        'app.school_statistics',
        'app.schools_cities',
        'app.schools_counties',
        'app.school_levels',
        'app.schools_school_levels',
        'app.schools_states',
        'app.rankings_cities',
        'app.rankings_counties',
        'app.ranges',
        'app.rankings_ranges',
        'app.rankings_states'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::getTableLocator()->exists('Counties') ? [] : ['className' => CountiesTable::class];
        $this->Counties = TableRegistry::getTableLocator()->get('Counties', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Counties);

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
