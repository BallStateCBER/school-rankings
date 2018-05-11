<?php
namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Network\Exception\BadRequestException;

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
}
