<?php
/** @var array $metrics */
function showTree($metrics) {
    echo '<ul>';
    foreach ($metrics as $metric) {
        echo '<li>';
        echo '<span class="metric">' . $metric['name'] . '</span>';
        echo '<span class="metric-id">' . $metric['id'] . '</span>';

        if ($metric['children']) {
            showTree($metric['children']);
        }

        echo '</li>';
    }
    echo '</ul>';
}
?>

<style>
    .metric-id {
        color: #aaa;
        font-size: 80%;
        margin-left: 10px;
    }
</style>

<?php //pr($metrics); ?>

<?php foreach ($metrics as $context => $metricsSubset): ?>
    <h1>
        <?= ucwords($context) ?>s
    </h1>
    <?php showTree($metricsSubset); ?>
<?php endforeach; ?>
