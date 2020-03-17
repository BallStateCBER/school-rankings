<?php
/**
 * @var \App\View\AppView $this
 * @var array $counties
 * @var array $gradeLevels
 * @var array $schoolTypes
 */
$this->Html->script('/dist/js/ranking-results.js', ['block' => 'bottom']);
$this->Html->css('/dist/css/formula-form.css', ['block' => true]);
$rankingHash = $this->request->getParam('hash');
?>

<div id="ranking-results" data-ranking-hash="<?= $rankingHash ?>"></div>

<script>
    window.formulaForm = {
        counties: <?= json_encode($counties) ?>,
        schoolTypes: <?= json_encode($schoolTypes) ?>,
        gradeLevels: <?= json_encode($gradeLevels) ?>
    };
</script>
