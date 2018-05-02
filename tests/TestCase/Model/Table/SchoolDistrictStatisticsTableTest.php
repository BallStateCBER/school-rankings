<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\SchoolDistrictStatisticsTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\SchoolDistrictStatisticsTable Test Case
 */
class SchoolDistrictStatisticsTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\SchoolDistrictStatisticsTable
     */
    public $SchoolDistrictStatistics;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.school_district_statistics',
        'app.school_district_metrics',
        'app.school_metrics',
        'app.school_districts',
        'app.schools',
        'app.school_types',
        'app.rankings',
        'app.users',
        'app.formulas',
        'app.school_levels',
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
        'app.ranges',
        'app.rankings_ranges',
        'app.rankings_school_districts',
        'app.school_statistics',
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
        $config = TableRegistry::exists('SchoolDistrictStatistics') ? [] : ['className' => SchoolDistrictStatisticsTable::class];
        $this->SchoolDistrictStatistics = TableRegistry::get('SchoolDistrictStatistics', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->SchoolDistrictStatistics);

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
