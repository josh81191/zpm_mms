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
        <nav class="sidebar" aria-label="Primary navigation">
            <a aria-current="page" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/dashboard">Dashboard</a>
            <a href="#" aria-disabled="true">Members (Phase 3)</a>
            <a href="#" aria-disabled="true">Organization (Phase 2)</a>
            <a href="#" aria-disabled="true">Reports (Phase 4)</a>
            <a href="#" aria-disabled="true">GIS &amp; Maps (Phase 5)</a>
        </nav>
        <main class="main">
            <h1>Welcome, <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?></h1>
            <section class="content-card" aria-labelledby="foundation-title">
                <h2 id="foundation-title">Foundation is ready</h2>
                <p class="status">Authentication and role-based access control are active.</p>
                <p>This initial release establishes the secure application shell. Membership, organization, reporting, and GIS functions will be added in their approved implementation phases.</p>
                <ul class="phase-list">
                    <li>Secure sign-in, session handling, CSRF protection, and server-side permissions</li>
                    <li>Auditable user accounts, roles, and permissions</li>
                    <li>Migration-based PostgreSQL schema management</li>
                    <li>Responsive shared interface for future modules</li>
                </ul>
            </section>
        </main>
    </div>
</body>
</html>

