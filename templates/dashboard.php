<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> — Dashboard</title>
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
            <h1>Welcome, <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?></h1>
            <section class="stat-grid" aria-label="Organization summary">
                <article class="stat-card">
                    <p class="stat-label">Recorded members</p>
                    <p class="stat-value"><?= number_format((int) $stats['total_members']) ?></p>
                </article>
                <article class="stat-card">
                    <p class="stat-label">Active members</p>
                    <p class="stat-value"><?= number_format((int) $stats['active_members']) ?></p>
                </article>
                <article class="stat-card">
                    <p class="stat-label">Active districts</p>
                    <p class="stat-value"><?= number_format((int) $stats['active_districts']) ?></p>
                </article>
                <article class="stat-card">
                    <p class="stat-label">Active units</p>
                    <p class="stat-value"><?= number_format((int) $stats['active_units']) ?></p>
                </article>
            </section>
            <section class="content-card" aria-labelledby="foundation-title">
                <h2 id="foundation-title">Operational demo is ready</h2>
                <p class="status">Authentication, role-based access control, member management, reporting, and GIS aggregation are active.</p>
                <p>This development build contains the complete demonstrable core workflow: secure access, organization hierarchy, member register, audit history, report exports, and aggregate GIS visualization.</p>
                <ul class="phase-list">
                    <li>Secure sign-in, session handling, CSRF protection, and server-side permissions</li>
                    <li>Auditable user accounts, roles, permissions, member changes, and archive actions</li>
                    <li>Searchable member register with create, edit, status, and organizational assignment workflows</li>
                    <li>Migration-based PostgreSQL/PostGIS schema management</li>
                    <li>Responsive shared interface for future modules</li>
                </ul>
            </section>
        </main>
    </div>
</body>
</html>
