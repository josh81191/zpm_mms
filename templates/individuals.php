<?php require __DIR__ . '/partials/labels.php'; ?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $e($appName) ?> — Registered individuals</title><link rel="stylesheet" href="<?= $e($basePath) ?>/assets/app.css"></head>
<body>
<header class="app-header"><p class="brand">ZPM Registry &amp; GIS</p><div class="user-menu"><span><?= $e($user['full_name']) ?></span><form method="post" action="<?= $e($basePath) ?>/logout"><input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>"><button class="logout" type="submit">Sign out</button></form></div></header>
<div class="layout"><?php require __DIR__ . '/partials/nav.php'; ?>
<main class="main">
<div class="section-heading"><h1>Registered individuals</h1><?php if ($permissions['members.manage'] ?? false): ?><a class="button-link primary-link" href="<?= $e($basePath) ?>/individuals/new">Register individual</a><?php endif; ?></div>
<section class="content-card">
<form class="filter-grid" method="get" data-auto-submit="true">
<div class="field"><label for="q">Search name or record code</label><input id="q" name="q" value="<?= $e($memberFilters['q']) ?>"></div>
<div class="field"><label for="party">Party</label><select id="party" name="party"><option value="">All parties</option><?php foreach ($partyLabels as $key => $label): ?><option value="<?= $key ?>" <?= $memberFilters['party'] === $key ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="classification">Classification</label><select id="classification" name="classification"><option value="">All</option><?php foreach ($classificationLabels as $key => $label): ?><option value="<?= $key ?>" <?= $memberFilters['classification'] === $key ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option><?php foreach (['active','inactive','resigned','deceased','archived'] as $status): ?><option value="<?= $status ?>" <?= $memberFilters['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="family">Family</label><select id="family" name="family"><option value="">All</option><?php foreach (['heads' => 'Family heads', 'members' => 'Family members', 'none' => 'Not in a family'] as $key => $label): ?><option value="<?= $key ?>" <?= $memberFilters['family'] === $key ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></div>
<div class="filter-actions"><button type="submit">Search</button><a class="button-link" href="<?= $e($basePath) ?>/individuals">Clear</a></div>
</form>
<?php require __DIR__ . '/partials/legend.php'; ?>
<p class="muted"><?= number_format($memberTotal) ?> records; showing page <?= (int) $memberFilters['page'] ?> of <?= (int) $memberPages ?>.</p>
<div class="table-wrap"><table class="member-table"><thead><tr><th>Code</th><th>Name</th><th>Party</th><th>Classification</th><th>Status</th><th>Family</th><th>District</th><th>Block</th><th>Unit</th><th>Registered</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($memberRows as $row): ?>
<tr>
<td><?= $e($row['member_code']) ?></td>
<td><?= $e($row['full_name']) ?></td>
<td><span class="party-badge party-<?= $e($row['party']) ?>"><?= $e($row['party'] === 'other' && $row['other_party_name'] ? 'Other: ' . $row['other_party_name'] : $partyLabels[$row['party']]) ?></span></td>
<td><?php if ($row['classification'] !== null): ?><span class="class-badge class-<?= $e($row['classification']) ?>"><?= $e($classificationLabels[$row['classification']]) ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
<td><span class="status-badge status-<?= $e($row['membership_status']) ?>"><?= $e(ucfirst($row['membership_status'])) ?></span></td>
<td><?php if ((int) $row['family_size'] > 0): ?><span class="family-badge">Head · <?= (int) $row['family_size'] ?> member<?= (int) $row['family_size'] === 1 ? '' : 's' ?></span><?php elseif ($row['head_name'] !== null): ?><span class="muted">Of </span><a href="<?= $e($basePath) ?>/individuals?<?= $e(http_build_query(['q' => $row['head_name']])) ?>"><?= $e($row['head_name']) ?></a><?php else: ?><span class="muted">—</span><?php endif; ?></td>
<td><?= $e($row['district_name']) ?></td><td><?= $e($row['block_name']) ?></td><td><?= $e($row['unit_name']) ?></td><td><?= $e($row['enrollment_date']) ?></td>
<td><?php if ($permissions['members.manage'] ?? false): ?><a href="<?= $e($basePath) ?>/individuals/<?= (int) $row['id'] ?>/edit">Edit</a><?php if ($row['membership_status'] !== 'archived'): ?><form class="inline-form" method="post" action="<?= $e($basePath) ?>/individuals/<?= (int) $row['id'] ?>/archive" data-confirm="Archive this record?"><input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>"><button class="link-button" type="submit">Archive</button></form><?php endif; ?><?php else: ?><span class="muted">View only</span><?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if ($memberPages > 1): ?><nav class="pagination" aria-label="Registered individuals pages"><?php if ($memberFilters['page'] > 1): ?><a class="button-link" href="<?= $e($basePath) ?>/individuals?<?= $e(http_build_query(array_merge($memberFilters, ['page' => $memberFilters['page'] - 1]))) ?>">Previous</a><?php endif; ?><span>Page <?= (int) $memberFilters['page'] ?> of <?= (int) $memberPages ?></span><?php if ($memberFilters['page'] < $memberPages): ?><a class="button-link" href="<?= $e($basePath) ?>/individuals?<?= $e(http_build_query(array_merge($memberFilters, ['page' => $memberFilters['page'] + 1]))) ?>">Next</a><?php endif; ?></nav><?php endif; ?>
</section></main></div><script src="<?= $e($basePath) ?>/assets/app.js"></script></body></html>
