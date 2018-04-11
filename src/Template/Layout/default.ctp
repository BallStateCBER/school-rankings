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

    <?= $this->Html->css('base.css') ?>
    <?= $this->Html->css('cake.css') ?>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <script>window.jQuery || document.write('<script src="/js/jquery-3.3.1.min.js"><\/script>')</script>
    <?= $this->fetch('script') ?>
</head>
<body>
    <h1>
        Indiana School Rankings
        <?php if ($titleForLayout): ?>
            - <?= $titleForLayout ?>
        <?php endif; ?>
    </h1>
    <?= $this->Flash->render() ?>
    <div class="container clearfix">
        <?= $this->fetch('content') ?>
    </div>
    <footer>
    </footer>
    <script>
        $(document).ready(function () {
            <?= $this->fetch('buffered'); ?>
        });
    </script>
</body>
</html>
