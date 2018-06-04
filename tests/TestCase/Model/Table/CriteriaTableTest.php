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
        'app.formulas'
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
     * Tests that adding a criterion fails when metric_id is invalid
     *
     * @return void
     */
    public function testAddFailInvalidMetric()
    {
        $criterion = $this->Criteria->newEntity([
            'formula_id' => 1,
            'metric_id' => 999999,
            'preference' => 'high',
            'weight' => 1
        ]);
        $result = $this->Criteria->save($criterion);
        $this->assertFalse($result);
    }

    /**
     * Tests successful adding
     *
     * @return void
     */
    public function testAddSuccess()
    {
        $criterion = $this->Criteria->newEntity([
            'formula_id' => 1,
            'metric_id' => 1,
            'preference' => 'high',
            'weight' => 1
        ]);
        $result = $this->Criteria->save($criterion);
        $this->assertInstanceOf('App\Model\Entity\Criterion', $result);
    }

    /**
     * Tests CriteriaTable::getContext()
     *
     * @return void
     */
    public function testGetContext()
    {
        $schoolCriterion = $this->Criteria->get(1);
        $context = $this->Criteria->getContext($schoolCriterion);
        $this->assertEquals('school', $context);

        $districtCriterion = $this->Criteria->get(2);
        $context = $this->Criteria->getContext($districtCriterion);
        $this->assertEquals('district', $context);
    }
}
