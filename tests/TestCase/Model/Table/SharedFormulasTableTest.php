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
        'app.Cities',
        'app.CitiesCounties',
        'app.Counties',
        'app.Criteria',
        'app.Formulas',
        'app.Grades',
        'app.Metrics',
        'app.Ranges',
        'app.Rankings',
        'app.RankingsCities',
        'app.RankingsCounties',
        'app.RankingsRanges',
        'app.RankingsSchoolDistricts',
        'app.RankingsStates',
        'app.SchoolDistricts',
        'app.SchoolDistrictsCities',
        'app.SchoolDistrictsCounties',
        'app.SchoolDistrictsStates',
        'app.SchoolTypes',
        'app.Schools',
        'app.SchoolsCities',
        'app.SchoolsCounties',
        'app.SchoolsGrades',
        'app.SchoolsStates',
        'app.SharedFormulas',
        'app.States',
        'app.Statistics',
        'app.Users'
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
