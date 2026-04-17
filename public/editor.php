<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="api-base" content="../api">
  <title>Chartificator - Notes editor</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <main class="container">
    <h1>Notes &amp; sections</h1>
    <?php $current = 'editor'; require __DIR__ . '/partials/nav.php'; ?>
    <p class="pageIntro">Shape the song structure, keep notes readable, and move sections around with a softer editing surface.</p>

    <p id="editorStatus" class="muted"></p>
    <div class="linkRow">
      <a id="backLink" href="songs.php">Back</a>
      <a id="songLink" href="songs.php">Open song details</a>
    </div>

    <section class="panel">
      <h2>Sections</h2>
      <div class="row compactRow">
        <input id="sectionId" type="hidden">
        <input id="sectionLabel" type="text" placeholder="verse / chorus / bridge">
        <label for="sectionColor" class="sectionColorLabel">Color</label>
        <input id="sectionColor" type="color" value="#2b7cff" title="Section color">
        <input id="sectionStart" type="number" min="1" placeholder="Bar start">
        <input id="sectionEnd" type="number" min="1" placeholder="Bar end">
        <button type="button" id="saveSectionBtn">Save section</button>
        <button type="button" id="clearSectionBtn">Clear section form</button>
      </div>
      <ul id="sectionsList"></ul>
    </section>

    <section class="panel">
      <h2>Bar notes</h2>
      <div class="row compactRow">
        <input id="sharedOriginalBar" type="hidden">
        <input id="privateOriginalBar" type="hidden">
        <input id="noteBar" type="number" min="1" placeholder="Bar number">
      </div>
      <div class="grid2 noteEditorGrid">
        <div class="noteEditorPanel">
          <p id="sharedNoteStatus" class="muted noteFormStatus">Shared note form</p>
          <textarea id="sharedNote" rows="3" placeholder="Shared note"></textarea>
          <div class="row compactRow">
            <button type="button" id="saveSharedNoteBtn">Save shared note</button>
            <button type="button" id="clearSharedNoteBtn">Clear shared form</button>
          </div>
        </div>
        <div class="noteEditorPanel">
          <p id="privateNoteStatus" class="muted noteFormStatus">Private note form</p>
          <textarea id="privateNote" rows="3" placeholder="Private note"></textarea>
          <div class="row compactRow">
            <button type="button" id="savePrivateNoteBtn">Save private note</button>
            <button type="button" id="clearPrivateNoteBtn">Clear private form</button>
          </div>
        </div>
      </div>
      <div class="grid2">
        <div>
          <h3>Shared notes</h3>
          <ul id="sharedNotesList"></ul>
        </div>
        <div>
          <h3>My private notes</h3>
          <ul id="privateNotesList"></ul>
        </div>
      </div>
    </section>
  </main>
  <script src="assets/js/api-client.js"></script>
  <script src="assets/js/auth.js"></script>
  <script src="assets/js/toast.js"></script>
  <script src="assets/js/editor-page.js"></script>
</body>
</html>
