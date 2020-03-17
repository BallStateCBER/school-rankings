<?php
namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Event\Event;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/3.0/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{

    /**
     * Initialization hook method.
     *
     * @return void
     * @throws \Exception
     */
    public function initialize()
    {
        parent::initialize();

        $this->loadComponent('RequestHandler', ['enableBeforeRedirect' => false]);
        $this->loadComponent('Flash');
        $this->loadComponent('CakeDC/Users.UsersAuth');

        $this->set('titleForLayout', null);
    }

    /**
     * Before render callback.
     *
     * @param \Cake\Event\Event $event The beforeRender event.
     * @return \Cake\Network\Response|null|void
     */
    public function beforeRender(Event $event)
    {
        $pathParts = [
            $this->getRequest()->getParam('controller'),
            $this->getRequest()->getParam('action'),
        ];
        $pathParts = array_filter($pathParts, 'strlen');
        $pathParts = array_map('strtolower', $pathParts);
        $pageId = 'page-' . implode('-', $pathParts);

        $this->set([
            'authUser' => $this->Auth->user(),
            'pageId' => $pageId,
        ]);
    }
}
