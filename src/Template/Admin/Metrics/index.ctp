<?php
    /**
     * @var \App\View\Helper\MetricsHelper $metricsHelper
     * @var \Cake\View\View $this
     * @var array $metricGroups
     */
    $this->Html->css('/jstree/themes/default/style.min.css', ['block' => 'css']);
    $metricsHelper = $this->loadHelper('Metrics');
    $this->Html->css('/dist/css/metricManager.css', ['block' => true]);
    $this->Html->script('/dist/js/metricManager.js', ['block' => true]);
?>

<?php foreach ($metricGroups as $group): ?>
    <section data-context="<?= $group['context'] ?>">
        <h2>
            <?= $group['header'] ?>
        </h2>
        <div id="<?= $group['containerId'] ?>"></div>
    </section>
<?php endforeach; ?>
