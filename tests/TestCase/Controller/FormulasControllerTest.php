<?php
namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\FormulasController Test Case
 */
class FormulasControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.Counties',
        'app.States',
        'app.Grades',
        'app.SchoolTypes',
    ];

    /**
     * Tests that the home page can be loaded
     *
     * @return void
     * @throws \PHPUnit\Exception
     */
    public function testForm()
    {
        $this->get([
            'controller' => 'Formulas',
            'action' => 'form',
        ]);
        $this->assertResponseOk();
        $this->assertResponseContains('<html lang="en">');
    }
}
