<?php
/**
 * @var \Cake\View\View $this
 * @var string $titleForLayout
 */
?>
<!DOCTYPE html>
<html>
<head>
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
    <div class="container clearfix">
        <?php if ($titleForLayout): ?>
            <h1>
                <?= $titleForLayout ?>
            </h1>
        <?php endif; ?>
        <?= $this->fetch('content') ?>
    </div>
    <footer>
    </footer>
    <script>
        $(document).ready(function () {
            <?= $this->fetch('buffered'); ?>
        });
    </script>
    <?= $this->fetch('bottom') ?>
</body>
</html>
