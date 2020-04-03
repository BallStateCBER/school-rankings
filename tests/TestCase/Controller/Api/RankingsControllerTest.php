<?php
namespace App\Test\TestCase\Controller\Api;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Class RankingsControllerTest
 * @package App\Test\TestCase\Controller\Api
 * @property \App\Model\Table\RankingsTable $Rankings
 */
class RankingsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    private $Rankings;
    public $fixtures = [
        'app.Rankings',
        'app.Formulas',
        'app.Counties',
        'app.SchoolTypes',
        'app.Grades',
        'app.RankingsGrades',
        'app.RankingsCounties',
        'app.RankingsSchoolTypes',
        'app.QueuedJobs',
    ];
    private $addUrl = [
        'prefix' => 'api',
        'controller' => 'Rankings',
        'action' => 'add',
        '_ext' => 'json',
    ];
    private $validData = [
        'countyId' => 1,
        'formulaId' => 1,
        'schoolTypes' => [1],
        'gradeLevels' => [1],
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->Rankings = TableRegistry::getTableLocator()->get('Rankings');
    }

    /**
     * Tests that a call to the add endpoint is successful with valid data
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testAddSuccess()
    {
        $originalCount = $this->Rankings->find()->count();

        $this->post($this->addUrl, $this->validData);
        $this->assertResponseOk();

        $newCount = $this->Rankings->find()->count();
        $this->assertEquals($originalCount + 1, $newCount, 'New ranking was not created');

        /** @var \App\Model\Entity\Ranking $newRanking */
        $newRanking = $this->Rankings
            ->find()
            ->orderDesc('id')
            ->first();
        $expectedJsonResponse = [
            'jobId' => 2,
            'rankingHash' => $newRanking->hash,
            'success' => true,
        ];
        $this->assertJsonStringEqualsJsonString(
            json_encode($expectedJsonResponse),
            (string)$this->_response->getBody(),
            'Unexpected API response'
        );
    }

    /**
     * Tests that the add endpoint fails if the provided county is invalid
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testAddFailInvalidCounty()
    {
        $invalidData = $this->validData;
        $invalidData['countyId'] = 999;
        $this->post($this->addUrl, $invalidData);
        $this->assertResponseError('Response was not in the 4xx range');

        $originalCount = $this->Rankings->find()->count();
        $newCount = $this->Rankings->find()->count();
        $this->assertEquals($originalCount, $newCount, 'New ranking record was created, but shouldn\'t have been');

        $this->assertResponseContains('Invalid county selected');
    }
}