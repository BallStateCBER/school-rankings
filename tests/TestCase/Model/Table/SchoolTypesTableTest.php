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
     * @var \App\Model\Table\SchoolTypesTable
     */
    public $SchoolTypes;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.school_types',
        'app.rankings',
        'app.schools',
        'app.school_districts',
        'app.school_district_statistics',
        'app.rankings_school_districts',
        'app.cities',
        'app.school_districts_cities',
        'app.counties',
        'app.school_districts_counties',
        'app.states',
        'app.school_districts_states',
        'app.school_statistics',
        'app.schools_cities',
        'app.schools_counties',
        'app.school_levels',
        'app.schools_school_levels',
        'app.schools_states'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::exists('SchoolTypes') ? [] : ['className' => SchoolTypesTable::class];
        $this->SchoolTypes = TableRegistry::get('SchoolTypes', $config);
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
