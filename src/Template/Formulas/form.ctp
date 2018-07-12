<?php
/** @var array $counties */
    $this->Html->script('/dist/js/formula-form.js', ['block' => 'bottom']);
?>

<div id="formula-form"></div>

<script>
  window.formulaForm = {
    counties: <?= json_encode($counties) ?>
  };
</script>
