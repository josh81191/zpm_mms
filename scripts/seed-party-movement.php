<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (($config['environment'] ?? null) !== 'development') {
    fwrite(STDERR, "Demo data can only be seeded in the development environment.\n");
    exit(1);
}

$pdo = $database->connection();
$note = 'Synthetic demo party change';
$pdo->beginTransaction();
$pdo->exec("DELETE FROM party_classification_history WHERE note = '{$note}'");

mt_srand(2026);
$classes = ['green', 'yellow', 'red'];
$pools = [];
$stmt = $pdo->query("SELECT id, party, classification FROM members WHERE is_demo ORDER BY id");
foreach ($stmt->fetchAll() as $row) {
    $pools[$row['party']][] = $row;
}
foreach ($pools as &$pool) {
    shuffle($pool);
}
unset($pool);

$insert = $pdo->prepare(
    "INSERT INTO party_classification_history (member_id, previous_party, new_party, previous_classification, new_classification, note, recorded_at)
     VALUES (:id, :prev, :new, :prevc, :newc, :note, :at)"
);

$today = new DateTimeImmutable('now');
$monthStart = $today->modify('first day of this month')->setTime(10, 0);
$count = 0;

for ($m = 0; $m < 12; $m++) {
    $growth = 1 + $m / 11;
    // [from, to, number of moves this month]: ZPM grows steadily, MNF edges up,
    // Congress and BJP shrink, Other stays near zero.
    $flows = [
        ['congress', 'zpm', (int) round(5 * $growth) + mt_rand(0, 2)],
        ['bjp', 'zpm', (int) round(2 * $growth) + mt_rand(0, 1)],
        ['other', 'zpm', mt_rand(0, 1)],
        ['mnf', 'zpm', mt_rand(0, 1)],
        ['congress', 'mnf', 2 + mt_rand(0, 1)],
        ['bjp', 'mnf', mt_rand(0, 1)],
        ['zpm', 'mnf', mt_rand(0, 1)],
        ['mnf', 'other', mt_rand(0, 1)],
    ];
    $monthDate = $monthStart->modify('-' . (11 - $m) . ' months');
    $maxDay = $m === 11 ? max(0, (int) $today->format('j') - 2) : 26;

    foreach ($flows as [$from, $to, $n]) {
        for ($i = 0; $i < $n; $i++) {
            $person = array_pop($pools[$to]);
            if ($person === null) {
                continue;
            }
            $at = $monthDate->modify('+' . mt_rand(0, $maxDay) . ' days +' . mt_rand(0, 8) . ' hours');
            $insert->execute([
                'id' => $person['id'],
                'prev' => $from,
                'new' => $to,
                'prevc' => $from === 'zpm' ? null : $classes[mt_rand(0, 2)],
                'newc' => $person['classification'] ?: null,
                'note' => $note,
                'at' => $at->format('Y-m-d H:i:sP'),
            ]);
            $count++;
        }
    }
}
$pdo->commit();
echo "Seeded {$count} synthetic party changes.\n";
