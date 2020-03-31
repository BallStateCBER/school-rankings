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
                'weight' => 100,
            ],
            [
                'metric' => ['id' => 2],
                'weight' => 100,
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

        $newCount = $this->Formulas->find()->count();
        $this->assertEquals($originalCount + 1, $newCount, 'New formula record was not created');

        $responseBody = $this->_response->getBody();
        $this->assertJson((string)$responseBody, 'Response is not valid JSON');

        $this->assertJsonStringEqualsJsonString(
            json_encode(['success' => true, 'id' => $newCount]),
            (string)$responseBody,
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
        $originalCount = $this->Formulas->find()->count();

        $invalidData = $this->formulaData;
        $invalidData['context'] = 'invalid';

        $this->post($this->addUrl, $invalidData);
        $this->assertResponseError('Response was not in the 4xx range');

        $newCount = $this->Formulas->find()->count();
        $this->assertEquals($originalCount, $newCount, 'New formula record was created, but shouldn\'t have been');

        $responseBody = $this->_response->getBody();
        $this->assertJson((string)$responseBody, 'Response is not valid JSON');

        $this->assertJsonStringEqualsJsonString(
            json_encode(['success' => false, 'id' => null]),
            (string)$responseBody,
            'Unexpected API response'
        );
    }
}
