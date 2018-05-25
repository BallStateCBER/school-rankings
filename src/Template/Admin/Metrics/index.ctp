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

<div id="metric-manager"></div>

<div class="card mt-5" id="metric-manager-legend">
    <h4 class="card-header">
        Icon Legend
    </h4>
    <div class="card-body d-flex">
        <section>
            <h6 class="card-subtitle mb-2">
                Selectable by users
            </h6>
            <ul class="card-text">
                <li>
                    <span class="far fa-check-circle"></span> Selectable
                </li>
                <li>
                    <span class="fas fa-ban"></span> Not selectable
                </li>
            </ul>
        </section>

        <section>
            <h6 class="card-subtitle mb-2">
                Metric data type
            </h6>
            <ul class="card-text">
                <li>
                    <span class="far fa-chart-bar"></span> Numeric
                </li>
                <li>
                    <span class="far fa-thumbs-up"></span> Boolean
                </li>
            </ul>
        </section>
    </div>
</div>

<script>
    window.metricManager = {
        context: <?= json_encode($context) ?>
    };
</script>
