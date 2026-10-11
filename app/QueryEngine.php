<?php

declare(strict_types=1);

namespace Zpm;

use DateTimeImmutable;
use PDO;
use RuntimeException;

/**
 * Executes a query plan produced by the AI planner. The plan is validated against fixed whitelists;
 * no SQL is ever taken from the model or the user. Results are aggregate counts of registered individuals.
 */
final class QueryEngine
{
    public const DISCLOSURE_THRESHOLD = 5;
    private const MAX_ROWS = 50;

    private const PARTY_LABELS = ['zpm' => 'ZPM', 'mnf' => 'MNF', 'congress' => 'Congress', 'bjp' => 'BJP', 'other' => 'Other'];
    private const CLASS_LABELS = ['green' => 'Green', 'yellow' => 'Yellow', 'red' => 'Red', 'none' => 'Not classified (ZPM)'];
    private const STATUS_LABELS = ['active' => 'Active', 'inactive' => 'Inactive', 'resigned' => 'Resigned', 'deceased' => 'Deceased', 'archived' => 'Archived'];
    private const GENDER_LABELS = ['male' => 'Male', 'female' => 'Female'];

    private const DIMENSIONS = [
        'district' => ['label' => 'District', 'select' => 'd.name', 'group' => 'd.id, d.name'],
        'block' => ['label' => 'Block', 'select' => 'b.name', 'group' => 'b.id, b.name'],
        'unit' => ['label' => 'Unit', 'select' => 'u.name', 'group' => 'u.id, u.name'],
        'party' => ['label' => 'Party', 'select' => 'm.party', 'group' => 'm.party'],
        'classification' => ['label' => 'Classification', 'select' => "COALESCE(m.classification, 'none')", 'group' => "COALESCE(m.classification, 'none')"],
        'status' => ['label' => 'Status', 'select' => 'm.membership_status', 'group' => 'm.membership_status'],
        'gender' => ['label' => 'Gender', 'select' => 'm.gender', 'group' => 'm.gender'],
        'month' => ['label' => 'Month', 'select' => "to_char(date_trunc('month', m.enrollment_date), 'YYYY-MM')", 'group' => "date_trunc('month', m.enrollment_date)"],
        'year' => ['label' => 'Year', 'select' => "to_char(date_trunc('year', m.enrollment_date), 'YYYY')", 'group' => "date_trunc('year', m.enrollment_date)"],
    ];

    private const FROM = 'FROM members m
        INNER JOIN units u ON u.id = m.current_unit_id
        INNER JOIN blocks b ON b.id = u.block_id
        INNER JOIN districts d ON d.id = b.district_id';

