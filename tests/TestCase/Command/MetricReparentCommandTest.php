<?php
namespace App\Test\TestCase\Command;

use Cake\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

class MetricReparentCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.Metrics',
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
     * Tests that reparenting to a non-root metric succeeds
     *
     * @return void
     */
    public function testReparentNonRootSuccess()
    {
        $this->markTestIncomplete();
    }

    /**
     * Tests that reparenting to root succeeds
     *
     * @return void
     */
    public function testReparentToRootSuccess()
    {
        $this->markTestIncomplete();
    }

    /**
     * Tests that a reparenting does not take place if the user enters 'n' to cancel
     *
     * @return void
     */
    public function testReparentFailCanceled()
    {
        $this->markTestIncomplete();
    }
}
