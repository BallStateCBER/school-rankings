<?php
namespace App\Controller;

use App\Model\Entity\Formula;
use App\Model\Table\FormulasTable;
use App\Model\Table\GradesTable;
use Cake\Datasource\ResultSetInterface;
use Cake\Http\Response;
use Cake\ORM\Query;
use Cake\ORM\TableRegistry;
use Exception;

/**
 * Formulas Controller
 *
 * @property FormulasTable $Formulas
 *
 * @method Formula[]|ResultSetInterface paginate($object = null, array $settings = [])
 */
class FormulasController extends AppController
{
    /**
     * Initialization hook method.
     *
     * @return void
     * @throws Exception
     */
    public function initialize()
    {
        parent::initialize();

        $this->Auth->allow();
    }

    /**
     * The form for adding or editing a formula
     *
     * @return Response|void
     */
    public function form()
    {
        /** @var GradesTable $gradesTable */
        $gradesTable = TableRegistry::getTableLocator()->get('Grades');
        $countiesTable = TableRegistry::getTableLocator()->get('Counties');
        $schoolTypesTable = TableRegistry::getTableLocator()->get('SchoolTypes');
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
            'titleForLayout' => 'Create a Ranking Formula',
        ]);
    }
}
