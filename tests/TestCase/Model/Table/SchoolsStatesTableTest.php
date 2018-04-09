<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\SchoolsStatesTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\SchoolsStatesTable Test Case
 */
class SchoolsStatesTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\SchoolsStatesTable
     */
    public $SchoolsStates;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.schools_states',
        'app.schools',
        'app.school_districts',
        'app.school_district_statistics',
        'app.rankings',
        'app.rankings_school_districts',
        'app.cities',
        'app.school_districts_cities',
        'app.counties',
        'app.school_districts_counties',
        'app.states',
        'app.school_districts_states',
        'app.school_types',
        'app.school_statistics',
        'app.schools_cities',
        'app.schools_counties',
        'app.school_levels',
        'app.schools_school_levels'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::exists('SchoolsStates') ? [] : ['className' => SchoolsStatesTable::class];
        $this->SchoolsStates = TableRegistry::get('SchoolsStates', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->SchoolsStates);

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