<?php
namespace App\Controller\Api;

use App\Controller\AppController;
use App\Ranking\Ranking;
use Cake\Database\Expression\QueryExpression;
use Cake\Http\Exception\BadRequestException;
use Cake\ORM\TableRegistry;

class RankingsController extends AppController
{
    /**
     * Generates and returns a ranking according to provided formula parameters
     *
     * @return void
     * @throws \Exception
     * @throws BadRequestException
     */
    public function generate()
    {
        $context = $this->request->getData('context');
        $countyIds = [$this->request->getData('countyId')];
        $locations = TableRegistry::getTableLocator()
            ->get('Counties')
            ->find()
            ->where([
                function (QueryExpression $exp) use ($countyIds) {
                    return $exp->in('id', $countyIds);
                }
            ])
            ->all();
        $criteriaData = $this->request->getData('criteria');
        if (empty($criteriaData)) {
            throw new BadRequestException('Criteria data missing');
        }
        $criteria = [];
        $criteriaTable = TableRegistry::getTableLocator()->get('Criteria');
        foreach ($criteriaData as $criterionData) {
            $criteria[] = $criteriaTable->newEntity([
                'formula_id' => null,
                'metric_id' => $criterionData['metric']['metricId'],
                'weight' => 100,
                'preference' => 'high'
            ]);
        }
        $ranking = new Ranking([
            'context' => $context,
            'locations' => $locations,
            'criteria' => $criteria
        ]);

        $ranking = $ranking->getRanking();

        $this->set([
            '_serialize' => ['ranking'],
            'ranking' => $ranking
        ]);
    }
}
