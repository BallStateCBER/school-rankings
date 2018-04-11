<?php
    /**
     * @var \App\View\Helper\MetricsHelper $metricsHelper
     * @var \Cake\View\View $this
     * @var array $metricGroups
     */
    $this->Html->script('/jstree/jstree.min.js', ['block' => 'script']);
    $this->Html->css('/jstree/themes/default/style.min.css', ['block' => 'css']);
    $metricsHelper = $this->loadHelper('Metrics');
?>

<?php foreach ($metricGroups as $group): ?>
    <section data-context="<?= $group['context'] ?>">
        <h2>
            <?= $group['header'] ?>
        </h2>
        <div id="<?= $group['containerId'] ?>">

        </div>
    </section>
    <?php $this->Html->script('metricManager', ['block' => true]) ?>
    <?php $this->append('buffered'); ?>
        $('#<?= $group['containerId'] ?>').jstree({
            'core': {
                'data': <?= json_encode($metricsHelper->getJsTreeData($group['metrics'])) ?>,
                'check_callback': true
            },
            'plugins': [
                'contextmenu',
                'dnd',
                'sort',
                'state',
                'wholerow'
            ],
            'contextmenu': metricManager.contextMenuConfig
        });
    <?php $this->end(); ?>
<?php endforeach; ?>
