<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\RankingResultsSchoolsTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\RankingResultsSchoolsTable Test Case
 */
class RankingResultsSchoolsTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\RankingResultsSchoolsTable
     */
    public $RankingResultsSchools;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.ranking_results_schools',
        'app.rankings',
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
        $config = TableRegistry::getTableLocator()->exists('RankingResultsSchools') ? [] : ['className' => RankingResultsSchoolsTable::class];
        $this->RankingResultsSchools = TableRegistry::getTableLocator()->get('RankingResultsSchools', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->RankingResultsSchools);

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
