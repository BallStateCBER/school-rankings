<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\SharedFormulasTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\SharedFormulasTable Test Case
 */
class SharedFormulasTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\SharedFormulasTable
     */
    public $SharedFormulas;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.shared_formulas',
        'app.formulas',
        'app.users',
        'app.rankings',
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
        'app.ranges',
        'app.rankings_ranges',
        'app.criteria'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::getTableLocator()->exists('SharedFormulas') ? [] : ['className' => SharedFormulasTable::class];
        $this->SharedFormulas = TableRegistry::getTableLocator()->get('SharedFormulas', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->SharedFormulas);

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
