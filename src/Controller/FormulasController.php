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
     * The form for adding or editing a formula
     *
     * @return \Cake\Http\Response|void
     */
    public function form()
    {
        $countiesTable = TableRegistry::getTableLocator()->get('Counties');
        $schoolTypesTable = TableRegistry::getTableLocator()->get('SchoolTypes');
        $gradesTable = TableRegistry::getTableLocator()->get('Grades');
        $this->set([
            'counties' => $countiesTable->find()
                ->select(['Counties.id', 'Counties.name'])
                ->matching('States', function (Query $q) {
                    return $q->where(['States.name' => 'Indiana']);
                })
                ->orderAsc('Counties.name')
                ->toArray(),
            'gradeLevels' => array_values($gradesTable->getAll()),
            'schoolTypes' => $schoolTypesTable->find()
                ->select(['id', 'name'])
                ->all()
                ->toArray(),
            'titleForLayout' => 'Create a Ranking Formula'
        ]);
    }
}
