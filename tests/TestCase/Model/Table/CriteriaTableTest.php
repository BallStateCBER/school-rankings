<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\CriteriaTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\CriteriaTable Test Case
 */
class CriteriaTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\CriteriaTable
     */
    public $Criteria;

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
        'app.users',
        'app.rankings',
        'app.school_types',
        'app.schools',
        'app.school_districts',
        'app.school_district_statistics',
        'app.rankings_school_districts',
        'app.cities',
        'app.states',
        'app.counties',
        'app.cities_counties',
        'app.rankings_counties',
        'app.school_districts_counties',
        'app.schools_counties',
        'app.rankings_states',
        'app.school_districts_states',
        'app.schools_states',
        'app.rankings_cities',
        'app.school_districts_cities',
        'app.schools_cities',
        'app.school_statistics',
        'app.school_levels',
        'app.schools_school_levels',
        'app.ranges',
        'app.rankings_ranges',
        'app.shared_formulas'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::getTableLocator()->exists('Criteria') ? [] : ['className' => CriteriaTable::class];
        $this->Criteria = TableRegistry::getTableLocator()->get('Criteria', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Criteria);

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

    /**
     * Tests that adding a criterion fails when metric_id is invalid
     *
     * @return void
     */
    public function testAddFailInvalidMetric()
    {
        $table = TableRegistry::getTableLocator()->get('Criteria');
        $criterion = $table->newEntity([
            'formula_id' => 1,
            'metric_id' => 999999,
            'preference' => 'high',
            'weight' => 1
        ]);
        $result = $table->save($criterion);
        $this->assertFalse($result);
    }

    /**
     * Tests that adding a criterion fails when metric_id is invalid
     *
     * @return void
     */
    public function testAddSuccess()
    {
        $table = TableRegistry::getTableLocator()->get('Criteria');
        $criterion = $table->newEntity([
            'formula_id' => 1,
            'metric_id' => 1,
            'preference' => 'high',
            'weight' => 1
        ]);
        $result = $table->save($criterion);
        $this->assertInstanceOf('App\Model\Entity\Criterion', $result);
    }
}
