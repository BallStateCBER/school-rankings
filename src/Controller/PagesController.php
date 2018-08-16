<?php
namespace App\Controller;

use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\View\Exception\MissingTemplateException;

class PagesController extends AppController
{
    /**
     * Home page
     *
     * @return void
     */
    public function home()
    {
        $this->set('title_for_layout', null);
    }
}
