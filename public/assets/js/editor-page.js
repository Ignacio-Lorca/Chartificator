(function () {
  var api = window.SharedChartsApi.api;
  var escapeHtml = window.SharedChartsApi.escapeHtml;
  var requireAuth = window.SharedChartsAuth.requireAuth;

  var el = function (id) {
    return document.getElementById(id);
  };

  function qs(name) {
    var m = new RegExp('[?&]' + name + '=([^&]*)').exec(window.location.search);
    return m ? decodeURIComponent(m[1].replace(/\+/g, ' ')) : '';
  }

  var songId = 0;
  var sessionId = 0;
  var sections = [];
  var sharedNotes = [];
  var privateNotes = [];

  function clearSectionForm() {
    el('sectionId').value = '';
    el('sectionLabel').value = '';
    el('sectionColor').value = '#2b7cff';
    el('sectionStart').value = '';
    el('sectionEnd').value = '';
  }

  function loadSectionIntoForm(sectionId) {
    var section = sections.find(function (item) {
      return Number(item.id) === Number(sectionId);
    });
    if (!section) return;
    el('sectionId').value = section.id;
    el('sectionLabel').value = section.label;
    el('sectionColor').value = (section.colorHex || '#2B7CFF').toLowerCase();
    el('sectionStart').value = section.barStart;
    el('sectionEnd').value = section.barEnd;
  }

  function setNoteStatus(kind, text) {
    var id = kind === 'shared' ? 'sharedNoteStatus' : 'privateNoteStatus';
    if (el(id)) {
      el(id).textContent = text;
    }
  }

  function clearNoteForm(kind, preserveBar) {
    var originalId = kind === 'shared' ? 'sharedOriginalBar' : 'privateOriginalBar';
    var textId = kind === 'shared' ? 'sharedNote' : 'privateNote';
    if (!preserveBar) {
      el('noteBar').value = '';
    }
    el(originalId).value = '';
    el(textId).value = '';
    setNoteStatus(kind, (kind === 'shared' ? 'Shared' : 'Private') + ' note form');
  }

  function loadNoteIntoForm(kind, barNumber) {
    var list = kind === 'shared' ? sharedNotes : privateNotes;
    var note = list.find(function (item) {
      return Number(item.barNumber) === Number(barNumber);
    });
    if (!note) return;
    el('noteBar').value = note.barNumber;
    el(kind === 'shared' ? 'sharedOriginalBar' : 'privateOriginalBar').value = note.barNumber;
    el(kind === 'shared' ? 'sharedNote' : 'privateNote').value = note.noteText;
    setNoteStatus(kind, 'Editing ' + kind + ' note at bar ' + note.barNumber);
  }

  function renderNoteList(list, listId, kind) {
    el(listId).innerHTML = list
      .map(function (n) {
        return (
          '<li><strong>Bar ' +
          n.barNumber +
          ':</strong> ' +
          escapeHtml(n.noteText) +
          '<div class="listActions">' +
          '<button type="button" class="noteEditBtn" data-kind="' +
          kind +
          '" data-bar-number="' +
          n.barNumber +
          '">Edit</button>' +
          '</div></li>'
        );
      })
      .join('');

    Array.prototype.forEach.call(el(listId).querySelectorAll('.noteEditBtn'), function (button) {
      button.addEventListener('click', function () {
        loadNoteIntoForm(
          button.getAttribute('data-kind'),
          Number(button.getAttribute('data-bar-number'))
        );
      });
    });
  }

  async function moveSection(sectionId, direction) {
    await api('section-reorder.php', 'POST', {
      songId: songId,
      sectionId: sectionId,
      direction: direction,
    });
    await refreshNotesAndSections();
  }

  async function deleteSection(sectionId) {
    await api('section-delete.php', 'POST', {
      songId: songId,
      sectionId: sectionId,
    });
    if (Number(el('sectionId').value) === Number(sectionId)) {
      clearSectionForm();
    }
    await refreshNotesAndSections();
  }

  async function refreshNotesAndSections() {
    var notes = await api('bar-notes.php?songId=' + encodeURIComponent(String(songId)));
    sharedNotes = notes.sharedNotes || [];
    privateNotes = notes.privateNotes || [];
    renderNoteList(sharedNotes, 'sharedNotesList', 'shared');
    renderNoteList(privateNotes, 'privateNotesList', 'private');

    var sec = await api('sections.php?songId=' + encodeURIComponent(String(songId)));
    sections = sec.sections || [];
    el('sectionsList').innerHTML = sections
      .map(function (s, index) {
        var color = s.colorHex || '#2B7CFF';
        return (
          '<li><span class="sectionColorSwatch" style="background:' +
          escapeHtml(color) +
          '"></span>' +
          escapeHtml(s.label) +
          ': bars ' +
          s.barStart +
          '-' +
          s.barEnd +
          ' (' +
          escapeHtml(color) +
          ') ' +
          '<button type="button" class="sectionEditBtn" data-section-id="' +
          s.id +
          '">Edit</button> ' +
          '<button type="button" class="sectionMoveBtn" data-direction="up" data-section-id="' +
          s.id +
          '"' +
          (index === 0 ? ' disabled' : '') +
          '>Up</button> ' +
          '<button type="button" class="sectionMoveBtn" data-direction="down" data-section-id="' +
          s.id +
          '"' +
          (index === sections.length - 1 ? ' disabled' : '') +
          '>Down</button> ' +
          '<button type="button" class="sectionDeleteBtn" data-section-id="' +
          s.id +
          '">Delete</button></li>'
        );
      })
      .join('');

    Array.prototype.forEach.call(
      el('sectionsList').querySelectorAll('.sectionEditBtn'),
      function (button) {
        button.addEventListener('click', function () {
          loadSectionIntoForm(Number(button.getAttribute('data-section-id')));
        });
      }
    );
    Array.prototype.forEach.call(
      el('sectionsList').querySelectorAll('.sectionMoveBtn'),
      function (button) {
        button.addEventListener('click', async function () {
          try {
            await moveSection(
              Number(button.getAttribute('data-section-id')),
              button.getAttribute('data-direction')
            );
          } catch (err) {
            alert(err.message || String(err));
          }
        });
      }
    );
    Array.prototype.forEach.call(
      el('sectionsList').querySelectorAll('.sectionDeleteBtn'),
      function (button) {
        button.addEventListener('click', async function () {
          try {
            await deleteSection(Number(button.getAttribute('data-section-id')));
          } catch (err) {
            alert(err.message || String(err));
          }
        });
      }
    );
  }

  async function init() {
    var user = await requireAuth();
    if (!user) return;

    songId = parseInt(qs('songId'), 10);
    sessionId = parseInt(qs('sessionId'), 10);
    if (!(songId > 0)) {
      el('editorStatus').textContent = 'Open this page with ?songId= or use the Songs/Rehearsal pages.';
      return;
    }

    el('editorStatus').textContent =
      sessionId > 0 ? 'Song ' + songId + ' | Session ' + sessionId : 'Song ' + songId;
    if (el('backLink')) {
      el('backLink').href =
        sessionId > 0
          ? 'rehearsal.php?sessionId=' + sessionId + '&songId=' + songId
          : 'songs.php?songId=' + songId;
      el('backLink').textContent = sessionId > 0 ? 'Back to rehearsal' : 'Back to songs';
    }
    if (el('songLink')) {
      el('songLink').href = 'songs.php?songId=' + songId;
    }

    try {
      await refreshNotesAndSections();
    } catch (e) {
      el('editorStatus').textContent = e.message || String(e);
      return;
    }

    el('saveSharedNoteBtn').addEventListener('click', async function () {
      try {
        var nextBar = Number(el('noteBar').value);
        var originalBar = Number(el('sharedOriginalBar').value);
        await api('bar-note-shared-upsert.php', 'POST', {
          songId: songId,
          barNumber: nextBar,
          noteText: el('sharedNote').value,
        });
        if (originalBar > 0 && originalBar !== nextBar) {
          await api('bar-note-shared-upsert.php', 'POST', {
            songId: songId,
            barNumber: originalBar,
            noteText: '',
          });
        }
        clearNoteForm('shared');
        await refreshNotesAndSections();
      } catch (err) {
        alert(err.message || String(err));
      }
    });

    el('savePrivateNoteBtn').addEventListener('click', async function () {
      try {
        var nextBar = Number(el('noteBar').value);
        var originalBar = Number(el('privateOriginalBar').value);
        await api('bar-note-private-upsert.php', 'POST', {
          songId: songId,
          barNumber: nextBar,
          noteText: el('privateNote').value,
        });
        if (originalBar > 0 && originalBar !== nextBar) {
          await api('bar-note-private-upsert.php', 'POST', {
            songId: songId,
            barNumber: originalBar,
            noteText: '',
          });
        }
        clearNoteForm('private');
        await refreshNotesAndSections();
      } catch (err) {
        alert(err.message || String(err));
      }
    });
    el('clearSharedNoteBtn').addEventListener('click', function () {
      clearNoteForm('shared', false);
    });
    el('clearPrivateNoteBtn').addEventListener('click', function () {
      clearNoteForm('private', false);
    });

    el('saveSectionBtn').addEventListener('click', async function () {
      try {
        await api('section-upsert.php', 'POST', {
          songId: songId,
          sectionId: Number(el('sectionId').value) || undefined,
          label: el('sectionLabel').value,
          colorHex: (el('sectionColor').value || '#2b7cff').toUpperCase(),
          barStart: Number(el('sectionStart').value),
          barEnd: Number(el('sectionEnd').value),
        });
        clearSectionForm();
        await refreshNotesAndSections();
      } catch (err) {
        alert(err.message || String(err));
      }
    });
    el('clearSectionBtn').addEventListener('click', function () {
      clearSectionForm();
    });
    clearNoteForm('shared', true);
    clearNoteForm('private', true);
  }

  init();
})();
