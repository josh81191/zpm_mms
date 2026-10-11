<nav class="sidebar" aria-label="Primary navigation">
    <?php if ($permissions['dashboard.view'] ?? false): ?><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/dashboard">Dashboard</a><?php endif; ?>
    <?php if ($permissions['members.view'] ?? false): ?><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/members">Members</a><?php endif; ?>
    <?php if ($permissions['organization.manage'] ?? false): ?><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/organization">Organization</a><?php endif; ?>
    <?php if ($permissions['reports.view'] ?? false): ?><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/reports">Analysis &amp; Reports</a><?php endif; ?>
    <?php if ($permissions['reports.view'] ?? false): ?><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/gis">GIS &amp; Maps</a><?php endif; ?>
    <?php if ($permissions['audit.view'] ?? false): ?><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/audit">Audit log</a><?php endif; ?>
</nav>
