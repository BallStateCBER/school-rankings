<?php
    /** @var array $counties */
    $this->Html->script('/dist/js/formula-form.js', ['block' => 'bottom']);
    $this->Html->css('/jstree/themes/default/style.min.css', ['block' => 'css']);
    $this->Html->css('/dist/css/formula-form.css', ['block' => true]);
?>

<div id="formula-form"></div>

<script>
  window.formulaForm = {
    counties: <?= json_encode($counties) ?>
  };
</script>
