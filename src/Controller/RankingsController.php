<?php
namespace App\Controller;

use Cake\Datasource\Exception\RecordNotFoundException;
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
        if (!$this->Rankings->exists(['hash' => $hash])) {
            throw new RecordNotFoundException('Sorry, but that set of school rankings was not found.');
        }

        /** @var \App\Model\Entity\Ranking $ranking */
        $ranking = $this->Rankings->find()
            ->select(['for_school_districts', 'created'])
            ->where(['hash' => $hash])
            ->first();
        $createdDate = $ranking->created;

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
            'titleForLayout' => $ranking->for_school_districts ?
                'School District Ranking Results' :
                'School Ranking Results',
            'createdDate' => $createdDate,
        ]);
    }
}
