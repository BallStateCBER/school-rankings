<?php
use \Cake\Routing\Router;
$pages = [
    'Home' => Router::url('/'),
    'Formula Form' => Router::url([
        'prefix' => false,
        'controller' => 'Formulas',
        'action' => 'form'
    ])
];
$adminPages = [
    'School Metrics' => Router::url([
        'prefix' => 'admin',
        'controller' => 'Metrics',
        'action' => 'index',
        'school'
    ]),
    'District Metrics' => Router::url([
        'prefix' => 'admin',
        'controller' => 'Metrics',
        'action' => 'index',
        'district'
    ])
];
$isActive = function ($url, \Cake\Http\ServerRequest $request) {
    return explode('?', $url)[0] == explode('?', $request->getRequestTarget())[0];
};
?>

<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <h1>
        <a class="navbar-brand" href="<?= $pages['Home'] ?>">
            Indiana School Rankings
        </a>
    </h1>

    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar-pages"
            aria-controls="navbar-pages" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbar-pages">
        <ul class="navbar-nav mr-auto">
            <?php foreach ($pages as $label => $url): ?>
                <li class="nav-item <?= $isActive($url, $this->request) ? 'active' : '' ?>">
                    <a class="nav-link" href="<?= $url ?>">
                        <?= $label ?>
                        <?php if ($isActive($url, $this->request)): ?>
                            <span class="sr-only">
                                (current)
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>

            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navbar-admin-dropdown" role="button"
                   data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Admin
                </a>
                <div class="dropdown-menu" aria-labelledby="navbar-admin-dropdown">
                    <?php foreach ($adminPages as $label => $url): ?>
                        <a class="dropdown-item"
                           href="<?= $url ?>">
                            <?= $label ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </li>
        </ul>
    </div>
</nav>