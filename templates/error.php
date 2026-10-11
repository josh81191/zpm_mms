<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> — Error</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/app.css">
</head>
<body class="auth-page">
    <main class="auth-card">
        <h1 class="brand">Unable to continue</h1>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <p class="muted">Status code: <?= (int) $status ?></p>
        <p><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/">Return to the application</a></p>
    </main>
</body>
</html>

