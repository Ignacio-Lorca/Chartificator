<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="api-base" content="../api">
  <title>Chartificator - Rehearsal</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <main class="container">
    <h1>Rehearsal</h1>
    <?php $current = 'rehearsal'; require __DIR__ . '/partials/nav.php'; ?>
    <p class="pageIntro">Browse sessions, manage the setlist, and keep the scrolling chart in view with a roomier rehearsal layout.</p>

    <p id="pageStatus" class="muted"></p>

    <section class="panel">
      <div class="panelHeader">
        <div>
          <h2>Rehearsal list</h2>
          <p class="muted panelHeaderText">Open any rehearsal from the shared list. Joining happens automatically for the current user.</p>
        </div>
        <button type="button" id="toggleRehearsalListBtn" class="panelToggleBtn" aria-expanded="true" aria-controls="rehearsalListPanelBody">Hide</button>
      </div>
      <div id="rehearsalListPanelBody" class="panelBody">
        <div class="row compactRow">
          <input id="newRehearsalName" type="text" placeholder="New rehearsal name">
          <select id="newRehearsalSongSelect">
            <option value="">Select first song</option>
          </select>
          <button type="button" id="createRehearsalBtn">Create rehearsal</button>
        </div>
        <ul id="rehearsalList" class="rehearsalList"></ul>
      </div>
    </section>

    <div id="sessionContent" class="hidden">
      <p class="row">
        <a id="editorLink" href="editor.php">Open notes editor</a>
      </p>

      <section class="panel">
        <div class="panelHeader">
          <div>
            <h2>Setlist</h2>
            <p id="activeSongInfo" class="muted panelHeaderText"></p>
          </div>
          <button type="button" id="toggleSetlistBtn" class="panelToggleBtn" aria-expanded="true" aria-controls="setlistPanelBody">Hide</button>
        </div>
        <div id="setlistPanelBody" class="panelBody">
          <p id="membersInfo" class="muted"></p>
          <ul id="membersList" class="memberList"></ul>
          <div class="row compactRow">
            <select id="addSongSelect">
              <option value="">Select song to add</option>
            </select>
            <button type="button" id="addSongBtn">Add to setlist</button>
          </div>
          <p class="muted">Reorder songs, remove them from the playlist, or open one as the active song.</p>
          <ul id="setlistList"></ul>
        </div>
      </section>

      <section class="panel">
        <h2>Transport</h2>
        <div class="row compactRow transportRow">
          <button type="button" id="playBtn">Play</button>
          <button type="button" id="pauseBtn">Pause</button>
          <button type="button" id="songStartBtn">Song start</button>
          <input id="seekBar" type="number" min="1" step="0.25" placeholder="Seek bar">
          <button type="button" id="seekBtn">Seek</button>
          <input id="setBpm" type="number" min="30" max="300" placeholder="Set BPM">
          <button type="button" id="setBpmBtn">Apply BPM</button>
        </div>
        <p id="transportInfo" class="status"></p>
        <div class="timelineWrap">
          <div class="timelineNow">Now</div>
          <div id="timelineViewport" class="timelineViewport">
            <div id="timelineTrack" class="timelineTrack"></div>
          </div>
        </div>
        <div id="endOfSongPrompt" class="endOfSongPrompt hidden">
          <p id="endOfSongMessage" class="status">Song finished.</p>
          <div class="row">
            <button type="button" id="replaySongBtn">Replay song</button>
            <button type="button" id="nextSongBtn">Play next song</button>
          </div>
        </div>
      </section>

      <section class="panel">
        <h2>Sections (read-only)</h2>
        <ul id="sectionsList"></ul>
      </section>

      <section class="panel">
        <h2>Notes preview</h2>
        <div class="grid2">
          <div>
            <h3>Shared</h3>
            <ul id="sharedNotesList"></ul>
          </div>
          <div>
            <h3>My private</h3>
            <ul id="privateNotesList"></ul>
          </div>
        </div>
        <p class="muted">Edit notes on the <a href="editor.php">Editor</a> page.</p>
      </section>
    </div>
  </main>
  <script src="assets/js/api-client.js"></script>
  <script src="assets/js/auth.js"></script>
  <script src="assets/js/rehearsal-page.js"></script>
</body>
</html>
