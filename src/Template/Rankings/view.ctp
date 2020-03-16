<?php
/**
 * @var \App\View\AppView $this
 * @var array $counties
 * @var array $gradeLevels
 * @var array $schoolTypes
 */
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
