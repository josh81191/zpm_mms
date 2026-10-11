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
  <?php require __DIR__ . '/partials/header.php'; ?>
  <div class="layout"><?php require __DIR__ . '/partials/nav.php'; ?><main class="main">
      <section class="content-card">
        <div class="gis-controls">
          <div class="field"><label for="map-level">Geographic level</label>
            <select id="map-level">
              <option value="district">District</option>
              <option value="block">Block</option>
            </select>
          </div>
          <div class="field"><label for="map-area">Area</label>
            <select id="map-area">
              <option value="">Loading areas…</option>
            </select>
          </div>
        </div>
        <p class="muted" id="gis-map-message" aria-live="polite"></p>
        <div id="map" class="map" aria-label="Map with party population pie charts by district or block"></div>
      </section>
    </main>
  </div>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/gis.js"
    data-base-path="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>

</html>