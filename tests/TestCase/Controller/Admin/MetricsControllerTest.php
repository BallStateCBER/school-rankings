<?php
namespace App\Test\TestCase\Controller\Admin;

use App\Model\Context\Context;
use App\Test\TestCase\ApplicationTest;
use Cake\Routing\Router;

/**
 * MetricsControllerTest class
 */
class MetricsControllerTest extends ApplicationTest
{
    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.Metrics',
        'app.Statistics',
        'app.Users'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
    }

    /**
     * Tests request being blocked for anonymous users
     *
     * @return void
     * @throws \PHPUnit\Exception
     * @throws \Exception
     */
    public function testIndexFailNotLoggedIn()
    {
        foreach (Context::getContexts() as $context) {
            $url = Router::url([
                'prefix' => 'admin',
                'controller' => 'Metrics',
                'action' => 'index',
                $context
            ]);

            $this->get($url);
            $this->assertRedirectToLogin();
        }
    }

    /**
     * Tests request being blocked for non-admin users
     *
     * @return void
     * @throws \PHPUnit\Exception
     * @throws \Exception
     */
    public function testIndexFailNotAuthorized()
    {
        $this->session($this->normalUser);
        foreach (Context::getContexts() as $context) {
            $url = Router::url([
                'prefix' => 'admin',
                'controller' => 'Metrics',
                'action' => 'index',
                $context
            ]);

            $this->get($url);
            $this->assertRedirect('/');
        }
    }

    /**
     * Tests request succeeding for authorized users
     *
     * @return void
     * @throws \PHPUnit\Exception
     * @throws \Exception
     */
    public function testIndexSucceed()
    {
        $this->session($this->adminUser);
        foreach (Context::getContexts() as $context) {
            $url = Router::url([
                'prefix' => 'admin',
                'controller' => 'Metrics',
                'action' => 'index',
                $context
            ]);

            $this->get($url);
            $response = $this->_response->getBody()->__toString();
            $this->assertResponseOk('Response body: ' . $response);
        }
    }
}
