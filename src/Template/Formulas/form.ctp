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

<div id="formula-form" <?php if ($formDebugMode): ?>data-debug="1"<?php endif; ?>></div>

<script>
  window.formulaForm = {
    counties: <?= json_encode($counties) ?>,
    schoolTypes: <?= json_encode($schoolTypes) ?>,
    gradeLevels: <?= json_encode($gradeLevels) ?>
  };
</script>
