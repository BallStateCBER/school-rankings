<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\RankingsSchoolTypesTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\RankingsSchoolTypesTable Test Case
 */
class RankingsSchoolTypesTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var RankingsSchoolTypesTable
     */
    public $RankingsSchoolTypes;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.RankingsSchoolTypes',
        'app.Rankings',
        'app.SchoolTypes',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::getTableLocator()->exists('RankingsSchoolTypes') ? [] : ['className' => RankingsSchoolTypesTable::class];
        $this->RankingsSchoolTypes = TableRegistry::getTableLocator()->get('RankingsSchoolTypes', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->RankingsSchoolTypes);

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
