<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\RangesTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\RangesTable Test Case
 */
class RangesTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var RangesTable
     */
    public $Ranges;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.Cities',
        'app.CitiesCounties',
        'app.Counties',
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
        'app.States',
        'app.Statistics',
        'app.Users',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::getTableLocator()->exists('Ranges') ? [] : ['className' => RangesTable::class];
        $this->Ranges = TableRegistry::getTableLocator()->get('Ranges', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Ranges);

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
