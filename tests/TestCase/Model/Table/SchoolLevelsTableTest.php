<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\SchoolLevelsTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\SchoolLevelsTable Test Case
 */
class SchoolLevelsTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\SchoolLevelsTable
     */
    public $SchoolLevels;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.school_levels',
        'app.rankings',
        'app.users',
        'app.formulas',
        'app.school_types',
        'app.schools',
        'app.school_districts',
        'app.school_district_statistics',
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
        'app.school_statistics',
        'app.schools_school_levels',
        'app.ranges',
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
        $config = TableRegistry::exists('SchoolLevels') ? [] : ['className' => SchoolLevelsTable::class];
        $this->SchoolLevels = TableRegistry::get('SchoolLevels', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->SchoolLevels);

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
