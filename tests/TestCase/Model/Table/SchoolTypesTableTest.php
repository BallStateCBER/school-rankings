<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\SchoolTypesTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\SchoolTypesTable Test Case
 */
class SchoolTypesTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var SchoolTypesTable
     */
    public $SchoolTypes;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.SchoolTypes',
        'app.Rankings',
        'app.Schools',
        'app.SchoolDistricts',
        'app.RankingsSchoolDistricts',
        'app.Cities',
        'app.SchoolDistrictsCities',
        'app.Counties',
        'app.SchoolDistrictsCounties',
        'app.States',
        'app.SchoolDistrictsStates',
        'app.Statistics',
        'app.SchoolsCities',
        'app.SchoolsCounties',
        'app.Grades',
        'app.SchoolsGrades',
        'app.SchoolsStates',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::getTableLocator()->exists('SchoolTypes') ? [] : ['className' => SchoolTypesTable::class];
        $this->SchoolTypes = TableRegistry::getTableLocator()->get('SchoolTypes', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->SchoolTypes);

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
