<?php
/** @var string $current one of: login, songs, rehearsal, editor */
$current = $current ?? '';
?>
<nav class="appNav">
  <div class="appNavLinks">
    <a href="index.php" class="<?php echo $current === 'login' ? 'active' : ''; ?>">Login</a>
    <a href="songs.php" class="<?php echo $current === 'songs' ? 'active' : ''; ?>">Songs</a>
    <a href="rehearsal.php" class="<?php echo $current === 'rehearsal' ? 'active' : ''; ?>">Rehearsal</a>
    <a href="editor.php" class="<?php echo $current === 'editor' ? 'active' : ''; ?>">Editor</a>
  </div>
  <div class="appNavUser">
    <button type="button" id="themeToggleBtn" class="navButton themeToggleButton">Dark mode</button>
    <span id="currentUserDisplay" class="muted"></span>
    <button type="button" id="logoutBtn" class="navButton">Logout</button>
  </div>
</nav>
<div id="appToastRoot" class="appToastRoot" aria-live="polite" aria-atomic="true"></div>
