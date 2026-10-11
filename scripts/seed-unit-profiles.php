<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (($config['environment'] ?? null) !== 'development') {
    fwrite(STDERR, "Demo data can only be seeded in the development environment.\n");
    exit(1);
}

$pdo = $database->connection();
$note = 'Synthetic demo unit profile';
if ((int) $pdo->query("SELECT count(*) FROM party_classification_history WHERE note = '{$note}'")->fetchColumn() > 0) {
    echo "Unit profiles were already seeded; nothing to do.\n";
    exit(0);
}

mt_srand(4107);
$classes = ['green', 'yellow', 'red'];
$units = $pdo->query('SELECT id FROM units ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
shuffle($units);
$weak = array_slice($units, 0, 10);
$strong = array_slice($units, 10, 8);

// Members who already have a recorded party change are left alone so the history stays consistent.
$pick = $pdo->prepare(
    "SELECT m.id FROM members m
     WHERE m.current_unit_id = :unit AND m.party = :party AND m.is_demo
       AND NOT EXISTS (SELECT 1 FROM party_classification_history h WHERE h.member_id = m.id)
     ORDER BY m.id"
);
$setParty = $pdo->prepare('UPDATE members SET party = :party, classification = :class, classification_set_at = now(), updated_at = now() WHERE id = :id');
$history = $pdo->prepare(
    'INSERT INTO party_classification_history (member_id, previous_party, new_party, previous_classification, new_classification, note, recorded_at)
     VALUES (:id, :prev, :new, :prevc, :newc, :note, :at)'
);
$inactive = $pdo->prepare("UPDATE members SET membership_status = 'inactive', status_effective_date = CURRENT_DATE - :days::int, updated_at = now() WHERE id = :id");
$older = $pdo->prepare("UPDATE members SET enrollment_date = CURRENT_DATE - :days::int WHERE id = :id AND enrollment_date > CURRENT_DATE - 180");

$move = static function (int $unit, string $from, string $to, int $count, int $earliestMonthsAgo) use ($pick, $setParty, $history, $classes, $note): void {
    $pick->execute(['unit' => $unit, 'party' => $from]);
    $ids = array_slice($pick->fetchAll(PDO::FETCH_COLUMN), 0, $count);
    foreach ($ids as $id) {
        $previousClass = $from === 'zpm' ? null : $classes[mt_rand(0, 2)];
        $newClass = $to === 'zpm' ? null : $classes[mt_rand(0, 2)];
        $setParty->execute(['party' => $to, 'class' => $newClass, 'id' => $id]);
        $at = (new DateTimeImmutable('now'))->modify('-' . mt_rand(5, $earliestMonthsAgo * 30) . ' days');
        $history->execute(['id' => $id, 'prev' => $from, 'new' => $to, 'prevc' => $previousClass, 'newc' => $newClass, 'note' => $note, 'at' => $at->format('Y-m-d H:i:sP')]);
    }
};

$pdo->beginTransaction();
foreach ($weak as $unit) {
    $move((int) $unit, 'zpm', 'mnf', mt_rand(3, 5), 8);
    $move((int) $unit, 'zpm', 'congress', mt_rand(3, 5), 8);
    $pick->execute(['unit' => $unit, 'party' => 'zpm']);
    foreach (array_slice($pick->fetchAll(PDO::FETCH_COLUMN), 0, mt_rand(2, 3)) as $id) {
        $inactive->execute(['days' => mt_rand(20, 120), 'id' => $id]);
    }
    $all = $pdo->prepare('SELECT id FROM members WHERE current_unit_id = :unit AND is_demo');
    $all->execute(['unit' => $unit]);
    foreach ($all->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $older->execute(['days' => mt_rand(200, 400), 'id' => $id]);
    }
}
foreach ($strong as $unit) {
    $move((int) $unit, 'congress', 'zpm', mt_rand(2, 3), 8);
    $move((int) $unit, 'bjp', 'zpm', mt_rand(1, 2), 8);
    $move((int) $unit, 'mnf', 'zpm', mt_rand(1, 2), 8);
}
$pdo->commit();
echo 'Seeded unit profiles: ' . count($weak) . ' weaker units and ' . count($strong) . " stronger units.\n";
