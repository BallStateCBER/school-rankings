<?php
namespace App\Test\TestCase\Controller\Api;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Class FormulasControllerTest
 *
 * @package App\Test\TestCase\Controller\Api
 * @property \App\Model\Table\FormulasTable $Formulas
 */
class FormulasControllerTest extends TestCase
{
    use IntegrationTestTrait;

    private $Formulas;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.Metrics',
        'app.Statistics',
        'app.Formulas',
        'app.Criteria',
    ];

    /**
     * The URL array for creating a formula record
     *
     * @var array
     */
    private $addUrl = [
        'prefix' => 'api',
        'controller' => 'Formulas',
        'action' => 'add',
        '_ext' => 'json',
    ];

    private $formulaData = [
        'context' => 'school',
        'criteria' => [
            [
                'metric' => ['id' => 1],
                'weight' => 1,
            ],
            [
                'metric' => ['id' => 2],
                'weight' => 200,
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
        $this->Formulas = TableRegistry::getTableLocator()->get('Formulas');
    }

    /**
     * Tests that the add endpoint works correctly with valid input
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testAddSuccess()
    {
        $originalCount = $this->Formulas->find()->count();

        $this->post($this->addUrl, $this->formulaData);
        $this->assertResponseOk();
        $this->assertBodyIsJson();

        $newCount = $this->Formulas->find()->count();
        $this->assertEquals($originalCount + 1, $newCount, 'New formula record was not created');

        $this->assertJsonStringEqualsJsonString(
            json_encode(['success' => true, 'id' => $newCount]),
            (string)$this->_response->getBody(),
            'Unexpected API response'
        );
    }

    /**
     * Tests that the add endpoint fails for an invalid context
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testAddFailInvalidContext()
    {
        $invalidData = $this->formulaData;
        $invalidData['context'] = 'invalid';
        $this->post($this->addUrl, $invalidData);
        $this->assertBodyIsJson();
        $this->runAddFailureAssertions();
    }

    /**
     * Tests that the add endpoint fails if no criteria are provided
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testAddFailNoCriteria()
    {
        $invalidData = $this->formulaData;
        $invalidData['criteria'] = [];
        $this->post($this->addUrl, $invalidData);
        $this->assertBodyIsJson();
        $this->runAddFailureAssertions();
    }

    /**
     * Tests that the add endpoint fails if an invalid metric ID is selected
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testAddFailInvalidMetric()
    {
        $invalidData = $this->formulaData;
        $invalidData['criteria'][0]['metric']['id'] = 999;
        $this->post($this->addUrl, $invalidData);
        $this->assertBodyIsJson();
        $this->runAddFailureAssertions();
    }

    /**
     * Tests that the add endpoint fails if a weight is above the valid range
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testAddFailInvalidWeightHigh()
    {
        $invalidData = $this->formulaData;
        $invalidData['criteria'][0]['weight'] = 201;
        $this->post($this->addUrl, $invalidData);
        $this->assertBodyIsJson();
        $this->runAddFailureAssertions();
    }

    /**
     * Tests that the add endpoint fails if a weight is below the valid range
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testAddFailInvalidWeightLow()
    {
        $invalidData = $this->formulaData;
        $invalidData['criteria'][0]['weight'] = 0;
        $this->post($this->addUrl, $invalidData);
        $this->assertBodyIsJson();
        $this->runAddFailureAssertions();
    }

    /**
     * Runs assertions related to failure to add a new formula due to invalid data being provided
     *
     * @return void
     */
    private function runAddFailureAssertions()
    {
        $this->assertResponseError('Response was not in the 4xx range');

        $originalCount = $this->Formulas->find()->count();
        $newCount = $this->Formulas->find()->count();
        $this->assertEquals($originalCount, $newCount, 'New formula record was created, but shouldn\'t have been');

        $responseBody = $this->_response->getBody();
        $this->assertJsonStringEqualsJsonString(
            json_encode(['success' => false, 'id' => null]),
            (string)$responseBody,
            'Unexpected API response'
        );
    }

    /**
     * Asserts that the body of the response is valid JSON
     *
     * @return void
     */
    private function assertBodyIsJson()
    {
        $responseBody = $this->_response->getBody();
        $this->assertJson((string)$responseBody, 'Response is not valid JSON');
    }

    /**
     * Tests that the add endpoint fails if a criterion weight is missing
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testAddFailMissingWeight()
    {
        $invalidData = $this->formulaData;
        unset($invalidData['criteria'][0]['weight']);
        $this->post($this->addUrl, $invalidData);
        $this->assertBodyIsJson();
        $this->runAddFailureAssertions();
    }

    /**
     * Tests that the add endpoint fails if a criterion metric and/or metric ID is missing
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testAddFailMissingMetric()
    {
        $invalidData = $this->formulaData;
        unset($invalidData['criteria'][0]['metric']['id']);
        unset($invalidData['criteria'][1]['metric']);
        $this->post($this->addUrl, $invalidData);
        $this->assertBodyIsJson();
        $this->runAddFailureAssertions();
    }
}
