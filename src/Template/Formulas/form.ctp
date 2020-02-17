<?php
    /**
     * @var AppView $this
     * @var array $counties
     * @var array $gradeLevels
     * @var array $schoolTypes
     */

    use App\View\AppView;
    use Cake\Core\Configure;

    $this->Html->script('/dist/js/formula-form.js', ['block' => 'bottom']);
    $this->Html->css('/jstree/themes/default/style.min.css', ['block' => 'css']);
    $this->Html->css('/dist/css/formula-form.css', ['block' => true]);
    $formDebugMode = Configure::read('debug') || $this->request->getQuery('debug');
?>

<?php $this->append('top'); ?>
<div class="flex-row" id="steps-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-1">
                <img src="/img/icons/iconmonstr-school.svg" alt="" />
            </div>
            <div class="col-3">
                1. Choose what to rank
            </div>
            <div class="col-1">
                <img src="/img/icons/iconmonstr-task.svg" alt="" />
            </div>
            <div class="col-3">
                2. Select ranking criteria
            </div>
            <div class="col-1">
                <img src="/img/icons/iconmonstr-cursor.svg" alt="" />
            </div>
            <div class="col-3">
                3. Click to get your results!
            </div>
        </div>
    </div>
</div>
<?php $this->end(); ?>

<div id="formula-form" <?php if ($formDebugMode): ?>data-debug="1"<?php endif; ?>></div>

<script>
  window.formulaForm = {
    counties: <?= json_encode($counties) ?>,
    schoolTypes: <?= json_encode($schoolTypes) ?>,
    gradeLevels: <?= json_encode($gradeLevels) ?>
  };
</script>
