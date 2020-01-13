<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\RankingResultsSchoolDistrictsTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\RankingResultsSchoolDistrictsTable Test Case
 */
class RankingResultsSchoolDistrictsTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var RankingResultsSchoolDistrictsTable
     */
    public $RankingResultsSchoolDistricts;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.RankingResultsSchoolDistricts',
        'app.Rankings',
        'app.SchoolDistricts',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::getTableLocator()->exists('RankingResultsSchoolDistricts') ? [] : ['className' => RankingResultsSchoolDistrictsTable::class];
        $this->RankingResultsSchoolDistricts = TableRegistry::getTableLocator()->get('RankingResultsSchoolDistricts', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->RankingResultsSchoolDistricts);

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
