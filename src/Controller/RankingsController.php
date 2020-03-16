<?php
namespace App\Controller;

use Cake\ORM\Query;
use Cake\ORM\TableRegistry;

/**
 * Rankings Controller
 *
 * @property \App\Model\Table\RankingsTable $Rankings
 */
class RankingsController extends AppController
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
     * Page for viewing a specific set of ranking results
     *
     * @param string $hash Ranking hash
     * @return void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($hash)
    {
        /** @var \App\Model\Table\GradesTable $gradesTable */
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
            'titleForLayout' => '',
        ]);
    }
}
