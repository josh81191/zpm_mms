<header class="app-header">
  <p class="brand">ZPM Member Registry</p>
  <div class="user-menu">
    <span><?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
    <form method="post" action="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/logout">
      <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <button class="logout" type="submit">Sign out</button>
    </form>
  </div>
</header>
