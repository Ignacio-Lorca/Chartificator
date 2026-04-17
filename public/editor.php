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
      <div class="grid2 noteEditorGrid">
        <div class="noteEditorPanel">
          <p class="muted noteFormStatus">Shared section content</p>
          <textarea id="sectionSharedText" rows="5" placeholder="Shared notes for this section"></textarea>
        </div>
        <div class="noteEditorPanel">
          <p class="muted noteFormStatus">My private section content</p>
          <textarea id="sectionPrivateText" rows="5" placeholder="Private notes for this section"></textarea>
        </div>
      </div>
      <ul id="sectionsList"></ul>
    </section>
  </main>
  <script src="assets/js/api-client.js"></script>
  <script src="assets/js/auth.js"></script>
  <script src="assets/js/toast.js"></script>
  <script src="assets/js/editor-page.js"></script>
</body>
</html>
