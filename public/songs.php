<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="api-base" content="../api">
  <title>Chartificator - Songs</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <main class="container">
    <h1>Songs</h1>
    <?php $current = 'songs'; require __DIR__ . '/partials/nav.php'; ?>
    <p class="pageIntro">Organize the catalog, update song details, and launch rehearsals with a lighter, more focused layout.</p>

    <section class="panel">
      <h2>All songs</h2>
      <ul id="songList" class="songList"></ul>
      <p class="muted">Click a song to edit, or create a new one below.</p>
    </section>

    <section class="panel">
      <h2>Create / edit song</h2>
      <div class="grid2">
        <input id="songName" type="text" placeholder="Song name">
        <input id="songBpm" type="number" min="30" max="300" value="120" placeholder="BPM">
        <input id="songSigNum" type="number" min="1" max="16" value="4" placeholder="Time signature numerator">
        <select id="songSigDen">
          <option value="2">2</option>
          <option value="4" selected>4</option>
          <option value="8">8</option>
          <option value="16">16</option>
        </select>
      </div>
      <div class="row">
        <button type="button" id="createSongBtn">Create Song</button>
        <button type="button" id="updateSongBtn">Update Song</button>
        <span id="songInfo" class="muted"></span>
      </div>
    </section>

    <section class="panel">
      <h2>Start rehearsal session</h2>
      <div class="row">
        <input id="sessionName" type="text" placeholder="Session name">
        <button type="button" id="createSessionBtn">Create Session &amp; open rehearsal</button>
      </div>
      <p id="sessionInfo" class="muted"></p>
      <p id="inviteOut" class="muted">Open existing rehearsals from the Rehearsal page.</p>
    </section>
  </main>
  <script src="assets/js/api-client.js"></script>
  <script src="assets/js/auth.js"></script>
  <script src="assets/js/songs-page.js"></script>
</body>
</html>
