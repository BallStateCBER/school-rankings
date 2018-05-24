<?php
    /**
     * @var \App\View\Helper\MetricsHelper $metricsHelper
     * @var \Cake\View\View $this
     * @var array $metricGroups
     */
    $this->Html->css('/jstree/themes/default/style.min.css', ['block' => 'css']);
    $metricsHelper = $this->loadHelper('Metrics');
    $this->Html->css('/dist/css/metric-manager.css', ['block' => true]);
    $this->Html->script('/dist/js/metric-manager.js', ['block' => 'bottom']);
?>

<script defer src="https://use.fontawesome.com/releases/v5.0.4/js/all.js"></script>

<div id="metric-manager"></div>

<script>
    window.metricManager = {
        context: <?= json_encode($context) ?>
    };
</script>
