<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (($config['environment'] ?? null) !== 'development') {
    fwrite(STDERR, "Demo data can only be seeded in the development environment.\n");
    exit(1);
}

$districts = [
    ['AIZ', 'Aizawl', 23.7271, 92.7176, ['Aizawl', 'Sairang', 'Durtlang', 'Bawngkawn', 'Zemabawk']],
    ['CHA', 'Champhai', 23.4745, 93.3255, ['Champhai', 'Khawbung', 'Ngopa', 'Zokhawthar', 'Hnahlan']],
    ['HNA', 'Hnahthial', 22.9660, 92.9240, ['Hnahthial', 'Thingsai', 'Lungsen', 'Dawn', 'Muallianpui']],
    ['KHA', 'Khawzawl', 23.1760, 93.1900, ['Khawzawl', 'Vanzau', 'Khawhai', 'Chalrang', 'Rabung']],
    ['KOL', 'Kolasib', 24.2230, 92.6760, ['Kolasib', 'Kawnpui', 'Bairabi', 'Vairengte', 'Bilkhawthlir']],
    ['LAW', 'Lawngtlai', 22.5280, 92.8920, ['Lawngtlai', 'Sangau', 'Chawngte', 'Pangzawl', 'Diltlang']],
    ['LUN', 'Lunglei', 22.8870, 92.7500, ['Lunglei', 'Pukpui', 'Zobawk', 'Ramthar', 'Theiriat']],
    ['MAM', 'Mamit', 23.9270, 92.4890, ['Mamit', 'Zawlnuam', 'West Phaileng', 'Kawrthah', 'Kanghmun']],
    ['SAI', 'Saitual', 23.9700, 92.5700, ['Saitual', 'Phullen', 'Suangpuilawn', 'Darlawn', 'Keifang']],
    ['SER', 'Serchhip', 23.3430, 92.8460, ['Serchhip', 'Thenzawl', 'North Vanlaiphai', 'Buangpui', 'Chhingchhip']],
    ['SIA', 'Siaha', 22.4880, 92.9810, ['Siaha', 'Tuipang', 'Niawhtlang', 'Chapi', 'Tipa']],
];

$connection = $database->connection();
$connection->beginTransaction();

