<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> — Audit log</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/app.css">
</head>
<body>
    <header class="app-header"><p class="brand">ZPM Membership Management</p><div class="user-menu"><span><?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?><form method="post" action="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/logout"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"><button class="logout" type="submit">Sign out</button></form></div></header>
    <div class="layout">
        <?php require __DIR__ . '/partials/nav.php'; ?>
        <main class="main"><h1>Audit log</h1><section class="content-card"><p class="muted">Showing the latest 100 recorded administrative and authentication events.</p><div class="table-wrap"><table><thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Entity</th><th>IP</th></tr></thead><tbody><?php foreach ($auditRows as $row): ?><tr><td><?= htmlspecialchars($row['occurred_at'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($row['actor_name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($row['action_key'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($row['entity_type'] . ' ' . ($row['entity_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($row['request_ip'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr><?php endforeach; ?></tbody></table></div></section></main>
    </div>
</body>
</html>
