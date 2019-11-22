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
        'app.SchoolDistricts',
        'app.Statistics',
        'app.Schools',
        'app.Rankings',
        'app.RankingsSchoolDistricts',
        'app.Cities',
        'app.SchoolDistrictsCities',
        'app.Counties',
        'app.SchoolDistrictsCounties',
        'app.States',
        'app.SchoolDistrictsStates'
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

    /**
     * Tests that multiple districts with the same code are not allowed
     *
     * @return void
     */
    public function testSaveFailNonUniqueCode()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
