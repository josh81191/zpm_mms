<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> — Reports</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/app.css">
</head>
<body>
    <header class="app-header">
        <p class="brand">ZPM Membership Management</p>
        <div class="user-menu">
            <span><?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
            <form method="post" action="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/logout">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button class="logout" type="submit">Sign out</button>
            </form>
        </div>
    </header>
    <div class="layout">
        <?php require __DIR__ . '/partials/nav.php'; ?>
        <main class="main">
            <h1>Analysis &amp; Reports</h1>
            <section class="content-card">
                <h2>Report filters</h2>
                <form class="filter-grid" method="get" action="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/reports">
                    <div class="field">
                        <label for="date_from">Enrollment from</label>
                        <input id="date_from" name="date_from" type="date" value="<?= htmlspecialchars($filters['date_from'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="field">
                        <label for="date_to">Enrollment to</label>
                        <input id="date_to" name="date_to" type="date" value="<?= htmlspecialchars($filters['date_to'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="field">
                        <label for="status">Membership status</label>
                        <select id="status" name="status">
                            <option value="">All statuses</option>
                            <?php foreach (['active', 'inactive', 'resigned', 'deceased', 'archived'] as $status): ?>
                                <option value="<?= $status ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="district_id">District</label>
                        <select id="district_id" name="district_id">
                            <option value="">All districts</option>
                            <?php foreach ($districtOptions as $district): ?>
                                <option value="<?= (int) $district['id'] ?>" <?= (int) $filters['district_id'] === (int) $district['id'] ? 'selected' : '' ?>><?= htmlspecialchars($district['name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit">Apply filters</button>
                        <a class="button-link" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/reports">Clear</a>
                    </div>
                </form>
                <p class="muted">Definitions: totals are current member records matching the selected enrollment and status filters. No estimates are included.</p>
            </section>

            <section class="stat-grid" aria-label="Filtered report summary">
                <article class="stat-card"><p class="stat-label">Recorded members</p><p class="stat-value"><?= number_format((int) $stats['total_members']) ?></p></article>
                <article class="stat-card"><p class="stat-label">Active members</p><p class="stat-value"><?= number_format((int) $stats['active_members']) ?></p></article>
                <article class="stat-card"><p class="stat-label">Inactive or departed</p><p class="stat-value"><?= number_format((int) $stats['inactive_members']) ?></p></article>
                <article class="stat-card"><p class="stat-label">Enrolled in last 30 days</p><p class="stat-value"><?= number_format((int) $stats['recent_enrollments']) ?></p></article>
            </section>

            <section class="report-grid">
                <article class="content-card">
                    <div class="section-heading"><h2>Members by district</h2><a class="button-link" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/reports/export.csv?<?= http_build_query($filters) ?>">Export CSV</a></div>
                    <table>
                        <thead><tr><th>District</th><th>Members</th></tr></thead>
                        <tbody>
                        <?php foreach ($districtRows as $row): ?>
                            <tr><td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= number_format((int) $row['member_count']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </article>
                <article class="content-card">
                    <h2>Enrollment trend</h2>
                    <?php if ($trendRows === []): ?>
                        <p class="muted">No enrollment records match the selected filters.</p>
                    <?php else: ?>
                        <div class="trend-list">
                        <?php $maxTrend = max(array_map(static fn (array $row): int => (int) $row['member_count'], $trendRows)); ?>
                        <?php foreach ($trendRows as $row): ?>
                            <div class="trend-row"><span><?= htmlspecialchars($row['month'], ENT_QUOTES, 'UTF-8') ?></span><div class="bar"><span style="width: <?= $maxTrend > 0 ? ((int) $row['member_count'] / $maxTrend) * 100 : 0 ?>%"></span></div><strong><?= (int) $row['member_count'] ?></strong></div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            </section>

            <section class="content-card">
                <h2>Data quality checks</h2>
                <div class="quality-grid">
                    <div><strong><?= number_format((int) ($quality['units_without_members'] ?? 0)) ?></strong><span>Active units without members</span></div>
                    <div><strong><?= number_format((int) ($quality['active_members_in_inactive_units'] ?? 0)) ?></strong><span>Active members in inactive units</span></div>
                    <div><strong><?= number_format((int) ($quality['members_without_epci'] ?? 0)) ?></strong><span>Members without EPCI</span></div>
                    <div><strong><?= number_format((int) ($quality['possible_duplicate_groups'] ?? 0)) ?></strong><span>Possible duplicate groups</span></div>
                </div>
                <p class="muted">These checks identify records for authorized review. They do not automatically modify, merge, or classify member records.</p>
            </section>
        </main>
    </div>
</body>
</html>
