<?php
/**
 * @var View $this
 * @var string $containerClass
 * @var string $containerId
 * @var string $titleForLayout
 */
$containerClass = $containerClass ?? 'container';
$containerId = $containerId ?? '';

use Cake\View\View; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= $this->element('analytics') ?>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        Indiana School Rankings
        <?php if ($titleForLayout): ?>
            - <?= $titleForLayout ?>
        <?php endif; ?>
    </title>
    <?= $this->Html->meta('icon') ?>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->Html->css('/dist/css/main.css') ?>
    <?= $this->Html->script('/dist/js/main.js') ?>
    <?= $this->fetch('script') ?>
</head>
<body>
    <?= $this->element('nav_header') ?>
    <?= $this->Flash->render() ?>
    <div class="<?= $containerClass ?> clearfix" id="<?= $containerId ?>">
        <?php if ($titleForLayout): ?>
            <h1>
                <?= $titleForLayout ?>
            </h1>
        <?php endif; ?>
        <?= $this->fetch('content') ?>
    </div>
    <footer class="d-flex">
        <ul class="list-unstyled list-inline mx-auto justify-content-center">
            <li class="list-inline-item">
                <?= $this->Html->link(
                    'Terms of Use',
                    [
                        'controller' => 'Pages',
                        'action' => 'terms'
                    ]
                ) ?>
            </li>
            <li class="list-inline-item">
                <?= $this->Html->link(
                    'Privacy Policy',
                    [
                        'controller' => 'Pages',
                        'action' => 'privacy'
                    ]
                ) ?>
            </li>
        </ul>
    </footer>
    <script>
        $(document).ready(function () {
            <?= $this->fetch('buffered'); ?>
        });
    </script>
    <?= $this->fetch('bottom') ?>
</body>
</html>
