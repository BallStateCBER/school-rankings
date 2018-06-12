<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\SchoolDistrictsTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\SchoolDistrictsTable Test Case
 */
class SchoolDistrictsTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\SchoolDistrictsTable
     */
    public $SchoolDistricts;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.school_districts',
        'app.statistics',
        'app.schools',
        'app.rankings',
        'app.rankings_school_districts',
        'app.cities',
        'app.school_districts_cities',
        'app.counties',
        'app.school_districts_counties',
        'app.states',
        'app.school_districts_states'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::getTableLocator()->exists('SchoolDistricts') ? [] : ['className' => SchoolDistrictsTable::class];
        $this->SchoolDistricts = TableRegistry::getTableLocator()->get('SchoolDistricts', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->SchoolDistricts);

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
