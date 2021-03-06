<?php
/**
 * Routes configuration
 *
 * In this file, you set up routes to your controllers and their actions.
 * Routes are very important mechanism that allows you to freely connect
 * different URLs to chosen controllers and their actions (functions).
 *
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use Cake\Routing\Route\DashedRoute;

/**
 * The default class to use for all routes
 *
 * The following route classes are supplied with CakePHP and are appropriate
 * to set as the default:
 *
 * - Route
 * - InflectedRoute
 * - DashedRoute
 *
 * If no call is made to `Router::defaultRouteClass()`, the class used is
 * `Route` (`Cake\Routing\Route\Route`)
 *
 * Note that `Route` does not do any inflections on URLs which will result in
 * inconsistently cased URLs when used with `:plugin`, `:controller` and
 * `:action` markers.
 *
 */
Router::defaultRouteClass(DashedRoute::class);

Router::extensions(['json']);

Router::scope('/', function (RouteBuilder $routes) {
    // Pages
    $routes->connect('/', ['controller' => 'Pages', 'action' => 'home']);

    // Users
    $routes->connect('/register', ['controller' => 'Users', 'action' => 'register']);
    $routes->connect('/login', ['controller' => 'Users', 'action' => 'login']);
    $routes->connect('/logout', ['controller' => 'Users', 'action' => 'logout']);

    // Formulas
    $routes->connect('/rank', ['controller' => 'Formulas', 'action' => 'form']);

    // Rankings
    $routes->connect('/ranking/:hash', ['controller' => 'Rankings', 'action' => 'view'])
        ->setPass(['hash']);

    /**
     * Connect catchall routes for all controllers.
     *
     * Using the argument `DashedRoute`, the `fallbacks` method is a shortcut for
     *    `$routes->connect('/:controller', ['action' => 'index'], ['routeClass' => 'DashedRoute']);`
     *    `$routes->connect('/:controller/:action/*', [], ['routeClass' => 'DashedRoute']);`
     *
     * Any route class can be used with this method, such as:
     * - DashedRoute
     * - InflectedRoute
     * - Route
     * - Or your own route class
     *
     * You can remove these routes once you've connected the
     * routes you want in your application.
     */
    $routes->fallbacks(DashedRoute::class);
});

Router::prefix('admin', function (RouteBuilder $routes) {
    $routes->fallbacks(DashedRoute::class);

    foreach (['school', 'district'] as $context) {
        $routes->connect('/metrics/' . $context, ['controller' => 'Metrics', 'action' => 'index', $context]);
    }
});

Router::prefix('api', function (RouteBuilder $routes) {
    $routes->fallbacks(DashedRoute::class);

    // Rankings
    $routes->connect('/rankings/get/:hash', ['controller' => 'Rankings', 'action' => 'get'])
        ->setPass(['hash'])
        ->setExtensions(['json']);
});
