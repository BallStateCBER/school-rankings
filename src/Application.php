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
use App\Command\CleanAssociationsCommand;
use App\Command\DeleteCommand;
use App\Command\FixDistrictAssociationsCommand;
use App\Command\FixMetricTreeCommand;
use App\Command\FixPercentValuesCommand;
use App\Command\FixSelectableCommand;
use App\Command\ImportClosuresCommand;
use App\Command\ImportLocationsCommand;
use App\Command\ImportStatsCommand;
use App\Command\ImportStatsStatusCommand;
use App\Command\LocationMergeCommand;
use App\Command\MetricMergeCommand;
use App\Command\MetricParentMergeCommand;
use App\Command\MetricReparentCommand;
use App\Command\MetricTreeCleanCommand;
use App\Command\PopulateCodeTablesCommand;
use App\Command\PopulateElasticsearchCommand;
use App\Command\PopulateLocationOriginCommand;
use App\Command\SpeedTestElasticsearchCommand;
use App\Command\UpdateIdoeCodesCommand;
use App\Shell\RankTestShell;
use Cake\Console\CommandCollection;
use Cake\Core\Configure;
use Cake\Core\Exception\MissingPluginException;
use Cake\ElasticSearch\Plugin as ElasticSearchPlugin;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue as MiddlewareQueueAlias;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use DebugKit\Plugin;

/**
 * Application setup class.
 *
 * This defines the bootstrapping logic and middleware layers you
 * want to use in your application.
 */
class Application extends BaseApplication
{
    /**
     * {@inheritDoc}
     */
    public function bootstrap()
    {
        // Call parent to load bootstrap from files.
        parent::bootstrap();

        $this->addPlugin('Queue');
        $this->addPlugin(ElasticSearchPlugin::class);
        $this->addPlugin('CakeDC/Users');
        Configure::write('Users.config', ['users']);

        if (PHP_SAPI === 'cli') {
            $this->bootstrapCli();
        }

        /*
         * Only try to load DebugKit in development mode
         * Debug Kit should not be installed on a production system
         */
        if (Configure::read('debug')) {
            $this->addPlugin(Plugin::class);
        }
    }

    /**
     * Loads required plugins for the CLI environment
     *
     * @return void
     */
    protected function bootstrapCli()
    {
        try {
            $this->addPlugin('Bake');
        } catch (MissingPluginException $e) {
            // Do not halt if the plugin is missing
        }
        $this->addPlugin('Migrations');
    }

    /**
     * Setup the middleware queue your application will use.
     *
     * @param MiddlewareQueueAlias $middlewareQueue The middleware queue to setup.
     * @return MiddlewareQueueAlias The updated middleware queue.
     */
    public function middleware($middlewareQueue)
    {
        $middlewareQueue
            // Catch any exceptions in the lower layers,
            // and make an error page/response
            ->add(ErrorHandlerMiddleware::class)

            // Handle plugin/theme assets like CakePHP normally does.
            ->add(new AssetMiddleware([
                'cacheTime' => Configure::read('Asset.cacheTime'),
            ]))

            // Add routing middleware.
            // Routes collection cache enabled by default, to disable route caching
            // pass null as cacheConfig, example: `new RoutingMiddleware($this)`
            // you might want to disable this cache in case your routing is extremely simple
            ->add(new RoutingMiddleware($this));

        return $middlewareQueue;
    }

    /**
     * Defines the commands and subcommands in this application
     *
     * @param CommandCollection $commands Collection of commands
     * @return CommandCollection
     */
    public function console($commands)
    {
        $commands->autoDiscover();

        $commands->add('check-locations', CheckLocationsCommand::class);
        $commands->add('check-stats', CheckStatsCommand::class);
        $commands->add('clean-associations', CleanAssociationsCommand::class);
        $commands->add('delete', DeleteCommand::class);
        $commands->add('fix-district-associations', FixDistrictAssociationsCommand::class);
        $commands->add('fix-metric-tree', FixMetricTreeCommand::class);
        $commands->add('fix-percent-values', FixPercentValuesCommand::class);
        $commands->add('fix-selectable', FixSelectableCommand::class);
        $commands->add('import-closures', ImportClosuresCommand::class);
        $commands->add('import-locations', ImportLocationsCommand::class);
        $commands->add('import-stats', ImportStatsCommand::class);
        $commands->add('import-stats-status', ImportStatsStatusCommand::class);
        $commands->add('location-merge', LocationMergeCommand::class);
        $commands->add('metric-merge', MetricMergeCommand::class);
        $commands->add('metric-parent-merge', MetricParentMergeCommand::class);
        $commands->add('metric-reparent', MetricReparentCommand::class);
        $commands->add('metric-tree-clean', MetricTreeCleanCommand::class);
        $commands->add('populate-code-tables', PopulateCodeTablesCommand::class);
        $commands->add('populate-es', PopulateElasticsearchCommand::class);
        $commands->add('populate-location-origin', PopulateLocationOriginCommand::class);
        $commands->add('rank-test', RankTestShell::class);
        $commands->add('speed-test-es', SpeedTestElasticsearchCommand::class);
        $commands->add('update-idoe-codes', UpdateIdoeCodesCommand::class);

        return $commands;
    }
}
