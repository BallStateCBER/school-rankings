<?php
    /**
     * @var \App\View\Helper\MetricsHelper $metricsHelper
     * @var \Cake\View\View $this
     * @var array $metricGroups
     */
    $this->Html->css('/jstree/themes/default/style.min.css', ['block' => 'css']);
    $metricsHelper = $this->loadHelper('Metrics');
    $this->Html->css('/dist/css/metricManager.css', ['block' => true]);
    $this->Html->script('/dist/js/metricManager.js', ['block' => 'bottom']);
?>

<div id="metric-manager"></div>

<script>
    window.metricManager = {
        context: <?= json_encode($context) ?>
    };
</script>
