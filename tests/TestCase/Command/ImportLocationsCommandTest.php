<?php
namespace App\Test\TestCase\Command;

use Cake\TestSuite\ConsoleIntegrationTestCase;

class ImportLocationsCommandTest extends ConsoleIntegrationTestCase
{
    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.cities',
        'app.cities_counties',
        'app.counties',
        'app.imported_files',
        'app.school_districts',
        'app.school_districts',
        'app.school_districts_cities',
        'app.school_districts_counties',
        'app.school_districts_states',
        'app.school_types',
        'app.schools',
        'app.schools_cities',
        'app.schools_counties',
        'app.schools_states',
        'app.states',
    ];

    /**
     * Sets up each test
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->useCommandRunner();
    }

    /**
     * Tests that a school district can be successfully added and associated with related records
     *
     * @return void
     */
    public function testAddDistrictSuccess()
    {
        $this->markTestIncomplete();
    }

    /**
     * Tests that a school can be successfully added and associated with related records
     *
     * @return void
     */
    public function testAddSchoolSuccess()
    {
        $this->markTestIncomplete();
    }

    /**
     * Tests that a city can be successfully added and associated with related records
     *
     * @return void
     */
    public function testAddCitySuccess()
    {
        $this->markTestIncomplete();
    }

    /**
     * Tests that a county can be successfully added and associated with related records
     *
     * @return void
     */
    public function testAddCountySuccess()
    {
        $this->markTestIncomplete();
    }

    /**
     * Tests that a state can be successfully added and associated with related records
     *
     * @return void
     */
    public function testAddStateSuccess()
    {
        $this->markTestIncomplete();
    }

    /**
     * Tests that a school district can be successfully updated
     *
     * @return void
     */
    public function testUpdateDistrictSuccess()
    {
        $this->markTestIncomplete();
    }

    /**
     * Tests that a school can be successfully updated
     *
     * @return void
     */
    public function testUpdateSchoolSuccess()
    {
        $this->markTestIncomplete();
    }
}
