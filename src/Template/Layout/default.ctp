<?php
/**
 * @var View $this
 * @var string $containerClass
 * @var string $containerId
 * @var string $pageId
 * @var string $titleForLayout
 */

use Cake\View\View;

$containerClass = $containerClass ?? 'container';
$containerId = $containerId ?? '';
?>
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
<body id="<?= $pageId ?>">
    <?= $this->element('nav_header') ?>
    <?= $this->Flash->render() ?>
    <div class="<?= $containerClass ?> clearfix" id="<?= $containerId ?>">
        <?= $this->fetch('top') ?>
        <?php if ($titleForLayout): ?>
            <h1>
                <?= $titleForLayout ?>
            </h1>
        <?php endif; ?>
        <?= $this->fetch('content') ?>
    </div>
    <script>
        $(document).ready(function () {
            <?= $this->fetch('buffered'); ?>
        });
    </script>
    <?= $this->fetch('bottom') ?>
    <?= $this->element('footer') ?>
</body>
</html>
