<?php
namespace App\Controller;

use Cake\ORM\Query;
use Cake\ORM\TableRegistry;

/**
 * Formulas Controller
 *
 * @property \App\Model\Table\FormulasTable $Formulas
 *
 * @method \App\Model\Entity\Formula[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class FormulasController extends AppController
{

    /**
     * The form for adding or editing a formula
     *
     * @return \Cake\Http\Response|void
     */
    public function form()
    {
        $countiesTable = TableRegistry::getTableLocator()->get('Counties');
        $this->set([
            'counties' => $countiesTable->find()
                ->select(['Counties.id', 'Counties.name'])
                ->matching('States', function (Query $q) {
                    return $q->where(['States.name' => 'Indiana']);
                })
                ->orderAsc('Counties.name')
                ->toArray(),
            'titleForLayout' => 'Create a Ranking Formula'
        ]);
    }
}