    public const EXAMPLES = [
        'How many individuals are registered in each district?',
        'How did monthly registrations change during 2025?',
        'Compare ZPM and MNF members in Aizawl',
        'Which five units have the most registered individuals?',
        'How many women are registered, and where are they most concentrated?',
    ];

    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /** Schema description given to the AI planner. It contains no member data. */
    public function planningGuide(): string
    {
        $districts = $this->connection->query('SELECT name FROM districts ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
        $blocks = (int) $this->connection->query('SELECT count(*) FROM blocks')->fetchColumn();

        return "Dataset: one row per registered individual. Default measure counts individuals.\n"
            . "Set \"measure\": \"individuals\" (default) for how many individuals are registered, e.g. total members within each party = group_by [\"party\"].\n"
            . "Set \"measure\": \"party_flow\" for party migration: individuals who joined / left each party and the net change (group_by may add ONE of month, year, district, block; \"period\" then applies to the date the change was recorded; filters.party limits the parties shown).\n"
            . "Set \"measure\": \"unit_attention\" for which units/areas need attention, support, focus or priority in the next campaign or organising drive (ranks units by a documented administrative score; group_by stays empty; filters.party names the party, default zpm; filters.district / block narrow it; limit = how many units). Prefer a table for this measure.\n"
            . "Set \"measure\": \"party_moves\" for which party people moved from and to (routes such as MNF to ZPM), same group_by options.\n"
            . "group_by (max 2, in order): district, block, unit, party, classification, status, gender, month, year (month/year use registration date).\n"
            . "filters.party: zpm, mnf, congress, bjp, other. filters.classification: green, yellow, red, none (none = ZPM, unclassified). "
            . "filters.status: active, inactive, resigned, deceased, archived. filters.gender: male, female.\n"
            . 'filters.district / block / unit: arrays of exact area names. Districts: ' . implode(', ', $districts) . ". There are {$blocks} blocks and many units; "
            . 'copy block and unit names exactly as the user wrote them.';
    }

    public function answer(string $question, array $plan): array
    {
        if (is_string($plan['refused'] ?? null) && trim($plan['refused']) !== '') {
            throw new RuntimeException(mb_substr(trim($plan['refused']), 0, 300));
        }

        $dimensions = [];
        foreach ((array) ($plan['group_by'] ?? []) as $key) {
            if (is_string($key) && isset(self::DIMENSIONS[$key]) && !in_array($key, $dimensions, true)) {
                $dimensions[] = $key;
            }
        }
        $dimensions = array_slice($dimensions, 0, 2);

        $filters = [];
        $input = (array) ($plan['filters'] ?? []);
        $enums = ['party' => self::PARTY_LABELS, 'classification' => self::CLASS_LABELS, 'status' => self::STATUS_LABELS, 'gender' => self::GENDER_LABELS];
        foreach ($enums as $key => $labels) {
            $values = [];
            foreach ((array) ($input[$key] ?? []) as $value) {
                $value = is_string($value) ? strtolower(trim($value)) : '';
                if (isset($labels[$value]) && !in_array($value, $values, true)) {
                    $values[] = $value;
                }
            }
            if ($values !== []) {
                $filters[$key] = ['values' => $values, 'names' => array_map(static fn (string $v): string => $labels[$v], $values)];
            }
        }
        foreach (['district' => 'districts', 'block' => 'blocks', 'unit' => 'units'] as $type => $table) {
            foreach (array_slice((array) ($input[$type] ?? []), 0, 20) as $name) {
                if (!is_string($name) || trim($name) === '') {
                    continue;
                }
                $statement = $this->connection->prepare("SELECT id, name FROM {$table} WHERE lower(name) = lower(:name)");
                $statement->execute(['name' => trim($name)]);
                $matches = $statement->fetchAll();
                if ($matches === []) {
                    throw new RuntimeException('No ' . $type . ' named "' . mb_substr(trim($name), 0, 60) . '" was found in the register.');
                }
                foreach ($matches as $row) {
                    $filters[$type]['ids'][] = (int) $row['id'];
                    $filters[$type]['names'][] = (string) $row['name'];
                }
            }
        }

        $period = null;
        $range = $plan['period'] ?? null;
        if (is_array($range) && is_string($range['from'] ?? null) && is_string($range['to'] ?? null)) {
            $from = DateTimeImmutable::createFromFormat('!Y-m-d', $range['from']);
            $to = DateTimeImmutable::createFromFormat('!Y-m-d', $range['to']);
            if ($from !== false && $to !== false && $from <= $to) {
                $period = [$from, $to->modify('+1 day'), $from->format('j M Y') . ' to ' . $to->format('j M Y')];
            }
        }

        $order = in_array($plan['order'] ?? null, ['asc', 'desc'], true) ? $plan['order'] : null;
        $limit = is_int($plan['limit'] ?? null) ? max(1, min(self::MAX_ROWS, $plan['limit'])) : null;
        if (($plan['measure'] ?? null) === 'unit_attention' && ($limit === null || $limit < 3)) {
            $limit = 5;
        }
        $showTable = ($plan['show_table'] ?? false) === true;

        if (($plan['measure'] ?? null) === 'unit_attention') {
            return $this->unitAttention($question, $filters, $limit, $showTable);
        }
        if (in_array($plan['measure'] ?? null, ['party_flow', 'party_moves'], true)) {
            return $this->flow($question, $plan['measure'], $dimensions, $filters, $period, $order, $limit, $showTable);
        }

        [$where, $parameters] = $this->buildWhere($filters, $period);
        $rows = $this->run($dimensions, $where, $parameters);
        $total = $this->grandTotal($where, $parameters);

        return $this->present($question, $dimensions, $filters, $period, $rows, $total, $order, $limit, $showTable);
    }

    /** Transparent, unit-level attention indicator for organisational planning; it never scores individuals. */
    private function unitAttention(string $question, array $filters, ?int $limit, bool $showTable): array
    {
        $focus = $filters['party']['values'][0] ?? 'zpm';
        $focusName = self::PARTY_LABELS[$focus];
        unset($filters['party'], $filters['classification'], $filters['status'], $filters['gender']);
        [$where, $parameters] = $this->buildWhere($filters, null);

        $state = $this->connection->prepare("SELECT count(*) AS total, count(*) FILTER (WHERE m.party = :focus) AS focus FROM members m");
        $state->execute(['focus' => $focus]);
        $stateRow = $state->fetch();
        $stateShare = (int) $stateRow['total'] > 0 ? 100 * (int) $stateRow['focus'] / (int) $stateRow['total'] : 0.0;

        $sql = "WITH ev AS (
                    SELECT m2.current_unit_id AS uid,
                           count(*) FILTER (WHERE h.new_party = :f1) - count(*) FILTER (WHERE h.previous_party = :f2) AS net
                    FROM party_classification_history h
                    INNER JOIN members m2 ON m2.id = h.member_id
                    WHERE h.previous_party IS NOT NULL AND h.previous_party <> h.new_party
                      AND h.recorded_at >= now() - INTERVAL '12 months'
                    GROUP BY m2.current_unit_id
                )
                SELECT u.name AS unit, b.name AS block, d.name AS district, count(*) AS total,
                       count(*) FILTER (WHERE m.party = :f3) AS focus,
                       count(*) FILTER (WHERE m.membership_status <> 'active') AS inactive,
                       count(*) FILTER (WHERE m.enrollment_date >= CURRENT_DATE - 180) AS recent,
                       COALESCE(max(ev.net), 0) AS net
                " . self::FROM . " LEFT JOIN ev ON ev.uid = u.id {$where}
                GROUP BY u.id, u.name, b.name, d.name
                HAVING count(*) >= 10";
        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters + ['f1' => $focus, 'f2' => $focus, 'f3' => $focus]);

