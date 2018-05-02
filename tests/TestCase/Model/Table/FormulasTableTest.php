<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\FormulasTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\FormulasTable Test Case
 */
class FormulasTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\FormulasTable
     */
    public $Formulas;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.formulas',
        'app.users',
        'app.rankings',
        'app.school_types',
        'app.schools',
        'app.school_districts',
        'app.school_district_statistics',
        'app.school_district_metrics',
        'app.school_metrics',
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
        'app.criteria',
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
        $config = TableRegistry::exists('Formulas') ? [] : ['className' => FormulasTable::class];
        $this->Formulas = TableRegistry::get('Formulas', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Formulas);

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
