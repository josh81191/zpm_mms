<?php

declare(strict_types=1);

namespace Zpm;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class Application
{
    private const PARTIES = ['zpm', 'mnf', 'congress', 'bjp', 'other'];
    private const CLASSIFICATIONS = ['green', 'yellow', 'red'];
    // Classification breakdowns are hidden on maps when a unit has fewer non-ZPM individuals than this.
    private const MAP_DISCLOSURE_THRESHOLD = 5;

    private Auth $auth;
    private View $view;

    public function __construct(private array $config, private Database $database, string $templatesPath)
    {
        date_default_timezone_set($config['timezone']);
        $this->auth = new Auth($database);
        $this->view = new View($templatesPath);
    }

    public function handle(): void
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            $basePath = rtrim($this->config['base_path'], '/');
            if ($basePath !== '' && str_starts_with($path, $basePath)) {
                $path = substr($path, strlen($basePath)) ?: '/';
            }

            if ($method === 'GET' && $path === '/') {
                $this->redirect($this->auth->user() === null ? '/login' : '/dashboard');
            }

            if ($method === 'GET' && $path === '/login') {
                if ($this->auth->user() !== null) {
                    $this->redirect('/dashboard');
                }
                $this->view->render('login', $this->pageData(['error' => $this->flash('error')]));
                return;
            }

            if ($method === 'POST' && $path === '/login') {
                Csrf::verify($_POST['_csrf'] ?? null);
                $email = trim((string) ($_POST['email'] ?? ''));
                $password = (string) ($_POST['password'] ?? '');
                if ($email === '' || $password === '' || !$this->auth->attempt($email, $password)) {
                    $this->flash('error', 'The email address or password is incorrect.');
                    $this->redirect('/login');
                }
                $this->redirect('/dashboard');
            }

            if ($method === 'POST' && $path === '/logout') {
                Csrf::verify($_POST['_csrf'] ?? null);
                $this->auth->logout();
                $this->redirect('/login');
            }

            if ($method === 'GET' && $path === '/dashboard') {
                $this->requirePermission('dashboard.view');
                $this->view->render('dashboard', $this->pageData($this->reportData($_GET) + ['partyMovement' => $this->partyMovement(), 'familyStats' => $this->familyStats()]));
                return;
            }

            if ($method === 'GET' && $path === '/reports') {
                $this->requirePermission('reports.view');
                $this->view->render('reports', $this->pageData($this->reportData($_GET)));
                return;
            }

            if ($method === 'POST' && $path === '/reports/ask') {
                $this->askReport();
                return;
            }

            if ($method === 'GET' && $path === '/reports/export.csv') {
                $this->requirePermission('reports.view');
                $this->exportReport($_GET);
                return;
            }

            if ($method === 'GET' && $path === '/audit') {
                $this->requirePermission('audit.view');
                $this->view->render('audit', $this->pageData(['auditRows' => $this->auditRows()]));
                return;
            }

            if ($method === 'GET' && $path === '/members') {
                $this->redirect('/individuals');
            }

            if ($method === 'GET' && $path === '/individuals') {
                $this->requirePermission('members.view');
                $this->view->render('individuals', $this->pageData($this->memberList($_GET)));
                return;
            }

            if ($method === 'GET' && $path === '/individuals/heads') {
                $this->requirePermission('members.manage');
                $this->familyHeadSearch($_GET);
                return;
            }

            if ($method === 'GET' && $path === '/individuals/new') {
                $this->requirePermission('members.manage');
                $this->view->render('individual-form', $this->pageData($this->memberFormData()));
                return;
            }

            if ($method === 'POST' && $path === '/individuals') {
                $this->requirePermission('members.manage');
                Csrf::verify($_POST['_csrf'] ?? null);
                $this->saveMember($_POST);
                $this->redirect('/individuals');
            }

            if ($method === 'GET' && preg_match('#^/individuals/([0-9]+)/edit$#', $path, $matches) === 1) {
                $this->requirePermission('members.manage');
                $this->view->render('individual-form', $this->pageData($this->memberFormData((int) $matches[1])));
                return;
            }

            if ($method === 'POST' && preg_match('#^/individuals/([0-9]+)/archive$#', $path, $matches) === 1) {
                $this->requirePermission('members.manage');
                Csrf::verify($_POST['_csrf'] ?? null);
                $this->archiveMember((int) $matches[1]);
                $this->redirect('/individuals');
            }

            if ($method === 'GET' && $path === '/organization') {
                $this->requirePermission('organization.manage');
                $this->view->render('organization', $this->pageData($this->organizationData()));
                return;
            }

            if ($method === 'POST' && $path === '/organization/units') {
                $this->requirePermission('organization.manage');
                Csrf::verify($_POST['_csrf'] ?? null);
                $this->saveUnit($_POST);
                $this->redirect('/organization');
            }

            if ($method === 'GET' && $path === '/gis') {
                $this->requirePermission('reports.view');
                $this->view->render('gis', $this->pageData());
                return;
            }