        $rows = [];
        foreach ($statement->fetchAll() as $r) {
            $total = (int) $r['total'];
            $share = 100 * (int) $r['focus'] / $total;
            $net = (int) $r['net'];
            $inactiveShare = 100 * (int) $r['inactive'] / $total;
            $recent = (int) $r['recent'];
            $score = max(0.0, $stateShare - $share) + 2 * max(0, -$net) + 0.5 * $inactiveShare + ($recent === 0 ? 5 : 0);
            $reasons = [];
            if ($stateShare - $share >= 3) {
                $reasons[] = $focusName . ' share is ' . round($share, 1) . '%, below the statewide ' . round($stateShare, 1) . '%';
            }
            if ($net < 0) {
                $reasons[] = 'net loss of ' . (-$net) . ' ' . $focusName . ' members in the last 12 months';
            }
            if ($inactiveShare >= 10) {
                $reasons[] = 'inactive share is ' . round($inactiveShare, 1) . '%';
            }
            if ($recent === 0) {
                $reasons[] = 'no new registrations in the last 6 months';
            }
            $rows[] = [
                'labels' => [$r['unit'] . ' (' . $r['block'] . ', ' . $r['district'] . ')'],
                'score' => $score,
                'reasons' => $reasons === [] ? 'no specific concern; ranked relatively higher than the other units' : implode('; ', $reasons),
                'cells' => [number_format($score, 1), round($share, 1) . '%', ($net > 0 ? '+' : '') . $net, round($inactiveShare, 1) . '%', (string) $recent],
            ];
        }
        usort($rows, static fn (array $a, array $b): int => [$b['score'], $a['labels'][0]] <=> [$a['score'], $b['labels'][0]]);
        $cap = $limit ?? 10;
        $rows = array_slice($rows, 0, $cap);

