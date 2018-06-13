<?php
namespace App\Test\TestCase\Command;

use Cake\TestSuite\TestCase;

/**
 * App\Command\Utility Test Case
 */
class UtilityTest extends TestCase
{
    /**
     * Tests that parsing a valid ID string succeeds
     *
     * @return void
     */
    public function testParseSuccess()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Tests that parsing an ID string fails because of a non-numeric ID
     *
     * @return void
     */
    public function testParseFailNotNumeric()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Tests that parsing an ID string fails because of a range like "1-3-5"
     *
     * @return void
     */
    public function testParseFailInvalidRange()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
