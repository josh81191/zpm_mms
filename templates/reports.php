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
            <section class="content-card" id="ask-card" data-endpoint="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/reports/ask" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <h2>Ask a question</h2>
                <form id="ask-form" class="ask-form" autocomplete="off">
                    <label class="visually-hidden" for="ask-input">Your question in English</label>
                    <input id="ask-input" type="text" maxlength="300" placeholder="Type your question in English" required>
                    <button type="submit" id="ask-button">Analyse</button>
                </form>
                <div id="ask-result" aria-live="polite" hidden>
                    <p id="ask-error" class="ask-error" hidden></p>
                    <div id="ask-output" hidden>
                        <p id="ask-summary" class="ask-summary"></p>
                        <p id="ask-interpretation" class="muted"></p>
                        <div id="ask-table-block" hidden>
                            <div class="section-heading"><h3>Supporting table</h3><button type="button" class="button-link" id="ask-csv">Download CSV</button></div>
                            <div class="table-scroll"><table id="ask-table"><thead></thead><tbody></tbody></table></div>
                        </div>
                        <p id="ask-notes" class="muted"></p>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <script src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/app.js"></script>
    <script src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/ask.js"></script>
    </body>
    </html>