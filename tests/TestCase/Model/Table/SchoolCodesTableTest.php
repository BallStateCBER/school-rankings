<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\SchoolCodesTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\SchoolCodesTable Test Case
 */
class SchoolCodesTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\SchoolCodesTable
     */
    public $SchoolCodes;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.school_codes',
        'app.schools'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::getTableLocator()->exists('SchoolCodes') ? [] : ['className' => SchoolCodesTable::class];
        $this->SchoolCodes = TableRegistry::getTableLocator()->get('SchoolCodes', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->SchoolCodes);

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
