<?php
namespace App\Test\TestCase\Controller\Api;

use App\Model\Context\Context;
use App\Model\Table\SchoolTypesTable;
use App\Test\Fixture\CriteriaFixture;
use App\Test\Fixture\RankingsFixture;
use App\Test\Fixture\RankingsGradesFixture;
use App\Test\Fixture\RankingsSchoolTypesFixture;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Cake\Utility\Hash;

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
        'app.Counties',
        'app.Criteria',
        'app.Formulas',
        'app.Grades',
        'app.Metrics',
        'app.QueuedJobs',
        'app.RankingResultsSchoolDistricts',
        'app.RankingResultsSchoolDistrictsStatistics',
        'app.RankingResultsSchools',
        'app.RankingResultsSchoolsStatistics',
        'app.Rankings',
        'app.RankingsCounties',
        'app.RankingsGrades',
        'app.RankingsSchoolTypes',
        'app.SchoolDistricts',
        'app.Schools',
        'app.SchoolsGrades',
        'app.SchoolTypes',
        'app.Statistics',
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
     * Tests that the add endpoint fails if the provided county ID is invalid
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testAddFailInvalidCounty()
    {
        $originalCount = $this->Rankings->find()->count();
        $invalidData = $this->validData;
        $invalidData['countyId'] = 999;
        $this->post($this->addUrl, $invalidData);
        $this->assertResponseError('Response was not in the 4xx range');
        $this->assertNoNewRecordsCreated($originalCount);
        $this->assertResponseContains('Invalid county selected');
    }

    /**
     * Tests that the add endpoint fails if the provided formula ID is invalid
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testAddFailInvalidFormula()
    {
        $originalCount = $this->Rankings->find()->count();
        $invalidData = $this->validData;
        $invalidData['formulaId'] = 999;
        $this->post($this->addUrl, $invalidData);
        $this->assertResponseError('Response was not in the 4xx range');
        $this->assertNoNewRecordsCreated($originalCount);
        $this->assertResponseContains('Invalid formula selected');
    }

    /**
     * Asserts that the current count of records is the same as the count of records before any requests were made
     *
     * @param int $originalCount Original count of records
     * @return void
     */
    private function assertNoNewRecordsCreated($originalCount)
    {
        $newCount = $this->Rankings->find()->count();
        $this->assertEquals($originalCount, $newCount, 'New ranking record was created, but shouldn\'t have been');
    }

    /**
     * Tests that the add endpoint fails for the school context if no school types are provided
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testAddForSchoolFailNoSchoolTypes()
    {
        $originalCount = $this->Rankings->find()->count();
        $invalidData = $this->validData;
        $invalidData['schoolTypes'] = [];
        $this->post($this->addUrl, $invalidData);
        $this->assertResponseError('Response was not in the 4xx range');
        $this->assertNoNewRecordsCreated($originalCount);
        $this->assertResponseContains('Please specify at least one type of school');
    }

    /**
     * Tests that the add endpoint succeeds for the school context if no grade levels are provided
     *
     * If 'gradeLevels' is empty, that is interpreted as meaning "all grades"
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testAddForSchoolSuccessNoGrades()
    {
        $originalCount = $this->Rankings->find()->count();
        $data = $this->validData;
        $data['gradeLevels'] = [];
        $this->post($this->addUrl, $data);
        $this->assertResponseOk('Response was not in the 4xx range');
        $newCount = $this->Rankings->find()->count();
        $this->assertEquals($originalCount + 1, $newCount, 'New ranking was not created');
    }

    /**
     * Tests a successful response from the /rankings/status?jobId=1 endpoint
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testGetStatusSuccess()
    {
        $this->get([
            'prefix' => 'api',
            'controller' => 'Rankings',
            'action' => 'status',
            '?' => ['jobId' => 1],
            '_ext' => 'json',
        ]);
        $this->assertResponseOk();
        $this->assertResponseContains('"progress":');
        $this->assertResponseContains('"status":');
        $this->assertResponseContains('"rankingUrl":"https:');
    }

    /**
     * Tests that an error response is returned from the /rankings/status endpoint if an invalid job ID is provided
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testGetStatusFailInvalidJob()
    {
        $this->get([
            'prefix' => 'api',
            'controller' => 'Rankings',
            'action' => 'status',
            '?' => ['jobId' => 999],
            '_ext' => 'json',
        ]);
        $this->assertResponseError();
    }

    /**
     * Tests a successful response from the /rankings/get/{rankingHash}.json endpoint
     *
     * @throws \PHPUnit\Exception
     * @return void
     */
    public function testGetRankingSuccess()
    {
        $rankingsFixture = new RankingsFixture();
        $ranking = $rankingsFixture->records[0];
        $this->get([
            'prefix' => 'api',
            'controller' => 'Rankings',
            'action' => 'get',
            'hash' => $ranking['hash'],
            '_ext' => 'json',
        ]);

        $this->assertResponseOk();

        $fullBaseUrl = Configure::read('App.fullBaseUrl');
        $expectedFormUrl = "$fullBaseUrl/rank?r={$ranking['hash']}";
        $this->assertResponseContains(sprintf(
            '"formUrl":"%s"',
            str_replace('/', '\/', $expectedFormUrl)
        ));

        $this->assertResponseContains('"inputSummary":');

        $this->assertResponseContains(sprintf(
            '"context":"%s"',
            $ranking['for_school_districts'] ? Context::DISTRICT_CONTEXT : Context::SCHOOL_CONTEXT
        ));

        $expectedRankingUrl = "$fullBaseUrl/ranking/{$ranking['hash']}";
        $this->assertResponseContains(sprintf(
            '"rankingUrl":"%s"',
            str_replace('/', '\/', $expectedRankingUrl)
        ));

        $rankingsSchoolTypesFixture = new RankingsSchoolTypesFixture();
        $expectedSchoolTypes = array_filter($rankingsSchoolTypesFixture->records, function ($record) use ($ranking) {
            return $record['ranking_id'] == $ranking['id'];
        });
        $expectedSchoolTypeIds = Hash::extract($expectedSchoolTypes, '{n}.school_type_id');
        $this->assertResponseContains(sprintf(
            '"schoolTypeIds":[%s]',
            implode(',', $expectedSchoolTypeIds)
        ));

        if ($expectedSchoolTypeIds == [SchoolTypesTable::SCHOOL_TYPE_PUBLIC]) {
            $this->assertResponseContains('"onlyPublic":true');
        } else {
            $this->assertResponseContains('"onlyPublic":false');
        }

        $this->assertResponseContains('"countyId":');

        $criteriaFixture = new CriteriaFixture();
        $metricsTable = TableRegistry::getTableLocator()->get('Metrics');
        $formulaId = $ranking['formula_id'];
        $criteria = array_filter($criteriaFixture->records, function ($criterion) use ($formulaId) {
            return $criterion['formula_id'] == $formulaId;
        });

        $criteria = array_map(function ($criterion) use ($metricsTable) {
            /** @var \App\Model\Entity\Metric $metric */
            $metricId = $criterion['metric_id'];
            $metric = $metricsTable->get($metricId);

            return [
                'id' => $criterion['id'],
                'formula_id' => $criterion['formula_id'],
                'weight' => $criterion['weight'],
                'metric' => [
                    'id' => $metric->id,
                    'name' => $metric->name,
                    'path' => $metricsTable
                        ->find('path', ['for' => $metricId])
                        ->select(['id'])
                        ->toArray(),
                ],
            ];
        }, $criteria);
        $expectedCriteria = json_encode(array_values($criteria));
        $this->assertResponseContains('"criteria":' . $expectedCriteria);

        $rankingsGradesFixture = new RankingsGradesFixture();
        $expectedGradeLevels = array_filter($rankingsGradesFixture->records, function ($record) use ($ranking) {
            return $record['ranking_id'] == $ranking['id'];
        });
        $this->assertResponseContains(sprintf(
            '"gradeIds":[%s]',
            implode(Hash::extract($expectedGradeLevels, '{n}.grade_id'))
        ));

        $this->assertResponseContains('"noDataResults":[]', '"noDataResults" not returned');

        $this->assertResponseContains('"results":[', 'No results returned');
        $this->assertResponseContains('"rank":1', 'Results had no ranks');
        $this->assertResponseContains('"statistics":[{"id":', 'Results were missing statistics');
        $this->assertResponseContains('"school":{"id":', 'Results were missing schools');
    }
}
