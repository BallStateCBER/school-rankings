<?php
namespace App\Test\TestCase\Command;

use App\Model\Table\StatisticsTable;
use Cake\Console\Exception\StopException;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\ConsoleIntegrationTestCase;

class MetricMergeCommandTest extends ConsoleIntegrationTestCase
{
    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.criteria',
        'app.metrics',
        'app.formulas',
        'app.statistics',
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
            $this->exec("metric-merge $context $metricIdWithChildren $metricIdOther");
            $errorMsg = 'cannot be merged while it has child-metrics';
            $this->assertErrorContains($errorMsg);
        }
    }

    /**
     * Tests that a merge fails because a metric was not found
     *
     * @return void
     */
    public function testMergeFailNotFound()
    {
        for ($n = 0; $n <= 1; $n++) {
            foreach ($this->contexts as $context) {
                $metricA = $n ? 2 : 9999999;
                $metricB = $n ? 9999999 : 3;
                $this->exec("metric-merge $context $metricA $metricB");
                $errorMsg = 'not found';
                $this->assertErrorContains($errorMsg);
            }
        }
    }

    /**
     * Tests that a merge successfully updates and deletes statistics
     *
     * @return void
     */
    public function testMergeStats()
    {
        /** @var StatisticsTable $statsTable */
        $statsTable = TableRegistry::getTableLocator()->get('Statistics');
        $metricA = 2;
        $metricB = 3;
        $statIdToUpdate = 2;
        $statIdToDelete = 3;

        foreach ($this->contexts as $context) {
            $this->assertTrue(
                $statsTable->exists(['id' => $statIdToDelete])
            );
            $this->assertEquals(
                $metricA,
                $statsTable->get($statIdToUpdate)->metric_id
            );

            $this->exec("metric-merge $context $metricA $metricB", ['y']);

            $this->assertEquals(
                $metricB,
                $statsTable->get($statIdToUpdate)->metric_id
            );
            $this->assertFalse(
                $statsTable->exists(['id' => $statIdToDelete])
            );
        }
    }

    /**
     * Tests that a merge successfully updates and deletes criteria
     *
     * @return void
     */
    public function testMergeCriteria()
    {
        $criteriaTable = TableRegistry::getTableLocator()->get('Criteria');
        $metricA = 2;
        $metricB = 3;

        // Array of IDs of criteria records to be updated and deleted
        $criteria = [
            'school' => [
                'toUpdate' => 3,
                'toDelete' => 4
            ],
            'district' => [
                'toUpdate' => 6,
                'toDelete' => 7
            ]
        ];

        foreach ($criteria as $context => $criteriaIds) {
            $criterionToUpdate = $criteriaIds['toUpdate'];
            $criterionToDelete = $criteriaIds['toDelete'];

            // Test that fixture data is correct
            $this->assertTrue(
                $criteriaTable->exists(['id' => $criterionToUpdate])
            );
            $this->assertEquals(
                $metricA,
                $criteriaTable->get($criterionToUpdate)->metric_id
            );
            $this->assertTrue(
                $criteriaTable->exists(['id' => $criterionToDelete])
            );

            // Execute merge
            try {
                $this->exec("metric-merge $context $metricA $metricB", ['y']);
            } catch (StopException $e) {
                print_r($e->getCode());
            }

            // Test that update succeeded
            $this->assertTrue(
                $criteriaTable->exists(['id' => $criterionToUpdate]),
                'Criterion that should have been updated (' . $criterionToUpdate . ') was deleted'
            );
            $this->assertEquals(
                $metricB,
                $criteriaTable->get($criterionToUpdate)->metric_id
            );

            // Test that delete succeeded
            $this->assertFalse(
                $criteriaTable->exists(['id' => $criterionToDelete]),
                'Criterion that should have been deleted (' . $criterionToDelete . ') wasn\'t'
            );
        }
    }

    /**
     * Tests that a metric is successfully deleted after being merged
     *
     * @return void
     */
    public function testPostMergeDelete()
    {
        $metricA = 2;
        $metricB = 3;
        $metricsTable = TableRegistry::getTableLocator()->get('Metrics');
        foreach ($this->contexts as $context) {
            $this->assertTrue($metricsTable->exists(['id' => $metricA]));
            $this->exec("metric-merge $context $metricA $metricB", ['y']);
            $this->assertFalse($metricsTable->exists(['id' => $metricA]));
        }
    }

    /**
     * Tests that a merge does not take place if the user enters 'n' to cancel
     *
     * @return void
     */
    public function testMergeFailCanceled()
    {
        $this->markTestIncomplete();
    }
}
