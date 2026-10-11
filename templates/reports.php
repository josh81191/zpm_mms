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
        <p class="brand">ZPM Registry &amp; GIS</p>
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
                        <label for="date_from">Registration from</label>
                        <input id="date_from" name="date_from" type="date" value="<?= htmlspecialchars($filters['date_from'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="field">
                        <label for="date_to">Registration to</label>
                        <input id="date_to" name="date_to" type="date" value="<?= htmlspecialchars($filters['date_to'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="field">
                        <label for="status">Status</label>
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
                    <div class="field">
                        <label for="party">Party</label>
                        <select id="party" name="party">
                            <option value="">All parties</option>
                            <?php foreach (['zpm' => 'ZPM', 'mnf' => 'MNF', 'congress' => 'Congress', 'bjp' => 'BJP', 'other' => 'Other'] as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $filters['party'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="classification">Classification</label>
                        <select id="classification" name="classification">
                            <option value="">All</option>
                            <?php foreach (['green' => 'Green', 'yellow' => 'Yellow', 'red' => 'Red'] as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $filters['classification'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit">Apply filters</button>
                        <a class="button-link" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/reports">Clear</a>
                    </div>
                </form>
                <p class="muted">Definitions: totals are current registered-individual records matching the selected registration and status filters. No estimates are included.</p>
            </section>

            <section class="stat-grid" aria-label="Filtered report summary">
                <article class="stat-card"><p class="stat-label">Registered individuals</p><p class="stat-value"><?= number_format((int) $stats['total_members']) ?></p></article>
                <article class="stat-card"><p class="stat-label">Active individuals</p><p class="stat-value"><?= number_format((int) $stats['active_members']) ?></p></article>
                <article class="stat-card"><p class="stat-label">Inactive or departed</p><p class="stat-value"><?= number_format((int) $stats['inactive_members']) ?></p></article>
                <article class="stat-card"><p class="stat-label">Registered in last 30 days</p><p class="stat-value"><?= number_format((int) $stats['recent_enrollments']) ?></p></article>
            </section>

            <?php require __DIR__ . '/partials/breakdown.php'; ?>

            <section class="report-grid">
                <article class="content-card">
                    <div class="section-heading"><h2>Registered individuals by district</h2><a class="button-link" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/reports/export.csv?<?= http_build_query($filters) ?>">Export CSV</a></div>
                    <table>
                        <thead><tr><th>District</th><th>Individuals</th></tr></thead>
                        <tbody>
                        <?php foreach ($districtRows as $row): ?>
                            <tr><td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= number_format((int) $row['member_count']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </article>
                <article class="content-card">
                    <h2>Registration trend</h2>
                    <?php if ($trendRows === []): ?>
                        <p class="muted">No registration records match the selected filters.</p>
                    <?php else: ?>
                        <div class="trend-list">
                        <?php $maxTrend = max(array_map(static fn (array $row): int => (int) $row['member_count'], $trendRows)); ?>
                        <?php foreach ($trendRows as $row): ?>
                            <div class="trend-row"><span><?= htmlspecialchars($row['month'], ENT_QUOTES, 'UTF-8') ?></span><div class="bar"><span data-width="<?= $maxTrend > 0 ? round(((int) $row['member_count'] / $maxTrend) * 100, 1) : 0 ?>"></span></div><strong><?= (int) $row['member_count'] ?></strong></div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            </section>

            <section class="content-card">
                <h2>Data quality checks</h2>
                <div class="quality-grid">
                    <div><strong><?= number_format((int) ($quality['units_without_members'] ?? 0)) ?></strong><span>Active units without individuals</span></div>
                    <div><strong><?= number_format((int) ($quality['active_members_in_inactive_units'] ?? 0)) ?></strong><span>Active individuals in inactive units</span></div>
                    <div><strong><?= number_format((int) ($quality['members_without_epci'] ?? 0)) ?></strong><span>Individuals without EPCI</span></div>
                    <div><strong><?= number_format((int) ($quality['possible_duplicate_groups'] ?? 0)) ?></strong><span>Possible duplicate groups</span></div>
                </div>
                <p class="muted">These checks identify records for authorized review. They do not automatically modify, merge, or change registered individual records.</p>
            </section>
        </main>
    </div>
    <script src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/app.js"></script>
</body>
</html>
