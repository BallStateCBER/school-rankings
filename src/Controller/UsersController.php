<?php
namespace App\Controller;

use CakeDC\Users\Controller\Traits\LoginTrait;
use CakeDC\Users\Controller\Traits\RegisterTrait;

/**
 * Users Controller
 *
 * @property \App\Model\Table\UsersTable $Users
 *
 * @method \App\Model\Entity\User[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class UsersController extends AppController
{
    use LoginTrait {
        login as pluginLogin;
        logout as pluginLogout;
    }
    use RegisterTrait {
        register as public pluginRegister;
    }

    /**
     * Initialization hook method.
     *
     * @return void
     * @throws \Exception
     */
    public function initialize()
    {
        parent::initialize();

        $this->Auth->allow();
    }

    /**
     * Register page
     *
     * @return void
     */
    public function register()
    {
        // Copy email to username because CakeDC/Users requires a unique, non-blank username
        $this->request = $this->request->withData('username', $this->request->getData('email'));

        $this->pluginRegister();

        // Don't send the password back to the form on error
        $this->request = $this->request->withData('password', '');
        $this->request = $this->request->withData('password_confirm', '');
    }

    /**
     * Login page
     *
     * @return void
     */
    public function login()
    {
        $this->pluginLogin();
    }

    /**
     * Logout page
     *
     * @return void
     */
    public function logout()
    {
        $this->pluginLogout();
    }
}
