<?php
/**
 * @var \App\View\AppView $this
 */
$this->extend('default');
?>
<?= $this->fetch('content') ?>
<p>
    <?= $this->Html->link(
        'Back',
        'javascript:history.back()',
        [
            'class' => 'btn btn-primary float-right',
            'escape' => false
        ]
    ) ?>
</p>
