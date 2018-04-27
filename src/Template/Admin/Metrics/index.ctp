<?php
    /**
     * @var \App\View\Helper\MetricsHelper $metricsHelper
     * @var \Cake\View\View $this
     * @var array $metricGroups
     */
    $this->Html->script('/jstree/jstree.min.js', ['block' => 'script']);
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

<div id="add-modal" class="modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form>
                <div class="modal-header">
                    <h5 class="modal-title">Add metric</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <fieldset class="form-group">
                        <label for="metric-name">Name:</label>
                        <input id="metric-name" type="text" class="form-control" required="required" />
                    </fieldset>
                    <fieldset class="form-group">
                        <label for="metric-description">Description:</label>
                        <textarea id="metric-description" class="form-control" rows="3"></textarea>
                    </fieldset>
                    <fieldset class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="type" id="numeric-radio" value="numeric" checked="checked" />
                            <label class="form-check-label" for="numeric-radio">
                                Numeric
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="type" id="boolean-radio" value="boolean">
                            <label class="form-check-label" for="boolean-radio">
                                Boolean
                            </label>
                        </div>
                    </fieldset>
                    <fieldset class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="selectable-checkbox" checked="checked" />
                            <label class="form-check-label" for="selectable-checkbox">
                                Selectable
                            </label>
                        </div>
                    </fieldset>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Add</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
