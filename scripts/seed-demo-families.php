<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (($config['environment'] ?? null) !== 'development') {
    fwrite(STDERR, "Demo data can only be seeded in the development environment.\n");
    exit(1);
}

$pdo = $database->connection();
$existing = (int) $pdo->query('SELECT count(*) FROM members WHERE family_head_id IS NOT NULL')->fetchColumn();
if ($existing > 0) {
    echo "Demo families already seeded ({$existing} family members).\n";
    exit(0);
}

mt_srand(2027);
$byUnit = [];
$rows = $pdo->query("SELECT id, current_unit_id FROM members WHERE is_demo AND membership_status = 'active' ORDER BY id")->fetchAll();
foreach ($rows as $row) {
    $byUnit[$row['current_unit_id']][] = (int) $row['id'];
}

$update = $pdo->prepare('UPDATE members SET family_head_id = :head WHERE id = :id');
$pdo->beginTransaction();
$families = 0;
$linked = 0;
foreach ($byUnit as $ids) {
    shuffle($ids);
    // Around 60% of each unit's individuals are placed into families of 2-6 people.
    $budget = (int) floor(count($ids) * 0.6);
    while ($budget >= 2 && count($ids) >= 2) {
        $size = min(mt_rand(2, 6), count($ids), $budget);
        $group = array_splice($ids, 0, $size);
        $head = array_shift($group);
        foreach ($group as $memberId) {
            $update->execute(['head' => $head, 'id' => $memberId]);
            $linked++;
        }
        $families++;
        $budget -= $size;
    }
}
$pdo->commit();
echo "Seeded {$families} demo families with {$linked} family members.\n";
