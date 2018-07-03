<?php
namespace App\Test\TestCase\Command;

use Cake\TestSuite\ConsoleIntegrationTestCase;

class ImportStatsCommandTest extends ConsoleIntegrationTestCase
{
    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.imported_files',
        'app.metrics',
        'app.school_districts',
        'app.schools',
        'app.spreadsheet_columns_metrics',
        'app.statistics',
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
     * Tests that data is successfully imported into the statistics table
     *
     * @return void
     */
    public function testAddDataSuccess()
    {
        $this->markTestIncomplete();
    }

    /**
     * Tests that data is successfully updated in the statistics table
     *
     * @return void
     */
    public function testUpdateDataSuccess()
    {
        $this->markTestIncomplete();
    }

    /**
     * Tests that a new school district can be added
     *
     * @return void
     */
    public function testAddDistrictSuccess()
    {
        $this->markTestIncomplete();
    }

    /**
     * Tests that a new school can be added
     *
     * @return void
     */
    public function testAddSchoolSuccess()
    {
        $this->markTestIncomplete();
    }

    /**
     * Tests that an import fails if the first row is blank
     *
     * @return void
     */
    public function testFailBlankFirstRow()
    {
        $this->markTestIncomplete();
    }

    /**
     * Tests that an import fails if expected district information is missing
     *
     * @return void
     */
    public function testFailIncompleteDistrictData()
    {
        $this->markTestIncomplete();
    }

    /**
     * Tests that an import fails if expected school information is missing
     *
     * @return void
     */
    public function testFailIncompleteSchoolData()
    {
        $this->markTestIncomplete();
    }

    /**
     * Tests that a record is correctly added to the imported_files table
     *
     * @return void
     */
    public function testRecordImportedFile()
    {
        $this->markTestIncomplete();
    }
}
