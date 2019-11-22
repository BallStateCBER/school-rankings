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
        'app.SchoolsStates',
        'app.Schools',
        'app.SchoolDistricts',
        'app.Rankings',
        'app.RankingsSchoolDistricts',
        'app.Cities',
        'app.SchoolDistrictsCities',
        'app.Counties',
        'app.SchoolDistrictsCounties',
        'app.States',
        'app.SchoolDistrictsStates',
        'app.SchoolTypes',
        'app.Statistics',
        'app.SchoolsCities',
        'app.SchoolsCounties',
        'app.Grades',
        'app.SchoolsGrades'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::getTableLocator()->exists('SchoolsStates') ? [] : ['className' => SchoolsStatesTable::class];
        $this->SchoolsStates = TableRegistry::getTableLocator()->get('SchoolsStates', $config);
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
