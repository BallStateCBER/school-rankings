<?php
    /**
     * @var array $counties
     * @var array $gradeLevels
     * @var array $schoolTypes
     */
    $this->Html->script('/dist/js/formula-form.js', ['block' => 'bottom']);
    $this->Html->css('/jstree/themes/default/style.min.css', ['block' => 'css']);
    $this->Html->css('/dist/css/formula-form.css', ['block' => true]);
?>

<div id="formula-form"></div>

<script>
  window.formulaForm = {
    counties: <?= json_encode($counties) ?>,
    schoolTypes: <?= json_encode($schoolTypes) ?>,
    gradeLevels: <?= json_encode($gradeLevels) ?>
  };
</script>
