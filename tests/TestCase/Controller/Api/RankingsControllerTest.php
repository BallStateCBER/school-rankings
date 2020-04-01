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
    }
}
