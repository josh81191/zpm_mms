<?php require __DIR__ . '/labels.php'; ?>
<section class="content-card">
    <h2>Party and classification</h2>
    <div class="breakdown" aria-label="Individuals by party">
        <?php foreach ($partyLabels as $key => $label): ?>
            <div><span class="party-badge party-<?= $key ?>"><?= $e($label) ?></span><strong><?= number_format((int) ($partyCounts[$key] ?? 0)) ?></strong></div>
        <?php endforeach; ?>
    </div>
    <h3>Non-ZPM classification</h3>
    <div class="breakdown" aria-label="Non-ZPM individuals by classification">
        <?php foreach ($classificationLabels as $key => $label): ?>
            <div><span class="class-badge class-<?= $key ?>"><?= $e($label) ?></span><strong><?= number_format((int) ($classificationCounts[$key] ?? 0)) ?></strong></div>
        <?php endforeach; ?>
    </div>
    <?php require __DIR__ . '/legend.php'; ?>
</section>
