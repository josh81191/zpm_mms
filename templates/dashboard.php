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
        <article class="stat-card">
          <p class="stat-label">Families (with a head)</p>
          <p class="stat-value"><?php echo number_format($familyStats['families']) ?></p>
          <p class="muted"><?php echo number_format($familyStats['linked']) ?> linked members</p>
        </article>
      </section>
      <?php require __DIR__ . '/partials/breakdown.php'; ?>
      <section class="content-card">
        <h2>Registered individuals per party — past 12 months, all Mizoram</h2>
        <p class="muted">Total individuals in each party at the end of each month, derived from today's register and the recorded party changes. Demo data is synthetic.</p>
        <div class="chart-box"><canvas id="party-population-chart" role="img" aria-label="Total individuals per party over the past 12 months" data-chart="<?php echo htmlspecialchars(json_encode($partyMovement, JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') ?>"></canvas></div>
        <details><summary>View figures as a table</summary>
          <div class="table-wrap"><table><thead><tr><th>Month</th><?php foreach (array_keys($partyMovement['population']) as $p): ?><th><?php echo htmlspecialchars(strtoupper($p), ENT_QUOTES, 'UTF-8') ?></th><?php endforeach; ?></tr></thead><tbody>
          <?php foreach ($partyMovement['labels'] as $i => $label): ?><tr><td><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></td><?php foreach ($partyMovement['population'] as $pop): ?><td><?php echo number_format((int) $pop[$i]) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
          </tbody></table></div>
        </details>
      </section>
      <section class="content-card">
        <h2>Party migration — past 12 months, all Mizoram</h2>
        <p class="muted">Net monthly change per party: individuals who moved to the party minus those who moved away. <?php echo number_format((int) $partyMovement['total']) ?> recorded party changes in the period. Demo data is synthetic.</p>
        <div class="chart-box"><canvas id="party-movement-chart" role="img" aria-label="Net monthly party change over the past 12 months" data-chart="<?php echo htmlspecialchars(json_encode($partyMovement, JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') ?>"></canvas></div>
        <details><summary>View figures as a table</summary>
          <div class="table-wrap"><table><thead><tr><th>Month</th><?php foreach (array_keys($partyMovement['series']) as $p): ?><th><?php echo htmlspecialchars(strtoupper($p), ENT_QUOTES, 'UTF-8') ?> net</th><?php endforeach; ?></tr></thead><tbody>
          <?php foreach ($partyMovement['labels'] as $i => $label): ?><tr><td><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></td><?php foreach ($partyMovement['series'] as $s): ?><td><?php echo (int) $s['net'][$i] ?></td><?php endforeach; ?></tr><?php endforeach; ?>
          </tbody></table></div>
        </details>
      </section>
    </main>
  </div>
  <script src="https://unpkg.com/chart.js@4.4.1/dist/chart.umd.js"></script>
  <script src="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/dashboard.js"></script>
</body>

</html>