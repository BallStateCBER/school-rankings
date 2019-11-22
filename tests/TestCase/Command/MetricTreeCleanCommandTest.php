<?php
namespace App\Test\TestCase\Command;

use Cake\TestSuite\ConsoleIntegrationTestCase;

class MetricTreeCleanCommandTest extends ConsoleIntegrationTestCase
{
    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.Metrics',
        'app.Statistics'
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
     * Tests that a metric tree can be successfully cleaned
     *
     * @return void
     */
    public function testCleanSuccess()
    {
        $this->markTestIncomplete();
    }

    /**
     * Tests that the operation does not continue when the user indicates that they want to exit
     *
     * @return void
     */
    public function testCleanFailCanceled()
    {
        $this->markTestIncomplete();
    }
}
