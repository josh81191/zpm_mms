<?php

declare(strict_types=1);

namespace Zpm;

use RuntimeException;
use Throwable;

final class Application
{
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
                $this->view->render('dashboard', $this->pageData(['stats' => $this->dashboardStats($_GET)]));
                return;
            }

            if ($method === 'GET' && $path === '/reports') {
                $this->requirePermission('reports.view');
                $this->view->render('reports', $this->pageData($this->reportData($_GET)));
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
                $this->requirePermission('members.view');
                $this->view->render('members', $this->pageData($this->memberList($_GET)));
                return;
            }

            if ($method === 'GET' && $path === '/members/new') {
                $this->requirePermission('members.manage');
                $this->view->render('member-form', $this->pageData($this->memberFormData()));
                return;
            }

            if ($method === 'POST' && $path === '/members') {
                $this->requirePermission('members.manage');
                Csrf::verify($_POST['_csrf'] ?? null);
                $this->saveMember($_POST);
                $this->redirect('/members');
            }

            if ($method === 'GET' && preg_match('#^/members/([0-9]+)/edit$#', $path, $matches) === 1) {
                $this->requirePermission('members.manage');
                $this->view->render('member-form', $this->pageData($this->memberFormData((int) $matches[1])));
                return;
            }

            if ($method === 'POST' && preg_match('#^/members/([0-9]+)/archive$#', $path, $matches) === 1) {
                $this->requirePermission('members.manage');
                Csrf::verify($_POST['_csrf'] ?? null);
                $this->archiveMember((int) $matches[1]);
                $this->redirect('/members');
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
        ];

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
                    m.membership_status, d.name AS district_name, b.name AS block_name, u.name AS unit_name
             FROM members m
             INNER JOIN units u ON u.id = m.current_unit_id
             INNER JOIN blocks b ON b.id = u.block_id
             INNER JOIN districts d ON d.id = b.district_id
             {$where}
             ORDER BY d.name, b.name, u.name, m.full_name"
        );
        $statement->execute($parameters);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="zpm-member-report-' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new RuntimeException('The report export could not be created.');
        }
        fputcsv($output, ['Member code', 'Full name', 'Gender', 'Enrollment date', 'Status', 'District', 'Block', 'Unit']);
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
                    m.membership_status, d.name AS district_name, b.name AS block_name, u.name AS unit_name
             FROM members m
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
            'memberFilters' => ['q' => $search, 'status' => $status, 'page' => $page],
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
        return [
            'member' => $member,
            'unitOptions' => $this->database->connection()->query(
                "SELECT u.id, u.name AS unit_name, b.name AS block_name, d.name AS district_name
                 FROM units u INNER JOIN blocks b ON b.id = u.block_id INNER JOIN districts d ON d.id = b.district_id
                 WHERE u.administrative_status = 'active' ORDER BY d.name, b.name, u.name"
            )->fetchAll(),
        ];
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
        $connection = $this->database->connection();
        $connection->beginTransaction();
        try {
            if ($memberId === null) {
                $code = 'M-' . strtoupper(bin2hex(random_bytes(5)));
                $statement = $connection->prepare(
                    "INSERT INTO members (member_code, full_name, gender, enrollment_date, current_unit_id, membership_status, status_effective_date, remarks, created_by_user_id, updated_by_user_id)
                     VALUES (:member_code, :full_name, :gender, :enrollment_date, :unit_id, :status, :enrollment_date, :remarks, :user_id, :user_id)
                     RETURNING id"
                );
                $statement->execute(['member_code' => $code, 'full_name' => $name, 'gender' => $gender, 'enrollment_date' => $enrollmentDate, 'unit_id' => $unitId, 'status' => $status, 'remarks' => trim((string) ($input['remarks'] ?? '')), 'user_id' => $this->auth->user()['id']]);
                $memberId = (int) $statement->fetchColumn();
                $eventType = 'new-enrollment';
            } else {
                $statement = $connection->prepare(
                    "UPDATE members SET full_name = :full_name, gender = :gender, enrollment_date = :enrollment_date,
                     current_unit_id = :unit_id, membership_status = :status, status_effective_date = CURRENT_DATE,
                     remarks = :remarks, updated_by_user_id = :user_id, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
                );
                $statement->execute(['full_name' => $name, 'gender' => $gender, 'enrollment_date' => $enrollmentDate, 'unit_id' => $unitId, 'status' => $status, 'remarks' => trim((string) ($input['remarks'] ?? '')), 'user_id' => $this->auth->user()['id'], 'id' => $memberId]);
                $eventType = 'record-correction';
            }
            $event = $connection->prepare(
                "INSERT INTO membership_events (member_id, event_type, new_value, effective_date, recorded_by_user_id, reason)
                 VALUES (:member_id, :event_type, CAST(:new_value AS JSONB), CURRENT_DATE, :user_id, :reason)"
            );
            $event->execute(['member_id' => $memberId, 'event_type' => $eventType, 'new_value' => json_encode(['status' => $status, 'unit_id' => $unitId], JSON_THROW_ON_ERROR), 'user_id' => $this->auth->user()['id'], 'reason' => 'Saved through member register.']);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    private function archiveMember(int $memberId): void
    {
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
                    count(m.id) AS member_count, public.ST_AsGeoJSON(u.representative_location)::json AS geometry
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
