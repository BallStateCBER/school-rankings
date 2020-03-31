<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\FormulasTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\FormulasTable Test Case
 */
class FormulasTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var FormulasTable
     */
    public $Formulas;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.Cities',
        'app.CitiesCounties',
        'app.Counties',
        'app.Criteria',
        'app.Formulas',
        'app.Grades',
        'app.Metrics',
        'app.Ranges',
        'app.Rankings',
        'app.RankingsCities',
        'app.RankingsCounties',
        'app.RankingsRanges',
        'app.RankingsSchoolDistricts',
        'app.RankingsStates',
        'app.SchoolDistricts',
        'app.SchoolDistrictsCities',
        'app.SchoolDistrictsCounties',
        'app.SchoolDistrictsStates',
        'app.SchoolTypes',
        'app.Schools',
        'app.SchoolsCities',
        'app.SchoolsCounties',
        'app.SchoolsGrades',
        'app.SchoolsStates',
        'app.SharedFormulas',
        'app.States',
        'app.Statistics',
        'app.Users',
    ];

    private $validData = [
        'user_id' => null,
        'is_example' => false,
        'title' => '',
        'context' => 'school',
        'hash' => 'hashhash',
        'notes' => '',
        'criteria' => [
            [
                'metric_id' => 1,
                'weight' => 100,
                'preference' => 'high',
            ],
        ],
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::getTableLocator()->exists('Formulas') ? [] : ['className' => FormulasTable::class];
        $this->Formulas = TableRegistry::getTableLocator()->get('Formulas', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Formulas);

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
     * Test buildRules method
     *
     * @return void
     */
    public function testBuildRules()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Tests hashes for uniqueness
     *
     * @return void
     */
    public function testHashUniqueness()
    {
        $hash1 = $this->Formulas->generateHash();
        $hash2 = $this->Formulas->generateHash();
        $this->assertNotEquals($hash1, $hash2, 'Hashes are not unique');
    }

    /**
     * Tests that valid data passes validation rules
     *
     * @return void
     */
    public function testValidationSuccess()
    {
        $formula = $this->Formulas->newEntity($this->validData);
        $this->assertFalse($formula->hasErrors());
    }

    /**
     * Tests that validation fails if a hash is not unique
     *
     * @return void
     */
    public function testValidationFailHashNotUnique()
    {
        $existingFormula = $this->Formulas->get(1);
        $invalidData = $this->validData;
        $invalidData['hash'] = $existingFormula->hash;
        $newFormula = $this->Formulas->newEntity($invalidData);
        $this->assertFalse($this->Formulas->checkRules($newFormula));
    }

    /**
     * Tests that validation fails if a formula's context is invalid
     *
     * @return void
     */
    public function testValidationFailInvalidContext()
    {
        $invalidData = $this->validData;
        $invalidData['context'] = 'invalid';
        $newFormula = $this->Formulas->newEntity($invalidData);
        $this->assertTrue($newFormula->hasErrors());
    }
}
