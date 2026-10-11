<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> — Dashboard</title>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/app.css">
</head>

<body>
  <header class="app-header">
    <p class="brand">ZPM Registry &amp; GIS</p>
    <div class="user-menu">
      <span><?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
      <form method="post" action="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/logout">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <button class="logout" type="submit">Sign out</button>
      </form>
    </div>
  </header>
  <div class="layout">
    <?php require __DIR__ . '/partials/nav.php'; ?>
    <main class="main">
      <h1>Welcome, <?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?></h1>
      <section class="stat-grid" aria-label="Organization summary">
        <article class="stat-card">
          <p class="stat-label">Registered individuals</p>
          <p class="stat-value"><?php echo number_format((int) $stats['total_members']) ?></p>
        </article>
        <article class="stat-card">
          <p class="stat-label">Active individuals</p>
          <p class="stat-value"><?php echo number_format((int) $stats['active_members']) ?></p>
        </article>
        <article class="stat-card">
          <p class="stat-label">Active districts</p>
          <p class="stat-value"><?php echo number_format((int) $stats['active_districts']) ?></p>
        </article>
        <article class="stat-card">
          <p class="stat-label">Active units</p>
          <p class="stat-value"><?php echo number_format((int) $stats['active_units']) ?></p>
        </article>
      </section>
      <?php require __DIR__ . '/partials/breakdown.php'; ?>
    </main>
  </div>
</body>

</html>