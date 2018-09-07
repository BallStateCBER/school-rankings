<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     3.3.0
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App;

use App\Command\CheckLocationsCommand;
use App\Command\CheckStatsCommand;
use App\Command\FixDistrictAssociationsCommand;
use App\Command\FixMetricTreeCommand;
use App\Command\FixPercentValuesCommand;
use App\Command\FixSelectableCommand;
use App\Command\ImportLocationsCommand;
use App\Command\ImportStatsCommand;
use App\Command\ImportStatsStatusCommand;
use App\Command\MetricMergeCommand;
use App\Command\MetricParentMergeCommand;
use App\Command\MetricReparentCommand;
use App\Command\MetricTreeCleanCommand;
use App\Shell\RankTestShell;
use Cake\Core\Configure;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\BaseApplication;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;

/**
 * Application setup class.
 *
 * This defines the bootstrapping logic and middleware layers you
 * want to use in your application.
 */
class Application extends BaseApplication
{
    /**
     * Application bootstrap method
     *
     * @return void
     */
    public function bootstrap()
    {
        parent::bootstrap();

        $this->addPlugin('Queue');

        if (Configure::read('debug')) {
            $this->addPlugin('DebugKit');
        }
    }

    /**
     * Setup the middleware queue your application will use.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to setup.
     * @return \Cake\Http\MiddlewareQueue The updated middleware queue.
     */
    public function middleware($middlewareQueue)
    {
        $middlewareQueue
            // Catch any exceptions in the lower layers,
            // and make an error page/response
            ->add(ErrorHandlerMiddleware::class)

            // Handle plugin/theme assets like CakePHP normally does.
            ->add(AssetMiddleware::class)

            // Add routing middleware.
            ->add(new RoutingMiddleware($this));

        return $middlewareQueue;
    }

    /**
     * Defines the commands and subcommands in this application
     *
     * @param \Cake\Console\CommandCollection $commands Collection of commands
     * @return \Cake\Console\CommandCollection
     */
    public function console($commands)
    {
        $commands->autoDiscover();

        $commands->add('check-locations', CheckLocationsCommand::class);
        $commands->add('check-stats', CheckStatsCommand::class);
        $commands->add('fix-district-associations', FixDistrictAssociationsCommand::class);
        $commands->add('fix-metric-tree', FixMetricTreeCommand::class);
        $commands->add('fix-percent-values', FixPercentValuesCommand::class);
        $commands->add('fix-selectable', FixSelectableCommand::class);
        $commands->add('import-locations', ImportLocationsCommand::class);
        $commands->add('import-stats', ImportStatsCommand::class);
        $commands->add('import-stats-status', ImportStatsStatusCommand::class);
        $commands->add('metric-merge', MetricMergeCommand::class);
        $commands->add('metric-parent-merge', MetricParentMergeCommand::class);
        $commands->add('metric-reparent', MetricReparentCommand::class);
        $commands->add('metric-tree-clean', MetricTreeCleanCommand::class);
        $commands->add('rank-test', RankTestShell::class);

        return $commands;
    }
}
