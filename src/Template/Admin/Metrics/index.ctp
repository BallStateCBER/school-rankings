<?php
    /**
     * @var \Cake\View\View $this
     * @var array $metricGroups
     */
    $this->Html->css('/jstree/themes/default/style.min.css', ['block' => 'css']);
    $this->Html->css('/dist/css/metric-manager.css', ['block' => true]);
    $this->Html->script('/dist/js/metric-manager.js', ['block' => 'bottom']);
?>

<div id="metric-manager"></div>

<script>
    window.metricManager = {
        context: <?= json_encode($context) ?>
    };
</script>
