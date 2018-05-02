<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\CriteriaTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\CriteriaTable Test Case
 */
class CriteriaTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\CriteriaTable
     */
    public $Criteria;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.criteria',
        'app.school_district_metrics',
        'app.school_metrics',
        'app.formulas',
        'app.users',
        'app.rankings',
        'app.school_types',
        'app.schools',
        'app.school_districts',
        'app.school_district_statistics',
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
        'app.school_levels',
        'app.schools_school_levels',
        'app.ranges',
        'app.rankings_ranges',
        'app.shared_formulas',
        'app.formulas_criteria'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::exists('Criteria') ? [] : ['className' => CriteriaTable::class];
        $this->Criteria = TableRegistry::get('Criteria', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Criteria);

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
