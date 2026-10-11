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

    public function __construct(private PDO $connection)
    {
    }

    /** Schema description given to the AI planner. It contains no member data. */
    public function planningGuide(): string
    {
        $districts = $this->connection->query('SELECT name FROM districts ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
        $blocks = (int) $this->connection->query('SELECT count(*) FROM blocks')->fetchColumn();

        return "Dataset: one row per registered individual. Only counts of individuals can be produced.\n"
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
        $showTable = ($plan['show_table'] ?? false) === true;

        [$where, $parameters] = $this->buildWhere($filters, $period);
        $rows = $this->run($dimensions, $where, $parameters);
        $total = $this->grandTotal($where, $parameters);

        return $this->present($question, $dimensions, $filters, $period, $rows, $total, $order, $limit, $showTable);
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
        return match ($dimension) {
            'party' => self::PARTY_LABELS[$value] ?? $value,
            'classification' => self::CLASS_LABELS[$value] ?? $value,
            'status', 'gender' => ucfirst($value),
            default => $value,
        };
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
