<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\RankingsTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\RankingsTable Test Case
 */
class RankingsTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\RankingsTable
     */
    public $Rankings;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.cities',
        'app.counties',
        'app.formulas',
        'app.grades',
        'app.ranges',
        'app.rankings',
        'app.rankings_cities',
        'app.rankings_counties',
        'app.rankings_ranges',
        'app.rankings_school_districts',
        'app.rankings_states',
        'app.school_districts',
        'app.school_districts_cities',
        'app.school_districts_counties',
        'app.school_districts_states',
        'app.school_types',
        'app.schools',
        'app.schools_cities',
        'app.schools_counties',
        'app.schools_grades',
        'app.schools_states',
        'app.states',
        'app.statistics',
        'app.users'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::getTableLocator()->exists('Rankings') ? [] : ['className' => RankingsTable::class];
        $this->Rankings = TableRegistry::getTableLocator()->get('Rankings', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Rankings);

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
