<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> — GIS</title>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/app.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>

<body>
  <header class="app-header">
    <p class="brand">ZPM Membership Management</p>
    <div class="user-menu"><span><?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?></span></div>
  </header>
  <div class="layout"><?php require __DIR__ . '/partials/nav.php'; ?><main class="main">
      <h1>GIS &amp; Maps</h1>
      <section class="content-card">
        <p class="muted">Development map showing synthetic unit reference points and aggregate member counts. No
          individual members are geolocated.</p>
        <div id="map" class="map" aria-label="Unit aggregate map"></div>
      </section>
    </main>
  </div>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/gis.js"
    data-base-path="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>

</html>