try {
    $admin = $connection->prepare('SELECT id FROM user_accounts WHERE email = :email');
    $admin->execute(['email' => 'admin@zpm.test']);
    $adminId = $admin->fetchColumn();
    if ($adminId === false) {
        throw new RuntimeException('Create development demo users before seeding demo data.');
    }

    $districtUpsert = $connection->prepare(
        'INSERT INTO districts (district_code, name, representative_location, is_demo)
         VALUES (:district_code, :name, public.ST_SetSRID(public.ST_MakePoint(:longitude, :latitude), 4326), TRUE)
         ON CONFLICT (district_code) DO UPDATE
         SET name = EXCLUDED.name,
             representative_location = EXCLUDED.representative_location,
             is_demo = TRUE,
             updated_at = CURRENT_TIMESTAMP
         RETURNING id'
    );
    $blockUpsert = $connection->prepare(
        'INSERT INTO blocks (district_id, block_code, name, representative_location, is_demo)
         VALUES (:district_id, :block_code, :name, public.ST_SetSRID(public.ST_MakePoint(:longitude, :latitude), 4326), TRUE)
         ON CONFLICT (block_code) DO UPDATE
         SET name = EXCLUDED.name,
             representative_location = EXCLUDED.representative_location,
             is_demo = TRUE,
             updated_at = CURRENT_TIMESTAMP
         RETURNING id'
    );
    $unitUpsert = $connection->prepare(
        'INSERT INTO units (block_id, unit_code, name, unit_type, representative_location, remarks, is_demo)
         VALUES (:block_id, :unit_code, :name, :unit_type, public.ST_SetSRID(public.ST_MakePoint(:longitude, :latitude), 4326), :remarks, TRUE)
         ON CONFLICT (unit_code) DO UPDATE
         SET name = EXCLUDED.name,
             representative_location = EXCLUDED.representative_location,
             remarks = EXCLUDED.remarks,
             is_demo = TRUE,
             updated_at = CURRENT_TIMESTAMP
         RETURNING id'
    );

    $unitIds = [];
    foreach ($districts as [$districtCode, $districtName, $latitude, $longitude, $localities]) {
        $districtUpsert->execute([
            'district_code' => $districtCode,
            'name' => $districtName,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
        $districtId = $districtUpsert->fetchColumn();

        $blockIds = [];
        foreach (['urban' => 'Urban Demo Block', 'rural' => 'Rural Demo Block'] as $blockSuffix => $blockName) {
            $blockUpsert->execute([
                'district_id' => $districtId,
                'block_code' => "{$districtCode}-{$blockSuffix}",
                'name' => "{$districtName} {$blockName}",
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);
            $blockIds[$blockSuffix] = $blockUpsert->fetchColumn();
        }

        foreach ($localities as $index => $locality) {
            $offset = ($index + 1) * 0.006;
            $unitCode = sprintf('%s-U%02d', $districtCode, $index + 1);
            $unitUpsert->execute([
                'block_id' => $blockIds[$index < 2 ? 'urban' : 'rural'],
                'unit_code' => $unitCode,
                'name' => $locality,
                'unit_type' => $index === 0 ? 'locality' : 'village',
                'latitude' => $latitude + (($index % 2 === 0) ? $offset : -$offset),
                'longitude' => $longitude + (($index % 2 === 0) ? -$offset : $offset),
                'remarks' => 'Synthetic demo reference point only. Verify approved boundaries and coordinates before operational use.',
            ]);
            $unitIds[] = $unitUpsert->fetchColumn();
        }
    }

    $existingDemoCount = (int) $connection->query('SELECT count(*) FROM members WHERE is_demo = TRUE')->fetchColumn();
    $memberUpsert = $connection->prepare(
        'INSERT INTO members (
            member_code, full_name, gender, enrollment_date, current_unit_id, membership_status,
            status_effective_date, remarks, is_demo, created_by_user_id, updated_by_user_id
         ) VALUES (
            :member_code, :full_name, :gender, :enrollment_date, :current_unit_id, :membership_status,
            :status_effective_date, :remarks, TRUE, :user_id, :user_id
         )
         ON CONFLICT (member_code) DO NOTHING
         RETURNING id'
    );
    $eventInsert = $connection->prepare(
        'INSERT INTO membership_events (
            member_id, event_type, new_value, effective_date, recorded_by_user_id, reason, is_demo
         ) VALUES (
            :member_id, :event_type, CAST(:new_value AS JSONB), :effective_date, :user_id, :reason, TRUE
         )'
    );

    for ($number = $existingDemoCount + 1; $number <= 1200; $number++) {
        $unitId = $unitIds[($number - 1) % count($unitIds)];
        $enrollmentDate = (new DateTimeImmutable('today'))->sub(new DateInterval('P' . (($number * 11) % 1095) . 'D'))->format('Y-m-d');
        $status = $number % 19 === 0 ? 'inactive' : 'active';
        $memberUpsert->execute([
            'member_code' => sprintf('DEMO-%05d', $number),
            'full_name' => sprintf('Synthetic Demo Member %05d', $number),
            'gender' => ['female', 'male', 'not-specified'][$number % 3],
            'enrollment_date' => $enrollmentDate,
            'current_unit_id' => $unitId,
            'membership_status' => $status,
            'status_effective_date' => $enrollmentDate,
            'remarks' => 'Synthetic demo record. Not a real person.',
            'user_id' => $adminId,
        ]);
        $memberId = $memberUpsert->fetchColumn();
        if ($memberId !== false) {
            $eventInsert->execute([
                'member_id' => $memberId,
                'event_type' => 'new-enrollment',
                'new_value' => json_encode(['membership_status' => $status], JSON_THROW_ON_ERROR),
                'effective_date' => $enrollmentDate,
                'user_id' => $adminId,
                'reason' => 'Synthetic demo data seed.',
            ]);
        }
    }

    $connection->commit();
    fwrite(STDOUT, "Seeded 11 districts, 22 demo blocks, 55 locality units, and 1,200 synthetic members.\n");
} catch (Throwable $exception) {
    $connection->rollBack();
    throw $exception;
}

