<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (($config['environment'] ?? null) !== 'development') {
    fwrite(STDERR, "Demo data can only be changed in the development environment.\n");
    exit(1);
}

$male = ['Lalrinpuia', 'Lalthanzara', 'Lalduhawma', 'Zoramthanga', 'Lalramliana', 'Vanlalruata', 'Lalnunmawia', 'Zothansanga', 'Lalremruata', 'Laltlanzova',
    'Vanlalhruaia', 'Lalhmingliana', 'Lalfakzuala', 'Rochhuanga', 'Zosangliana', 'Lalzarmawia', 'Malsawmtluanga', 'Lalmuanpuia', 'Zorammawia', 'Lalrampana',
    'Hmingthanmawia', 'Lalsawmliana', 'Remruatfela', 'Lalbiakzuala', 'Vanlalzova', 'Thangzuala', 'Lalchhuanmawia', 'Zodinpuia', 'Lalawmpuia', 'Biakmawia',
    'Lalhriatpuia', 'Chawngthuama', 'Lalenmawia', 'Saitluanga', 'Ramdinthara', 'Lalrosanga', 'Zohmingthanga', 'Lalthansanga', 'Vanlalngaia', 'Laldinthara',
    'Lalrinmawia', 'Rualthankhuma', 'Lalmalsawma', 'Zoramchhana', 'Lalhmunsiama', 'Vanlalliana', 'Lalropuia', 'Kapthianga', 'Lalnghinglova', 'Thanzauva'];
$female = ['Lalmuanpuii', 'Zothanpuii', 'Lalnunsangi', 'Vanlalhruaii', 'Lalremsiami', 'Lalhlimpuii', 'Zonunsangi', 'Lalhmangaihi', 'Rohmingliani', 'Lalrinsangi',
    'Malsawmi', 'Lalduhsaki', 'Vanlalawmpuii', 'Zoramsangi', 'Lalbiakmawii', 'Ngurthanzuali', 'Lalrintluangi', 'Zirsangzeli', 'Lalnunmawii', 'Hmangaihzuali',
    'Lalchhandami', 'Lalthlamuani', 'Lalzuiliani', 'Zothansangi', 'Lalramchhani', 'Vanlalruati', 'Biakthangi', 'Lalpekhlui', 'Lalfamkimi', 'Remsiami',
    'Lalthazuali', 'Lalramzauvi', 'Zokimi', 'Mawii', 'Lalhmingmawii', 'Lalnuntluangi', 'Vanneihtluangi', 'Lalrempuii', 'Zosiami', 'Lalawmpuii',
    'Thanmawii', 'Lalsiamkimi', 'Rinsangi', 'Lalmawipuii', 'Zuali', 'Lalthansangi', 'Hmingsangi', 'Lalduhzuali', 'Vanlalzuali', 'Chhuanthangi'];
$clans = ['Ralte', 'Hmar', 'Chhangte', 'Sailo', 'Pachuau', 'Fanai', 'Khiangte', 'Hnamte', 'Colney', 'Zadeng', 'Renthlei', 'Tlau', 'Hlawndo', 'Chawngthu',
    'Vanchhawng', 'Lalremsanga', 'Zathang', 'Thangluah', 'Lalbiaknunga', 'Bawitlung', 'Darngawn', 'Lianhna', 'Ngente', 'Sailung', 'Tochhawng', 'Varte', 'Zote', 'Leiri'];

$pdo = $database->connection();
mt_srand(2028);
$rows = $pdo->query('SELECT id, family_head_id FROM members WHERE is_demo ORDER BY id')->fetchAll();

$surnames = [];
$pick = static function (array $list): string {
    return $list[mt_rand(0, count($list) - 1)];
};
$surname = static function (int $key) use (&$surnames, $clans, $pick): string {
    return $surnames[$key] ??= $pick($clans);
};

$update = $pdo->prepare('UPDATE members SET full_name = :name, gender = :gender WHERE id = :id');
$pdo->beginTransaction();
$count = 0;
foreach ($rows as $row) {
    $isFemale = mt_rand(1, 100) <= 49;
    $given = $pick($isFemale ? $female : $male);
    // Family members share the head's clan name.
    $clan = $surname((int) ($row['family_head_id'] ?? $row['id']));
    $update->execute(['name' => $given . ' ' . $clan, 'gender' => $isFemale ? 'female' : 'male', 'id' => $row['id']]);
    $count++;
}
$pdo->commit();
echo "Renamed {$count} demo individuals with Mizo names.\n";
