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

    <?= $this->Html->css('cake.css') ?>
    <?= $this->Html->css('style.css') ?>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <script>window.jQuery || document.write('<script src="/js/jquery-3.3.1.min.js"><\/script>')</script>

    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js" integrity="sha384-uefMccjFJAIv6A+rW+L4AHf99KvxDjWSu1z9VI8SKNVmz4sk7buKt/6v9KI65qnm" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css" integrity="sha384-9gVQ4dYFwwWSjIDZnLEWnxCjeSWFphJiwGPXr1jddIhOegiu1FwO5qRGvFXOdJZ4" crossorigin="anonymous">

    <script>
        if (! $.fn.modal) {
            document.write('<script src="/bootstrap/js/bootstrap.min.js"><\/script>');
            document.write('<link rel="stylesheet" href="/bootstrap/css/bootstrap.min.css">');
        }
    </script>

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