            if ($method === 'GET' && $path === '/gis/units.geojson') {
                $this->requirePermission('reports.view');
                $this->geoJsonUnits();
                return;
            }

            $this->notFound();
        } catch (RuntimeException $exception) {
            $this->renderError($exception->getMessage(), 400);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->renderError('An unexpected error occurred. Please contact the system administrator.', 500);
        }
    }

    private function pageData(array $data = []): array
    {
        return array_merge($data, [
            'appName' => $this->config['app_name'],
            'basePath' => rtrim($this->config['base_path'], '/'),
            'user' => $this->auth->user(),
            'csrfToken' => Csrf::token(),
            'permissions' => [
                'dashboard.view' => $this->auth->can('dashboard.view'),
                'members.view' => $this->auth->can('members.view'),
                'members.manage' => $this->auth->can('members.manage'),
                'organization.manage' => $this->auth->can('organization.manage'),
                'reports.view' => $this->auth->can('reports.view'),
                'audit.view' => $this->auth->can('audit.view'),
            ],
        ]);
    }

    private function requirePermission(string $permission): void
    {
        if ($this->auth->user() === null) {
            $this->redirect('/login');
        }
        if (!$this->auth->can($permission)) {
            $this->renderError('You do not have permission to access this page.', 403);
            exit;
        }
    }

    private function partyMovement(): array
    {
        $statement = $this->database->connection()->query(
            "WITH months AS (
                SELECT generate_series(date_trunc('month', CURRENT_DATE) - INTERVAL '11 months', date_trunc('month', CURRENT_DATE), INTERVAL '1 month') AS month_start
             ), changes AS (
                SELECT date_trunc('month', recorded_at) AS month_start, previous_party, new_party
                FROM party_classification_history
                WHERE previous_party IS NOT NULL AND previous_party <> new_party
                  AND recorded_at >= date_trunc('month', CURRENT_DATE) - INTERVAL '11 months'
             )
             SELECT to_char(m.month_start, 'Mon YYYY') AS label, p.party,
                    (SELECT count(*) FROM changes c WHERE c.month_start = m.month_start AND c.new_party = p.party) AS joined,
                    (SELECT count(*) FROM changes c WHERE c.month_start = m.month_start AND c.previous_party = p.party) AS left_party
             FROM months m CROSS JOIN (VALUES ('zpm'), ('mnf'), ('congress'), ('bjp'), ('other')) AS p(party)
             ORDER BY m.month_start, p.party"
        );
        $labels = [];
        $series = [];
        foreach (self::PARTIES as $party) {
            $series[$party] = ['joined' => [], 'left' => [], 'net' => []];
        }
        foreach ($statement->fetchAll() as $row) {
            if (!in_array($row['label'], $labels, true)) {
                $labels[] = $row['label'];
            }
            $series[$row['party']]['joined'][] = (int) $row['joined'];
            $series[$row['party']]['left'][] = (int) $row['left_party'];
            $series[$row['party']]['net'][] = (int) $row['joined'] - (int) $row['left_party'];
        }
        $total = array_sum(array_map('array_sum', array_column($series, 'joined')));
        // Population is reconstructed backwards from today's counts using the recorded net changes.
        $current = $this->database->connection()->query("SELECT party, count(*) AS c FROM members GROUP BY party")->fetchAll(\PDO::FETCH_KEY_PAIR);
        $population = [];
        foreach (self::PARTIES as $party) {
            $running = (int) ($current[$party] ?? 0);
            $points = [];
            for ($i = count($labels) - 1; $i >= 0; $i--) {
                $points[$i] = $running;
                $running -= $series[$party]['net'][$i];
            }
            ksort($points);
            $population[$party] = array_values($points);
        }
        return ['labels' => $labels, 'series' => $series, 'population' => $population, 'total' => $total];
    }

    private function askReport(): void
    {
        $respond = static function (int $status, array $body): void {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        };

        if ($this->auth->user() === null) {
            $respond(401, ['ok' => false, 'error' => 'Your session has expired. Please sign in again.']);
            return;
        }
        if (!$this->auth->can('reports.view')) {
            $respond(403, ['ok' => false, 'error' => 'You do not have permission to run analysis.']);
            return;
        }
        if (!hash_equals($_SESSION['_csrf'] ?? '', (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
            $respond(419, ['ok' => false, 'error' => 'Your session has expired. Please reload the page.']);
            return;
        }

        $payload = json_decode((string) file_get_contents('php://input'), true);
        $question = is_array($payload) && is_string($payload['question'] ?? null) ? $payload['question'] : '';
        $question = trim(preg_replace('/\s+/u', ' ', $question) ?? '');
        if ($question === '' || mb_strlen($question) > 300) {
            $respond(422, ['ok' => false, 'error' => 'Please type a question of up to 300 characters.']);
            return;
        }
        $aiConfig = $this->config['ai'] ?? [];
        if (!AiPlanner::enabled($aiConfig)) {
            $respond(503, ['ok' => false, 'error' => 'AI analysis is not enabled. Ask the system administrator to configure the AI service.']);
            return;
        }
        if (preg_match('/\b(epci|phone|mobile|address|email|names? of|full names?)\b/i', $question) === 1) {
            $respond(422, ['ok' => false, 'error' => 'This tool answers aggregate questions only. It does not list or look up individual people or personal identifiers.']);
            return;
        }

        try {
            $engine = new QueryEngine($this->database->connection());
            $ai = new AiPlanner($aiConfig);
            $result = $engine->answer($question, $ai->plan($question, $engine->planningGuide()));
            try {
                $result['summary'] = $ai->narrate($question, $result);
            } catch (RuntimeException) {
                $result['summary'] = QueryEngine::plainSummary($result);
                $result['notes'][] = 'The AI could not write the narrative, so a basic summary is shown.';
            }
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
            $respond($message === AiPlanner::UNAVAILABLE ? 503 : 422, ['ok' => false, 'error' => $message === AiPlanner::UNUSABLE ? 'The AI could not interpret that question. Please rephrase it.' : $message, 'examples' => QueryEngine::EXAMPLES]);
            return;
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $respond(500, ['ok' => false, 'error' => 'The analysis could not be completed. Please try rephrasing the question.']);
            return;
        }

        $audit = $this->database->connection()->prepare(
            'INSERT INTO audit_logs (actor_user_id, action_key, entity_type, entity_id, request_ip, metadata)
             VALUES (:actor, :action, :entity, NULL, CAST(:ip AS INET), CAST(:metadata AS JSONB))'
        );
        $audit->execute([
            'actor' => $this->auth->user()['id'],
            'action' => 'reports.ask',
            'entity' => 'report_query',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'metadata' => json_encode(['question' => mb_substr($question, 0, 300), 'interpretation' => $result['interpretation']], JSON_UNESCAPED_UNICODE),
        ]);

        $respond(200, ['ok' => true] + $result);
    }

    private function dashboardStats(array $input): array
    {
        return $this->reportData($input)['stats'];
    }

    private function reportData(array $input): array
    {
        [$where, $parameters, $filters] = $this->memberFilters($input);
        $connection = $this->database->connection();
        $statement = $connection->prepare(
            "SELECT
                count(*) AS total_members,
                count(*) FILTER (WHERE m.membership_status = 'active') AS active_members,
                count(*) FILTER (WHERE m.membership_status <> 'active') AS inactive_members,
                count(*) FILTER (WHERE m.enrollment_date >= CURRENT_DATE - INTERVAL '30 days') AS recent_enrollments,
                (SELECT count(*) FROM districts WHERE administrative_status = 'active') AS active_districts,
                (SELECT count(*) FROM units WHERE administrative_status = 'active') AS active_units
             FROM members m
             INNER JOIN units u ON u.id = m.current_unit_id
             INNER JOIN blocks b ON b.id = u.block_id
             INNER JOIN districts d ON d.id = b.district_id
             {$where}"
        );
        $statement->execute($parameters);
        $stats = $statement->fetch();

        if ($stats === false) {
            throw new RuntimeException('Report totals could not be loaded.');
        }

        $districts = $connection->prepare(
            "SELECT d.name, count(m.id) AS member_count
             FROM districts d
             LEFT JOIN blocks b ON b.district_id = d.id
             LEFT JOIN units u ON u.block_id = b.id
             LEFT JOIN members m ON m.current_unit_id = u.id
             {$this->districtWhere($input)}
             GROUP BY d.id, d.name
             ORDER BY member_count DESC, d.name"
        );
        $districts->execute($parameters);

        $partyStatement = $connection->prepare(
            "SELECT m.party, count(*) AS individual_count
             FROM members m
             INNER JOIN units u ON u.id = m.current_unit_id
             INNER JOIN blocks b ON b.id = u.block_id
             INNER JOIN districts d ON d.id = b.district_id
             {$where}
             GROUP BY m.party"
        );
        $partyStatement->execute($parameters);
        $partyCounts = array_fill_keys(self::PARTIES, 0);
        foreach ($partyStatement->fetchAll() as $row) {
            $partyCounts[$row['party']] = (int) $row['individual_count'];
        }

        $classStatement = $connection->prepare(
            "SELECT m.classification, count(*) AS individual_count
             FROM members m
             INNER JOIN units u ON u.id = m.current_unit_id
             INNER JOIN blocks b ON b.id = u.block_id
             INNER JOIN districts d ON d.id = b.district_id
             " . ($where === '' ? 'WHERE' : $where . ' AND') . " m.classification IS NOT NULL
             GROUP BY m.classification"
        );
        $classStatement->execute($parameters);
        $classificationCounts = array_fill_keys(self::CLASSIFICATIONS, 0);
        foreach ($classStatement->fetchAll() as $row) {
            $classificationCounts[$row['classification']] = (int) $row['individual_count'];
        }

        $trend = $connection->prepare(
            "SELECT to_char(date_trunc('month', m.enrollment_date), 'YYYY-MM') AS month,
                    count(*) AS member_count
             FROM members m
             INNER JOIN units u ON u.id = m.current_unit_id
             INNER JOIN blocks b ON b.id = u.block_id
             INNER JOIN districts d ON d.id = b.district_id
             {$where}
             GROUP BY date_trunc('month', m.enrollment_date)
             ORDER BY month"
        );
        $trend->execute($parameters);

        $quality = $connection->query(
            "SELECT
                (SELECT count(*) FROM units u
                 WHERE NOT EXISTS (SELECT 1 FROM members m WHERE m.current_unit_id = u.id)
                   AND u.administrative_status = 'active') AS units_without_members,
                (SELECT count(*) FROM members m
                 INNER JOIN units u ON u.id = m.current_unit_id
                 WHERE u.administrative_status <> 'active'
                   AND m.membership_status = 'active') AS active_members_in_inactive_units,
                (SELECT count(*) FROM members WHERE epci_number IS NULL) AS members_without_epci,
                (SELECT count(*) FROM (
                    SELECT lower(trim(m.full_name)), u.id
                    FROM members m
                    INNER JOIN units u ON u.id = m.current_unit_id
                    GROUP BY lower(trim(m.full_name)), u.id
                    HAVING count(*) > 1
                ) duplicate_groups) AS possible_duplicate_groups"
        )->fetch();
        $districtOptions = $connection->query(
            "SELECT id, name FROM districts WHERE administrative_status = 'active' ORDER BY name"
        )->fetchAll();

        return [
            'stats' => $stats,
            'districtRows' => $districts->fetchAll(),
            'partyCounts' => $partyCounts,
            'classificationCounts' => $classificationCounts,
            'trendRows' => $trend->fetchAll(),
            'quality' => $quality ?: [],
            'filters' => $filters,
            'districtOptions' => $districtOptions,
        ];
    }

    private function memberFilters(array $input): array
    {
        $where = [];
        $parameters = [];
        $filters = [
            'date_from' => trim((string) ($input['date_from'] ?? '')),
            'date_to' => trim((string) ($input['date_to'] ?? '')),
            'district_id' => filter_var($input['district_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
            'status' => in_array(($input['status'] ?? ''), ['active', 'inactive', 'resigned', 'deceased', 'archived'], true) ? $input['status'] : '',
            'party' => in_array(($input['party'] ?? ''), self::PARTIES, true) ? $input['party'] : '',
            'classification' => in_array(($input['classification'] ?? ''), self::CLASSIFICATIONS, true) ? $input['classification'] : '',
        ];

        if ($filters['party'] !== '') {
            $where[] = 'm.party = :party';
            $parameters['party'] = $filters['party'];
        }
        if ($filters['classification'] !== '') {
            $where[] = 'm.classification = :classification';
            $parameters['classification'] = $filters['classification'];
        }

        if ($filters['date_from'] !== '') {
            $where[] = 'm.enrollment_date >= :date_from';
            $parameters['date_from'] = $filters['date_from'];
        }
        if ($filters['date_to'] !== '') {
            $where[] = 'm.enrollment_date <= :date_to';
            $parameters['date_to'] = $filters['date_to'];
        }
        if ($filters['district_id'] !== null) {
            $where[] = 'd.id = :district_id';
            $parameters['district_id'] = $filters['district_id'];
        }
        if ($filters['status'] !== '') {
            $where[] = 'm.membership_status = :status';
            $parameters['status'] = $filters['status'];
        }

        return [$where === [] ? '' : 'WHERE ' . implode(' AND ', $where), $parameters, $filters];
    }

    private function districtWhere(array $input): string
    {
        [$where] = $this->memberFilters($input);
        return $where === '' ? '' : 'WHERE ' . preg_replace('/^WHERE /', '', str_replace('m.', 'm.', $where));
    }

    private function exportReport(array $input): void
    {
        [$where, $parameters] = $this->memberFilters($input);
        $statement = $this->database->connection()->prepare(
            "SELECT m.member_code, m.full_name, m.gender, m.enrollment_date,
                    m.membership_status, CASE m.party WHEN 'zpm' THEN 'ZPM' WHEN 'mnf' THEN 'MNF' WHEN 'congress' THEN 'Congress' WHEN 'bjp' THEN 'BJP' ELSE COALESCE(m.other_party_name, 'Other') END AS party,
                    COALESCE(initcap(m.classification), '') AS classification,
                    d.name AS district_name, b.name AS block_name, u.name AS unit_name
             FROM members m
             INNER JOIN units u ON u.id = m.current_unit_id
             INNER JOIN blocks b ON b.id = u.block_id
             INNER JOIN districts d ON d.id = b.district_id
             {$where}
             ORDER BY d.name, b.name, u.name, m.full_name"
        );
        $statement->execute($parameters);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="zpm-registered-individuals-' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new RuntimeException('The report export could not be created.');
        }
        fputcsv($output, ['Record code', 'Full name', 'Gender', 'Registration date', 'Status', 'Party', 'Classification', 'District', 'Block', 'Unit']);
        while ($row = $statement->fetch()) {
            fputcsv($output, $row);
        }
        fclose($output);
    }

    private function auditRows(): array
    {
        return $this->database->connection()->query(
            "SELECT a.occurred_at, a.action_key, a.entity_type, a.entity_id,
                    COALESCE(u.full_name, 'System') AS actor_name, a.request_ip
             FROM audit_logs a
             LEFT JOIN user_accounts u ON u.id = a.actor_user_id
             ORDER BY a.occurred_at DESC
             LIMIT 100"
        )->fetchAll();
    }

    private function memberList(array $input): array
    {
        $search = trim((string) ($input['q'] ?? ''));
        $status = in_array(($input['status'] ?? ''), ['active', 'inactive', 'resigned', 'deceased', 'archived'], true) ? (string) $input['status'] : '';
        $parameters = [];
        $where = [];
        if ($search !== '') {
            $where[] = '(m.full_name ILIKE :search OR m.member_code ILIKE :search)';
            $parameters['search'] = '%' . $search . '%';
        }
        if ($status !== '') {
            $where[] = 'm.membership_status = :status';
            $parameters['status'] = $status;
        }
        $party = in_array(($input['party'] ?? ''), self::PARTIES, true) ? (string) $input['party'] : '';
        $classification = in_array(($input['classification'] ?? ''), self::CLASSIFICATIONS, true) ? (string) $input['classification'] : '';
        if ($party !== '') {
            $where[] = 'm.party = :party';
            $parameters['party'] = $party;
        }
        if ($classification !== '') {
            $where[] = 'm.classification = :classification';
            $parameters['classification'] = $classification;
        }
        $family = in_array(($input['family'] ?? ''), ['heads', 'members', 'none'], true) ? (string) $input['family'] : '';
        if ($family === 'heads') {
            $where[] = 'EXISTS (SELECT 1 FROM members f WHERE f.family_head_id = m.id)';
        } elseif ($family === 'members') {
            $where[] = 'm.family_head_id IS NOT NULL';
        } elseif ($family === 'none') {
            $where[] = 'm.family_head_id IS NULL AND NOT EXISTS (SELECT 1 FROM members f WHERE f.family_head_id = m.id)';
        }
        $page = max(1, (int) ($input['page'] ?? 1));
        $perPage = 25;
        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $count = $this->database->connection()->prepare(
            "SELECT count(*) FROM members m {$whereSql}"
        );
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $statement = $this->database->connection()->prepare(
            "SELECT m.id, m.member_code, m.full_name, m.gender, m.enrollment_date,
                    m.membership_status, m.party, m.other_party_name, m.classification, m.family_head_id,
                    h.full_name AS head_name,
                    (SELECT count(*) FROM members f WHERE f.family_head_id = m.id) AS family_size,
                    d.name AS district_name, b.name AS block_name, u.name AS unit_name
             FROM members m
             LEFT JOIN members h ON h.id = m.family_head_id
             INNER JOIN units u ON u.id = m.current_unit_id
             INNER JOIN blocks b ON b.id = u.block_id
             INNER JOIN districts d ON d.id = b.district_id
             {$whereSql}
             ORDER BY m.full_name, m.id
             LIMIT :limit OFFSET :offset"
        );
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $statement->execute();
        return [
            'memberRows' => $statement->fetchAll(),
            'memberFilters' => ['q' => $search, 'status' => $status, 'party' => $party, 'classification' => $classification, 'family' => $family, 'page' => $page],
            'memberTotal' => $total,
            'memberPages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    private function memberFormData(?int $memberId = null): array
    {
        $member = null;
        if ($memberId !== null) {
            $statement = $this->database->connection()->prepare('SELECT * FROM members WHERE id = :id');
            $statement->execute(['id' => $memberId]);
            $member = $statement->fetch();
            if ($member === false) {
                throw new RuntimeException('The member record was not found.');
            }
        }
        $familyHead = null;
        $familyMembers = [];
        if ($member !== null) {
            $connection = $this->database->connection();
            if ($member['family_head_id'] !== null) {
                $head = $connection->prepare('SELECT id, member_code, full_name FROM members WHERE id = :id');
                $head->execute(['id' => $member['family_head_id']]);
                $familyHead = $head->fetch() ?: null;
            }
            $dependents = $connection->prepare('SELECT id, member_code, full_name, membership_status FROM members WHERE family_head_id = :id ORDER BY full_name');
            $dependents->execute(['id' => $member['id']]);
            $familyMembers = $dependents->fetchAll();
        }
        return [
            'member' => $member,
            'familyHead' => $familyHead,
            'familyMembers' => $familyMembers,
            'unitOptions' => $this->database->connection()->query(
                "SELECT u.id, u.name AS unit_name, b.name AS block_name, d.name AS district_name
                 FROM units u INNER JOIN blocks b ON b.id = u.block_id INNER JOIN districts d ON d.id = b.district_id
                 WHERE u.administrative_status = 'active' ORDER BY d.name, b.name, u.name"
            )->fetchAll(),
        ];
    }

    private function familyStats(): array
    {
        $row = $this->database->connection()->query(
            "SELECT count(DISTINCT family_head_id) AS families, count(*) AS linked FROM members WHERE family_head_id IS NOT NULL"
        )->fetch();
        return ['families' => (int) $row['families'], 'linked' => (int) $row['linked']];
    }

    private function familyHeadSearch(array $input): void
    {
        $search = trim((string) ($input['q'] ?? ''));
        $exclude = (int) ($input['exclude'] ?? 0);
        $unitId = (int) ($input['unit_id'] ?? 0);
        $statement = $this->database->connection()->prepare(
            "SELECT m.id, m.member_code, m.full_name, u.name AS unit_name
             FROM members m INNER JOIN units u ON u.id = m.current_unit_id
             WHERE m.family_head_id IS NULL AND m.membership_status <> 'archived' AND m.id <> :exclude
               AND (m.full_name ILIKE :search OR m.member_code ILIKE :search)
             ORDER BY (m.current_unit_id = :unit_id) DESC, m.full_name
             LIMIT 15"
        );
        $statement->execute(['exclude' => $exclude, 'search' => '%' . $search . '%', 'unit_id' => $unitId]);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['results' => $statement->fetchAll()], JSON_THROW_ON_ERROR);
    }

    private function validateFamilyHead(\PDO $connection, ?int $memberId, ?int $headId, string $status): void
    {
        $dependents = 0;
        if ($memberId !== null) {
            $count = $connection->prepare('SELECT count(*) FROM members WHERE family_head_id = :id');
            $count->execute(['id' => $memberId]);
            $dependents = (int) $count->fetchColumn();
        }
        if ($dependents > 0 && $status === 'archived') {
            throw new RuntimeException('This individual is the head of a family. Move the family members to another head before archiving.');
        }
        if ($headId === null) {
            return;
        }
        if ($headId === $memberId) {
            throw new RuntimeException('An individual cannot be their own family head.');
        }
        if ($dependents > 0) {
            throw new RuntimeException('This individual is a family head with linked members and cannot be tied to another head.');
        }
        $head = $connection->prepare('SELECT family_head_id, membership_status FROM members WHERE id = :id');
        $head->execute(['id' => $headId]);
        $row = $head->fetch();
        if ($row === false) {
            throw new RuntimeException('The selected family head was not found.');
        }
        if ($row['family_head_id'] !== null) {
            throw new RuntimeException('The selected family head is already a member of another family.');
        }
        if ($row['membership_status'] === 'archived') {
            throw new RuntimeException('An archived individual cannot be a family head.');
        }
    }

    private function saveMember(array $input): void
    {
        $name = trim((string) ($input['full_name'] ?? ''));
        $unitId = filter_var($input['current_unit_id'] ?? null, FILTER_VALIDATE_INT);
        $enrollmentDate = trim((string) ($input['enrollment_date'] ?? ''));
        $gender = in_array(($input['gender'] ?? ''), ['male', 'female', 'not-specified'], true) ? $input['gender'] : 'not-specified';
        $status = in_array(($input['membership_status'] ?? ''), ['active', 'inactive', 'resigned', 'deceased', 'archived'], true) ? $input['membership_status'] : 'active';
        $memberId = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if ($name === '' || $unitId === false || $unitId === null || $enrollmentDate === '') {
            throw new RuntimeException('Full name, enrollment date, and organizational unit are required.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $enrollmentDate);
        if ($date === false || $date > new DateTimeImmutable('today')) {
            throw new RuntimeException('Enrollment date must be a valid date that is not in the future.');
        }
        $party = (string) ($input['party'] ?? '');
        $classification = (string) ($input['classification'] ?? '');
        $otherPartyName = trim((string) ($input['other_party_name'] ?? ''));
        $classificationNote = trim((string) ($input['classification_note'] ?? ''));
        if (!in_array($party, self::PARTIES, true)) {
            throw new RuntimeException('Please select a party.');
        }
        if ($party === 'zpm') {
            $classification = '';
        } elseif (!in_array($classification, self::CLASSIFICATIONS, true)) {
            throw new RuntimeException('A Green, Yellow, or Red classification is required for individuals who are not ZPM.');
        }
        if ($party === 'other') {
            if ($otherPartyName === '') {
                throw new RuntimeException('Please enter the party name when "Other" is selected.');
            }
        } else {
            $otherPartyName = '';
        }
        $connection = $this->database->connection();
        $connection->beginTransaction();
        try {
            $userId = $this->auth->user()['id'];
            $previous = null;
            if ($memberId !== null) {
                $lookup = $connection->prepare('SELECT party, classification FROM members WHERE id = :id FOR UPDATE');
                $lookup->execute(['id' => $memberId]);
                $previous = $lookup->fetch();
                if ($previous === false) {
                    throw new RuntimeException('The record was not found.');
                }
            }
            $headId = filter_var($input['family_head_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
            $this->validateFamilyHead($connection, $memberId, $headId, $status);
            $partyFields = [
                'party' => $party,
                'other_party_name' => $otherPartyName === '' ? null : $otherPartyName,
                'classification' => $classification === '' ? null : $classification,
                'classification_note' => $classificationNote === '' ? null : $classificationNote,
            ];
            if ($memberId === null) {
                $code = 'R-' . strtoupper(bin2hex(random_bytes(5)));
                $statement = $connection->prepare(
                    "INSERT INTO members (member_code, full_name, gender, enrollment_date, current_unit_id, membership_status, status_effective_date, remarks, created_by_user_id, updated_by_user_id,
                                          party, other_party_name, classification, classification_note, classification_set_at, classification_set_by_user_id)
                     VALUES (:member_code, :full_name, :gender, :enrollment_date, :unit_id, :status, :enrollment_date, :remarks, :user_id, :user_id,
                             :party, :other_party_name, :classification, :classification_note,
                             CASE WHEN CAST(:classification AS VARCHAR) IS NULL THEN NULL ELSE CURRENT_TIMESTAMP END,
                             CASE WHEN CAST(:classification AS VARCHAR) IS NULL THEN NULL ELSE CAST(:user_id AS BIGINT) END)
                     RETURNING id"
                );
                $statement->execute(['member_code' => $code, 'full_name' => $name, 'gender' => $gender, 'enrollment_date' => $enrollmentDate, 'unit_id' => $unitId, 'status' => $status, 'remarks' => trim((string) ($input['remarks'] ?? '')), 'user_id' => $userId] + $partyFields);
                $memberId = (int) $statement->fetchColumn();
                $eventType = 'new-enrollment';
            } else {
                $classificationChanged = ($previous['classification'] ?? null) !== $partyFields['classification'];
                $statement = $connection->prepare(
                    "UPDATE members SET full_name = :full_name, gender = :gender, enrollment_date = :enrollment_date,
                     current_unit_id = :unit_id, membership_status = :status, status_effective_date = CURRENT_DATE,
                     remarks = :remarks, updated_by_user_id = :user_id, updated_at = CURRENT_TIMESTAMP,
                     party = :party, other_party_name = :other_party_name, classification = :classification, classification_note = :classification_note,
                     classification_set_at = CASE WHEN CAST(:changed AS BOOLEAN) THEN CASE WHEN CAST(:classification AS VARCHAR) IS NULL THEN NULL ELSE CURRENT_TIMESTAMP END ELSE classification_set_at END,
                     classification_set_by_user_id = CASE WHEN CAST(:changed AS BOOLEAN) THEN CASE WHEN CAST(:classification AS VARCHAR) IS NULL THEN NULL ELSE CAST(:user_id AS BIGINT) END ELSE classification_set_by_user_id END
                     WHERE id = :id"
                );
                $statement->bindValue(':changed', $classificationChanged, \PDO::PARAM_BOOL);
                foreach (['full_name' => $name, 'gender' => $gender, 'enrollment_date' => $enrollmentDate, 'unit_id' => $unitId, 'status' => $status, 'remarks' => trim((string) ($input['remarks'] ?? '')), 'user_id' => $userId, 'id' => $memberId] + $partyFields as $key => $value) {
                    $statement->bindValue(':' . $key, $value);
                }
                $statement->execute();
                $eventType = 'record-correction';
            }
            $setHead = $connection->prepare('UPDATE members SET family_head_id = :head WHERE id = :id AND family_head_id IS DISTINCT FROM CAST(:head AS BIGINT)');
            $setHead->bindValue(':head', $headId, $headId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $setHead->bindValue(':id', $memberId, \PDO::PARAM_INT);
            $setHead->execute();
            if ($previous === null || $previous['party'] !== $party || ($previous['classification'] ?? null) !== $partyFields['classification']) {
                $history = $connection->prepare(
                    "INSERT INTO party_classification_history (member_id, previous_party, new_party, previous_classification, new_classification, note, recorded_by_user_id)
                     VALUES (:member_id, :previous_party, :new_party, :previous_classification, :new_classification, :note, :user_id)"
                );
                $history->execute(['member_id' => $memberId, 'previous_party' => $previous['party'] ?? null, 'new_party' => $party, 'previous_classification' => $previous['classification'] ?? null, 'new_classification' => $partyFields['classification'], 'note' => $partyFields['classification_note'], 'user_id' => $userId]);
            }
            $event = $connection->prepare(
                "INSERT INTO membership_events (member_id, event_type, new_value, effective_date, recorded_by_user_id, reason)
                 VALUES (:member_id, :event_type, CAST(:new_value AS JSONB), CURRENT_DATE, :user_id, :reason)"
            );
            $event->execute(['member_id' => $memberId, 'event_type' => $eventType, 'new_value' => json_encode(['status' => $status, 'unit_id' => $unitId, 'party' => $party, 'classification' => $partyFields['classification'], 'family_head_id' => $headId], JSON_THROW_ON_ERROR), 'user_id' => $userId, 'reason' => 'Saved through the registered individuals register.']);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    private function archiveMember(int $memberId): void
    {
        $this->validateFamilyHead($this->database->connection(), $memberId, null, 'archived');
        $statement = $this->database->connection()->prepare(
            "UPDATE members SET membership_status = 'archived', status_effective_date = CURRENT_DATE, updated_by_user_id = :user_id, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        );
        $statement->execute(['user_id' => $this->auth->user()['id'], 'id' => $memberId]);
    }

    private function organizationData(): array
    {
        return [
            'organizationRows' => $this->database->connection()->query(
                "SELECT d.name AS district_name, b.name AS block_name, u.id AS unit_id, u.unit_code, u.name AS unit_name,
                        u.unit_type, count(m.id) AS member_count
                 FROM districts d INNER JOIN blocks b ON b.district_id = d.id INNER JOIN units u ON u.block_id = b.id
                 LEFT JOIN members m ON m.current_unit_id = u.id
                 GROUP BY d.name, b.name, u.id, u.unit_code, u.name, u.unit_type
                 ORDER BY d.name, b.name, u.name"
            )->fetchAll(),
            'districtOptions' => $this->database->connection()->query("SELECT id, name FROM districts WHERE administrative_status = 'active' ORDER BY name")->fetchAll(),
        ];
    }

    private function saveUnit(array $input): void
    {
        $districtId = filter_var($input['district_id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim((string) ($input['name'] ?? ''));
        $type = in_array(($input['unit_type'] ?? ''), ['village', 'locality', 'other'], true) ? $input['unit_type'] : 'other';
        if ($districtId === false || $districtId === null || $name === '') {
            throw new RuntimeException('District and unit name are required.');
        }
        $block = $this->database->connection()->prepare(
            "SELECT id FROM blocks WHERE district_id = :district_id ORDER BY id LIMIT 1"
        );
        $block->execute(['district_id' => $districtId]);
        $blockId = $block->fetchColumn();
        if ($blockId === false) {
            throw new RuntimeException('The selected district has no block available for this unit.');
        }
        $statement = $this->database->connection()->prepare(
            "INSERT INTO units (block_id, unit_code, name, unit_type, remarks)
             VALUES (:block_id, :unit_code, :name, :unit_type, :remarks)"
        );
        $statement->execute(['block_id' => $blockId, 'unit_code' => 'UNIT-' . strtoupper(bin2hex(random_bytes(4))), 'name' => $name, 'unit_type' => $type, 'remarks' => 'Created through organizational management.']);
    }

    private function geoJsonUnits(): void
    {
        $rows = $this->database->connection()->query(
            "SELECT u.id, u.unit_code, u.name, d.name AS district_name, b.name AS block_name,
                    count(m.id) AS member_count,
                    count(m.id) FILTER (WHERE m.party = 'zpm') AS zpm_count,
                    count(m.id) FILTER (WHERE m.party <> 'zpm') AS non_zpm_count,
                    count(m.id) FILTER (WHERE m.classification = 'green') AS green_count,
                    count(m.id) FILTER (WHERE m.classification = 'yellow') AS yellow_count,
                    count(m.id) FILTER (WHERE m.classification = 'red') AS red_count,
                    public.ST_AsGeoJSON(u.representative_location)::json AS geometry
             FROM units u INNER JOIN blocks b ON b.id = u.block_id INNER JOIN districts d ON d.id = b.district_id
             LEFT JOIN members m ON m.current_unit_id = u.id
             WHERE u.representative_location IS NOT NULL
             GROUP BY u.id, u.unit_code, u.name, d.name, b.name, u.representative_location
             ORDER BY d.name, b.name, u.name"
        )->fetchAll();
        $features = array_map(static fn (array $row): array => [
            'type' => 'Feature',
            'geometry' => json_decode((string) $row['geometry'], true, 512, JSON_THROW_ON_ERROR),
            'properties' => [
                'unit_code' => $row['unit_code'],
                'name' => $row['name'],
                'district' => $row['district_name'],
                'block' => $row['block_name'],
                'member_count' => (int) $row['member_count'],
                'zpm_count' => (int) $row['zpm_count'],
                'non_zpm_count' => (int) $row['non_zpm_count'],
                'classification' => (int) $row['non_zpm_count'] >= self::MAP_DISCLOSURE_THRESHOLD
                    ? ['green' => (int) $row['green_count'], 'yellow' => (int) $row['yellow_count'], 'red' => (int) $row['red_count']]
                    : null,
            ],
        ], $rows);
        header('Content-Type: application/geo+json; charset=utf-8');
        echo json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_THROW_ON_ERROR);
    }

    private function flash(string $key, ?string $value = null): ?string
    {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }

        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $message;
    }

    private function redirect(string $path): void
    {
        header('Location: ' . rtrim($this->config['base_path'], '/') . $path, true, 302);
        exit;
    }

    private function notFound(): void
    {
        $this->renderError('The page you requested was not found.', 404);
    }

    private function renderError(string $message, int $status): void
    {
        http_response_code($status);
        $this->view->render('error', $this->pageData(['message' => $message, 'status' => $status]));
    }
}
