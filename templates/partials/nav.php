<?php
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$currentPath = str_starts_with($requestPath, $basePath)
    ? substr($requestPath, strlen($basePath))
    : $requestPath;
$currentPath = '/' . ltrim($currentPath ?: '/', '/');
$isCurrent = static function (string $section) use ($currentPath): bool {
    return $section === '/dashboard'
        ? $currentPath === '/dashboard'
        : str_starts_with($currentPath, $section);
};
?>
<nav class="sidebar" aria-label="Primary navigation">
    <p class="nav-label">Workspace</p>
    <?php if ($permissions['dashboard.view'] ?? false): ?><a <?= $isCurrent('/dashboard') ? 'aria-current="page"' : '' ?> href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/dashboard">Dashboard</a><?php endif; ?>
    <?php if ($permissions['members.view'] ?? false): ?><a <?= $isCurrent('/members') ? 'aria-current="page"' : '' ?> href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/members">Members</a><?php endif; ?>
    <?php if ($permissions['organization.manage'] ?? false): ?><a <?= $isCurrent('/organization') ? 'aria-current="page"' : '' ?> href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/organization">Organization</a><?php endif; ?>
    <p class="nav-label">Insights</p>
    <?php if ($permissions['reports.view'] ?? false): ?><a <?= $isCurrent('/reports') ? 'aria-current="page"' : '' ?> href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/reports">Analysis &amp; Reports</a><?php endif; ?>
    <?php if ($permissions['reports.view'] ?? false): ?><a <?= $isCurrent('/gis') ? 'aria-current="page"' : '' ?> href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/gis">GIS &amp; Maps</a><?php endif; ?>
    <?php if ($permissions['audit.view'] ?? false): ?><a <?= $isCurrent('/audit') ? 'aria-current="page"' : '' ?> href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/audit">Audit log</a><?php endif; ?>
</nav>
