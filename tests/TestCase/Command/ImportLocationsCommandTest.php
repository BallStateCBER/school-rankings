<?php
namespace App\Test\TestCase\Command;

use Cake\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

class ImportLocationsCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.Cities',
        'app.CitiesCounties',
        'app.Counties',
        'app.ImportedFiles',
        'app.SchoolDistricts',
        'app.SchoolDistrictsCities',
        'app.SchoolDistrictsCounties',
        'app.SchoolDistrictsStates',
        'app.SchoolTypes',
        'app.Schools',
        'app.SchoolsCities',
        'app.SchoolsCounties',
        'app.SchoolsStates',
        'app.States',
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
