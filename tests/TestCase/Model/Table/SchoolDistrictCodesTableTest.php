<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\SchoolDistrictCodesTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\SchoolDistrictCodesTable Test Case
 */
class SchoolDistrictCodesTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\SchoolDistrictCodesTable
     */
    public $SchoolDistrictCodes;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.school_district_codes',
        'app.school_districts'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::getTableLocator()->exists('SchoolDistrictCodes') ? [] : ['className' => SchoolDistrictCodesTable::class];
        $this->SchoolDistrictCodes = TableRegistry::getTableLocator()->get('SchoolDistrictCodes', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->SchoolDistrictCodes);

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
