<?php require __DIR__ . '/partials/labels.php'; $m = $member ?? []; ?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $e($appName) ?> — <?= $member === null ? 'Register' : 'Edit' ?> individual</title><link rel="stylesheet" href="<?= $e($basePath) ?>/assets/app.css"></head>
<body><header class="app-header"><p class="brand">ZPM Registry &amp; GIS</p><div class="user-menu"><span><?= $e($user['full_name']) ?></span></div></header>
<div class="layout"><?php require __DIR__ . '/partials/nav.php'; ?><main class="main">
<h1><?= $member === null ? 'Register individual' : 'Edit registered individual' ?></h1>
<section class="content-card"><form method="post" action="<?= $e($basePath) ?>/individuals" data-prevent-repeat="true"><input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>"><?php if ($member !== null): ?><input type="hidden" name="id" value="<?= (int) $member['id'] ?>"><?php endif; ?>
<div class="form-grid">
<div class="field"><label for="full_name">Full name</label><input id="full_name" name="full_name" required value="<?= $e($m['full_name'] ?? '') ?>"></div>
<div class="field"><label for="gender">Gender</label><select id="gender" name="gender"><?php foreach (['female','male','not-specified'] as $gender): ?><option value="<?= $gender ?>" <?= ($m['gender'] ?? 'not-specified') === $gender ? 'selected' : '' ?>><?= ucfirst(str_replace('-', ' ', $gender)) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="enrollment_date">Registration date</label><input id="enrollment_date" name="enrollment_date" type="date" required max="<?= date('Y-m-d') ?>" value="<?= $e($m['enrollment_date'] ?? date('Y-m-d')) ?>"></div>
<div class="field"><label for="membership_status">Status</label><select id="membership_status" name="membership_status"><?php foreach (['active','inactive','resigned','deceased','archived'] as $status): ?><option value="<?= $status ?>" <?= ($m['membership_status'] ?? 'active') === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="party">Party</label><select id="party" name="party" required><?php foreach ($partyLabels as $key => $label): ?><option value="<?= $key ?>" <?= ($m['party'] ?? 'zpm') === $key ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></div>
<div class="field" id="other-party-field"><label for="other_party_name">Party name (for “Other”)</label><input id="other_party_name" name="other_party_name" maxlength="120" value="<?= $e($m['other_party_name'] ?? '') ?>"></div>
<div class="field" id="classification-field"><label for="classification">Classification (non-ZPM)</label><select id="classification" name="classification"><option value="">Select Green, Yellow or Red</option><?php foreach ($classificationLabels as $key => $label): ?><option value="<?= $key ?>" <?= ($m['classification'] ?? '') === $key ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></div>
<div class="field" id="classification-note-field"><label for="classification_note">Classification note</label><input id="classification_note" name="classification_note" value="<?= $e($m['classification_note'] ?? '') ?>"></div>
<div class="field field-wide"><label for="current_unit_id">Organizational unit</label><select id="current_unit_id" name="current_unit_id" required><option value="">Select a unit</option><?php foreach ($unitOptions as $unit): ?><option value="<?= (int) $unit['id'] ?>" <?= (int) ($m['current_unit_id'] ?? 0) === (int) $unit['id'] ? 'selected' : '' ?>><?= $e($unit['district_name'] . ' / ' . $unit['block_name'] . ' / ' . $unit['unit_name']) ?></option><?php endforeach; ?></select></div>
<div class="field field-wide"><label for="remarks">Administrative remarks</label><textarea id="remarks" name="remarks" rows="4"><?= $e($m['remarks'] ?? '') ?></textarea></div>
</div>
<?php require __DIR__ . '/partials/legend.php'; ?>
<button type="submit">Save</button> <a class="button-link" href="<?= $e($basePath) ?>/individuals">Cancel</a></form></section></main></div><script src="<?= $e($basePath) ?>/assets/app.js"></script></body></html>
