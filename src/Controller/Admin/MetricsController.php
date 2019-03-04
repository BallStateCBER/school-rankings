<?php
namespace App\Controller\Admin;

use App\Controller\AppController;
use App\Model\Context\Context;
use App\Model\Table\MetricsTable;
use Cake\Http\Exception\BadRequestException;

/**
 * Class MetricsController
 * @package App\Controller\Admin
 * @property MetricsTable $Metrics
 */
class MetricsController extends AppController
{
    /**
     * Displays the metrics manager, used for adding, editing, and removing metrics
     *
     * @param string $context Either 'school' or 'district'
     * @return void
     * @throws BadRequestException
     */
    public function index($context)
    {
        if (!in_array($context, ['school', 'district'])) {
            throw new BadRequestException('Unrecognized metric context: ' . $context);
        }

        $this->set([
            'context' => $context,
            'titleForLayout' => $context == 'school' ? 'School Metrics' : 'School District Metrics'
        ]);
    }

    /**
     * Displays both entire metric trees for printing
     *
     * @throws \Exception
     * @return void
     */
    public function tree()
    {
        $metrics = [];
        foreach (Context::getContexts() as $context) {
            $this->Metrics->setScope($context);
            $metrics[$context] = $this->Metrics
                ->find('threaded')
                ->select(['id', 'name', 'parent_id'])
                ->where(['context' => $context])
                ->enableHydration(false)
                ->toArray();
        }
        $this->set([
            'metrics' => $metrics
        ]);
    }
}
