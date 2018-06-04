<?php
namespace App\Test\TestCase\Command;

use Cake\TestSuite\ConsoleIntegrationTestCase;

class ImportRunCommandTest extends ConsoleIntegrationTestCase
{
    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.criteria',
        'app.school_district_metrics',
        'app.school_metrics',
        'app.formulas',
        'app.school_district_statistics',
        'app.school_statistics',
    ];

    private $contexts = ['school', 'district'];

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
     * Tests that a merge fails because the first metric has children
     *
     * @return void
     */
    public function testMergeFailHasChildren()
    {
        foreach ($this->contexts as $context) {
            $metricIdWithChildren = 1;
            $metricIdOther = 4;
            $this->exec("merge-metrics $context $metricIdWithChildren $metricIdOther");
            $errorMsg = 'cannot be merged while it has child-metrics';
            $this->assertErrorContains($errorMsg);
        }
    }
}