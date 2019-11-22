<?php
namespace App\Controller;

class PagesController extends AppController
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

        $this->Auth->allow();
    }

    /**
     * Home page
     *
     * @return void
     */
    public function home()
    {
        $this->set('title_for_layout', null);
    }

    /**
     * Terms of use page
     *
     * @return void
     */
    public function terms()
    {
        $this->set('title_for_layout', 'Terms of Use');
    }

    /**
     * Privacy policy page
     *
     * @return void
     */
    public function privacy()
    {
        $this->set('title_for_layout', 'Privacy Policy');
    }
}