        $interpretation = ['Units ranked by attention score for ' . $focusName];
        foreach (['district' => 'District', 'block' => 'Block', 'unit' => 'Unit'] as $key => $label) {
            if (isset($filters[$key])) {
                $interpretation[] = $label . ': ' . implode(', ', $filters[$key]['names']);
            }
        }

        return [
            'question' => $question,
            'interpretation' => $interpretation,
            'total' => (int) $stateRow['total'],
            'show_table' => $rows !== [],
            'columns' => ['Unit', 'Attention score', $focusName . ' share of unit', $focusName . ' net change (12 months)', 'Inactive share', 'New registrations (6 months)'],
            'rows' => array_map(static fn (array $r): array => ['labels' => $r['labels'], 'n' => null, 'pct' => null, 'cells' => $r['cells'], 'reasons' => $r['reasons']], $rows),
            'notes' => [
                'Attention score = percentage points below the statewide ' . $focusName . ' share (' . round($stateShare, 1) . '%) + 2 for each net ' . $focusName . ' member lost in the last 12 months + half the inactive share (%) + 5 if no registrations in the last 6 months. Higher means more attention.',
                'This is an administrative indicator of record patterns for the units listed. It is not a prediction about any person, and only units with at least 10 registered individuals are ranked.',
            ],
            'scope' => 'units ranked by attention score for ' . $focusName,
            'measure' => 'unit_attention',
        ];
    }

    /** Recorded party changes: joined / left / net per party, or counts of each from→to route. */
    private function flow(string $question, string $measure, array $dimensions, array $filters, ?array $period, ?string $order, ?int $limit, bool $showTable): array
    {
        $extraKey = null;
        foreach ($dimensions as $key) {
            if (in_array($key, ['month', 'year', 'district', 'block'], true)) {
                $extraKey = $key;
                break;
            }
        }
        $extraSql = [
            'month' => "to_char(date_trunc('month', h.recorded_at), 'YYYY-MM')",
            'year' => "to_char(date_trunc('year', h.recorded_at), 'YYYY')",
            'district' => 'd.name',
            'block' => 'b.name',
        ];
        $parties = $filters['party']['values'] ?? [];
        unset($filters['party']);
        [$where, $parameters] = $this->buildWhere($filters, null);
        $clauses = ['h.previous_party IS NOT NULL', 'h.previous_party <> h.new_party'];
        if ($where !== '') {
            $clauses[] = substr($where, 6);
        }
        if ($period !== null) {
            $clauses[] = 'h.recorded_at >= :flow_from AND h.recorded_at < :flow_to';
            $parameters[':flow_from'] = $period[0]->format('Y-m-d');
            $parameters[':flow_to'] = $period[1]->format('Y-m-d');
        }
        $extra = $extraKey === null ? 'NULL' : $extraSql[$extraKey];
        $base = 'SELECT h.previous_party, h.new_party, ' . $extra . ' AS x FROM party_classification_history h
                 INNER JOIN members m ON m.id = h.member_id
                 INNER JOIN units u ON u.id = m.current_unit_id
                 INNER JOIN blocks b ON b.id = u.block_id
                 INNER JOIN districts d ON d.id = b.district_id
                 WHERE ' . implode(' AND ', $clauses);

        $partyFilter = '';
        if ($parties !== []) {
            $names = [];
            foreach ($parties as $i => $party) {
                $parameters[':fp' . $i] = $party;
                $names[] = ':fp' . $i;
            }
            $partyFilter = $measure === 'party_flow'
                ? ' WHERE party IN (' . implode(', ', $names) . ')'
                : ' WHERE previous_party IN (' . implode(', ', $names) . ') OR new_party IN (' . implode(', ', $names) . ')';
        }

        if ($measure === 'party_flow') {
            $sql = "WITH ev AS ({$base}), mv AS (
                        SELECT new_party AS party, x, 1 AS joined, 0 AS \"left\" FROM ev
                        UNION ALL SELECT previous_party, x, 0, 1 FROM ev)
                    SELECT party, x, sum(joined) AS joined, sum(\"left\") AS \"left\" FROM mv{$partyFilter} GROUP BY party, x";
        } else {
            $sql = "WITH ev AS ({$base}) SELECT previous_party, new_party, x, count(*) AS n FROM ev{$partyFilter} GROUP BY previous_party, new_party, x";
        }
        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);
        $raw = $statement->fetchAll();

        $threshold = self::DISCLOSURE_THRESHOLD;
        $rows = [];
        $total = 0;
        foreach ($raw as $r) {
            $labels = [];
            $x = $r['x'] === null ? null : (string) $r['x'];
            if ($measure === 'party_flow') {
                $joined = (int) $r['joined'];
                $left = (int) $r['left'];
                $size = $joined + $left;
                $labels[] = self::PARTY_LABELS[$r['party']] ?? $r['party'];
                $cells = [(string) $joined, (string) $left, ($joined - $left > 0 ? '+' : '') . ($joined - $left)];
                $score = $joined - $left;
            } else {
                $size = (int) $r['n'];
                $labels[] = (self::PARTY_LABELS[$r['previous_party']] ?? $r['previous_party']) . ' → ' . (self::PARTY_LABELS[$r['new_party']] ?? $r['new_party']);
                $cells = [(string) $size];
                $score = $size;
            }
            if ($x !== null) {
                $labels[] = $x;
            }
            $hidden = $extraKey === 'block' && $size < $threshold;
            $rows[] = ['labels' => $labels, 'n' => $hidden ? null : $score, 'pct' => null, 'cells' => $hidden ? array_fill(0, count($cells), '<' . $threshold) : $cells, 'sort' => $score, 'x' => $x];
            $total += $measure === 'party_flow' ? (int) $r['joined'] : (int) $r['n'];
        }

        $timeAxis = in_array($extraKey, ['month', 'year'], true);
        $sign = $order === 'asc' ? 1 : -1;
        usort($rows, static fn (array $a, array $b): int => $timeAxis
            ? [$a['x'], $a['labels'][0]] <=> [$b['x'], $b['labels'][0]]
            : [$sign * $a['sort'], $a['labels']] <=> [$sign * $b['sort'], $b['labels']]);
        $notes = ['Counts are recorded party changes (moves between parties), not estimates; a person moving twice counts twice.'];
        $cap = $limit ?? self::MAX_ROWS;
        if (count($rows) > $cap) {
            $rows = array_slice($rows, 0, $cap);
            if ($limit === null) {
                $notes[] = 'Showing the first ' . self::MAX_ROWS . ' rows.';
            }
        }
        if ($extraKey === 'block') {
            $notes[] = 'Block-level figures below ' . $threshold . ' are hidden to protect privacy.';
        }
        if ($period !== null) {
            $notes[] = 'Periods apply to the date the change was recorded.';
        }

        $interpretation = [$measure === 'party_flow' ? 'Party changes: joined, left and net per party' : 'Party changes by route (from → to)'];
        if ($extraKey !== null) {
            $interpretation[] = 'Grouped by: ' . self::DIMENSIONS[$extraKey]['label'];
        }
        foreach (['district' => 'District', 'block' => 'Block', 'unit' => 'Unit', 'classification' => 'Classification', 'status' => 'Status', 'gender' => 'Gender'] as $key => $label) {
            if (isset($filters[$key])) {
                $interpretation[] = $label . ': ' . implode(', ', $filters[$key]['names']);
            }
        }
        if ($parties !== []) {
            $interpretation[] = 'Party: ' . implode(', ', array_map(fn (string $p): string => self::PARTY_LABELS[$p], $parties));
        }
        if ($period !== null) {
            $interpretation[] = 'Recorded: ' . $period[2];
        }

        $columns = [$measure === 'party_flow' ? 'Party' : 'Route'];
        if ($extraKey !== null) {
            $columns[] = self::DIMENSIONS[$extraKey]['label'];
        }
        $columns = array_merge($columns, $measure === 'party_flow' ? ['Joined', 'Left', 'Net'] : ['Changes']);

        return [
            'question' => $question,
            'interpretation' => $interpretation,
            'total' => $total,
            'show_table' => $showTable && $rows !== [],
            'columns' => $columns,
            'rows' => array_map(static fn (array $r): array => ['labels' => $r['labels'], 'n' => $r['n'], 'pct' => null, 'cells' => $r['cells']], $rows),
            'notes' => $notes,
            'scope' => 'recorded party changes' . (isset($filters['district']) ? ' in ' . implode(', ', $filters['district']['names']) : ''),
            'measure' => $measure,
        ];
    }

    private function buildWhere(array $filters, ?array $period): array
    {
        $clauses = [];
        $parameters = [];
        $counter = 0;
        $in = static function (string $column, array $values) use (&$parameters, &$counter): string {
            $names = [];
            foreach ($values as $value) {
                $name = ':p' . $counter++;
                $parameters[$name] = $value;
                $names[] = $name;
            }
            return $column . ' IN (' . implode(', ', $names) . ')';
        };
        $columns = ['district' => 'd.id', 'block' => 'b.id', 'unit' => 'u.id'];
        foreach ($columns as $type => $column) {
            if (isset($filters[$type])) {
                $clauses[] = $in($column, $filters[$type]['ids']);
            }
        }
        $values = ['party' => 'm.party', 'classification' => "COALESCE(m.classification, 'none')", 'status' => 'm.membership_status', 'gender' => 'm.gender'];
        foreach ($values as $key => $column) {
            if (isset($filters[$key])) {
                $clauses[] = $in($column, $filters[$key]['values']);
            }
        }
        if ($period !== null) {
            $clauses[] = 'm.enrollment_date >= :period_from AND m.enrollment_date < :period_to';
            $parameters[':period_from'] = $period[0]->format('Y-m-d');
            $parameters[':period_to'] = $period[1]->format('Y-m-d');
        }

        return [$clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses), $parameters];
    }

    private function run(array $dimensions, string $where, array $parameters): array
    {
        if ($dimensions === []) {
            return [];
        }
        $selects = [];
        $groups = [];
        foreach ($dimensions as $index => $key) {
            $selects[] = self::DIMENSIONS[$key]['select'] . ' AS g' . ($index + 1);
            $groups[] = self::DIMENSIONS[$key]['group'];
        }
        $statement = $this->connection->prepare('SELECT ' . implode(', ', $selects) . ', count(*) AS n ' . self::FROM . " {$where} GROUP BY " . implode(', ', $groups));
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    private function grandTotal(string $where, array $parameters): int
    {
        $statement = $this->connection->prepare('SELECT count(*) ' . self::FROM . " {$where}");
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    private function valueLabel(string $dimension, ?string $value): string
    {
        $value = $value ?? '';
        switch ($dimension) {
            case 'party':
                return self::PARTY_LABELS[$value] ?? $value;
            case 'classification':
                return self::CLASS_LABELS[$value] ?? $value;
            case 'status':
            case 'gender':
                return ucfirst($value);
            default:
                return $value;
        }
    }

    private function present(string $question, array $dimensions, array $filters, ?array $period, array $rawRows, int $total, ?string $order, ?int $limit, bool $showTable): array
    {
        $timeFirst = $dimensions !== [] && in_array($dimensions[0], ['month', 'year'], true);
        $sensitive = (isset($filters['party']) && $filters['party']['values'] !== ['zpm']) || isset($filters['classification'])
            || array_intersect(['party', 'classification'], $dimensions) !== [];
        $suppress = $sensitive && in_array('unit', $dimensions, true);

        $rows = [];
        foreach ($rawRows as $raw) {
            $labels = [];
            foreach ($dimensions as $index => $key) {
                $labels[] = $this->valueLabel($key, $raw['g' . ($index + 1)]);
            }
            $n = (int) $raw['n'];
            $hidden = $suppress && $n < self::DISCLOSURE_THRESHOLD;
            $rows[] = ['labels' => $labels, 'n' => $hidden ? null : $n, 'pct' => $hidden || $total === 0 ? null : round($n * 100 / $total, 1), 'raw' => $n];
        }

        if ($timeFirst) {
            usort($rows, static fn (array $a, array $b): int => [$a['labels'][0], $a['labels'][1] ?? ''] <=> [$b['labels'][0], $b['labels'][1] ?? '']);
        } else {
            $sums = [];
            foreach ($rows as $row) {
                $sums[$row['labels'][0]] = ($sums[$row['labels'][0]] ?? 0) + $row['raw'];
            }
            $sign = $order === 'asc' ? 1 : -1;
            usort($rows, static fn (array $a, array $b): int => [$sign * $sums[$a['labels'][0]], $a['labels'][0], $a['labels'][1] ?? ''] <=> [$sign * $sums[$b['labels'][0]], $b['labels'][0], $b['labels'][1] ?? '']);
        }

        $notes = ['Counts are recorded registered-individual records, not estimates.'];
        $cap = $limit ?? self::MAX_ROWS;
        if (count($dimensions) === 1 && count($rows) > $cap) {
            $rows = array_slice($rows, 0, $cap);
            if ($limit === null) {
                $notes[] = 'Showing the first ' . self::MAX_ROWS . ' rows.';
            }
        }
        if ($suppress) {
            $notes[] = 'Unit-level party or classification counts below ' . self::DISCLOSURE_THRESHOLD . ' are hidden to protect privacy.';
        }
        if ($period !== null) {
            $notes[] = 'Periods apply to registration (enrollment) date.';
        }

        $interpretation = [];
        if ($dimensions !== []) {
            $interpretation[] = 'Grouped by: ' . implode(' × ', array_map(static fn (string $k): string => self::DIMENSIONS[$k]['label'], $dimensions));
        }
        foreach (['district' => 'District', 'block' => 'Block', 'unit' => 'Unit', 'party' => 'Party', 'classification' => 'Classification', 'status' => 'Status', 'gender' => 'Gender'] as $key => $label) {
            if (isset($filters[$key])) {
                $interpretation[] = $label . ': ' . implode(', ', $filters[$key]['names']);
            }
        }
        if ($period !== null) {
            $interpretation[] = 'Registered: ' . $period[2];
        }
        if ($interpretation === []) {
            $interpretation[] = 'All registered individuals';
        }

        $columns = array_map(static fn (string $k): string => self::DIMENSIONS[$k]['label'], $dimensions);
        $columns[] = 'Individuals';
        $columns[] = 'Share of matching';

        return [
            'question' => $question,
            'interpretation' => $interpretation,
            'total' => $total,
            'show_table' => $showTable && $rows !== [],
            'columns' => $columns,
            'rows' => array_map(static fn (array $r): array => ['labels' => $r['labels'], 'n' => $r['n'], 'pct' => $r['pct']], $rows),
            'notes' => $notes,
            'scope' => $this->scopeSentence($filters, $period),
        ];
    }

    private function scopeSentence(array $filters, ?array $period): string
    {
        $parts = [];
        foreach (['party', 'classification', 'status', 'gender'] as $key) {
            if (isset($filters[$key])) {
                $parts[] = implode(' / ', $filters[$key]['names']);
            }
        }
        $text = 'registered individuals' . ($parts !== [] ? ' (' . implode('; ', $parts) . ')' : '');
        $areas = [];
        foreach (['unit', 'block', 'district'] as $key) {
            if (isset($filters[$key])) {
                $areas[] = implode(', ', $filters[$key]['names']);
            }
        }
        if ($areas !== []) {
            $text .= ' in ' . implode(', ', $areas);
        }
        if ($period !== null) {
            $text .= ' registered ' . $period[2];
        }

        return $text;
    }

    /** Plain wording, used only if the AI cannot write the narrative. */
    public static function plainSummary(array $result): string
    {
        $text = number_format($result['total']) . ' ' . $result['scope'] . ($result['total'] === 0 ? ' were found.' : ' match your question.');
        $visible = array_values(array_filter($result['rows'], static fn (array $r): bool => $r['n'] !== null));
        if ($visible !== []) {
            usort($visible, static fn (array $a, array $b): int => $b['n'] <=> $a['n']);
            $text .= ' The largest group is ' . implode(' – ', $visible[0]['labels']) . ' with ' . number_format($visible[0]['n']) . '.';
        }

        return $text;
    }
}
