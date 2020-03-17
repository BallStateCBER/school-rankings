<?php
namespace App\Test\TestCase\Controller;

use App\Test\Fixture\RankingsFixture;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\RankingsController Test Case
 *
 * @uses \App\Controller\RankingsController
 */
class RankingsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.Cities',
        'app.Counties',
        'app.Formulas',
        'app.Grades',
        'app.Ranges',
        'app.RankingResultsSchoolDistricts',
        'app.RankingResultsSchools',
        'app.Rankings',
        'app.RankingsCities',
        'app.RankingsCounties',
        'app.RankingsGrades',
        'app.RankingsRanges',
        'app.RankingsSchoolDistricts',
        'app.RankingsSchoolTypes',
        'app.RankingsStates',
        'app.SchoolDistricts',
        'app.SchoolTypes',
        'app.States',
        'app.Users',
    ];

    /**
     * Test view method
     *
     * @return void
     * @throws \PHPUnit\Exception
     */
    public function testView()
    {
        $ranking = (new RankingsFixture())->records[0];
        $this->get([
            'controller' => 'Rankings',
            'action' => 'view',
            'hash' => $ranking['hash'],
        ]);
        $this->assertResponseOk();
        $this->assertResponseContains('<html lang="en">');
    }
}
