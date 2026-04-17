<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="api-base" content="../api">
  <title>Chartificator - Login</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <main class="container">
    <h1>Chartificator</h1>
    <?php $current = 'login'; require __DIR__ . '/partials/nav.php'; ?>
    <p class="pageIntro">A calmer, cleaner workspace for keeping songs, notes, and rehearsals in sync.</p>

    <section class="panel" id="authPanel">
      <h2>Login</h2>
      <p class="muted">Choose a user to continue.</p>
      <div class="row">
        <select id="displayName" autocomplete="username">
          <option value="">Select a user</option>
        </select>
      </div>
      <p id="authStatus" class="muted"></p>
      <p><a href="songs.php">Continue to songs</a> (if already logged in)</p>
    </section>
  </main>
  <script src="assets/js/api-client.js"></script>
  <script src="assets/js/auth.js"></script>
  <script src="assets/js/toast.js"></script>
  <script src="assets/js/login-page.js"></script>
</body>
</html>
