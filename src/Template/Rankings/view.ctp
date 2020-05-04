<?php
/**
 * @var \App\View\AppView $this
 * @var array $counties
 * @var array $gradeLevels
 * @var array $schoolTypes
 * @var \Cake\I18n\FrozenTime $createdDate
 */
$this->Html->script('/dist/js/ranking-results.js', ['block' => 'bottom']);
$this->Html->css('/dist/css/formula-form.css', ['block' => true]);
$rankingHash = $this->request->getParam('hash');
$formattedDate = $this->Time->format($createdDate, 'MMMM d, Y', false, 'America/New_York');
?>

<p>
    Generated on <?= $formattedDate ?>
</p>

<div id="ranking-results" data-ranking-hash="<?= $rankingHash ?>"></div>

<script>
    window.formulaForm = {
        counties: <?= json_encode($counties) ?>,
        schoolTypes: <?= json_encode($schoolTypes) ?>,
        gradeLevels: <?= json_encode($gradeLevels) ?>
    };
</script>
