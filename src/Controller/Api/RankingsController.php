<?php
namespace App\Controller\Api;

use App\Controller\AppController;
use App\Model\Entity\Ranking;
use App\Model\Table\RankingsTable;
use Cake\Database\Expression\QueryExpression;
use Cake\Http\Exception\BadRequestException;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;

/**
 * Class RankingsController
 * @package App\Controller\Api
 * @property RankingsTable $rankingsTable
 */
class RankingsController extends AppController
{
    private $rankingsTable;

    /**
     * Initialization method
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();
        $this->rankingsTable = TableRegistry::getTableLocator()->get('Rankings');
    }

    /**
     * Adds a ranking record to be processed by a background task
     *
     * @return void
     * @throws \Exception
     * @throws BadRequestException
     */
    public function add()
    {
        $context = $this->request->getData('context');

        $ranking = $this->rankingsTable->newEntity([
            'user_id' => null,
            'formula_id' => $this->request->getData('formulaId'),
            'school_type_id' => null,
            'for_school_districts' => $context == 'district',
            'hash' => RankingsTable::generateHash()
        ]);

        $countyIds = [$this->request->getData('countyId')];
        $ranking->counties = TableRegistry::getTableLocator()
            ->get('Counties')
            ->find()
            ->where([
                function (QueryExpression $exp) use ($countyIds) {
                    return $exp->in('id', $countyIds);
                }
            ])
            ->toArray();

        $success = (bool)$this->rankingsTable->save($ranking);
        if ($success) {
            $id = $ranking->id;
        } else {
            $this->logRankingError($ranking);
            $id = null;
        }

        $this->set([
            '_serialize' => ['id', 'success'],
            'id' => $id,
            'success' => $success
        ]);
    }

    /**
     * Logs error in creating ranking
     *
     * @param Ranking $ranking Ranking entity
     * @return void
     */
    private function logRankingError($ranking)
    {
        $errors = $ranking->getErrors();
        $passesRules = $this->rankingsTable->checkRules($ranking, 'create');
        if ($errors || !$passesRules) {
            $errorMsg = 'There was an error creating that ranking.';
            if ($errors) {
                foreach (Hash::flatten($errors) as $field => $errorMsg) {
                    $errorMsg .= "\n - $errorMsg ($field)";
                }
            }
            if (!$passesRules) {
                $errorMsg .= "\n - Did not pass application rules";
            }
            Log::write('error', $errorMsg);
        }
    }
}
