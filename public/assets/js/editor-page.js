(function () {
  var api = window.SharedChartsApi.api;
  var escapeHtml = window.SharedChartsApi.escapeHtml;
  var requireAuth = window.SharedChartsAuth.requireAuth;
  var toast = window.SharedChartsToast;

  var el = function (id) {
    return document.getElementById(id);
  };

  function notify(message, type) {
    if (toast && typeof toast.show === 'function') {
      toast.show(message, type || 'info');
    }
  }

  function qs(name) {
    var m = new RegExp('[?&]' + name + '=([^&]*)').exec(window.location.search);
    return m ? decodeURIComponent(m[1].replace(/\+/g, ' ')) : '';
  }

  var songId = 0;
  var sessionId = 0;
  var songName = '';
  var sections = [];

  function clearSectionForm() {
    el('sectionId').value = '';
    el('sectionLabel').value = '';
    el('sectionColor').value = '#2b7cff';
    el('sectionStart').value = '';
    el('sectionEnd').value = '';
    el('sectionSharedText').value = '';
    el('sectionPrivateText').value = '';
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
    el('sectionSharedText').value = section.sharedText || '';
    el('sectionPrivateText').value = section.privateText || '';
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
          ')' +
          '<div class="muted">' +
          escapeHtml((s.sharedText || '').slice(0, 120) || 'No shared content') +
          '</div>' +
          '<div class="muted">' +
          escapeHtml((s.privateText || '').slice(0, 120) || 'No private content') +
          '</div>' +
          ' ' +
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
            notify('Section moved.', 'success');
          } catch (err) {
            notify(err.message || String(err), 'error');
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
            notify('Section deleted.', 'success');
          } catch (err) {
            notify(err.message || String(err), 'error');
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
      notify('Open this page with a valid song first.', 'error');
      return;
    }

    try {
      var song = await api('song-get.php?songId=' + encodeURIComponent(String(songId)));
      songName = (song && song.name) || '';
    } catch (err) {
      songName = '';
    }

    var songDisplay = songName ? songName : 'Song ' + songId;
    el('editorStatus').textContent =
      sessionId > 0 ? 'Song: ' + songDisplay + ' | Session ' + sessionId : 'Song: ' + songDisplay;
    if (el('backLink')) {
      el('backLink').href =
        sessionId > 0
          ? 'rehearsal.php?sessionId=' + sessionId + '&songId=' + songId
          : 'songs.php?songId=' + songId;
      el('backLink').textContent = sessionId > 0 ? 'Back to rehearsal' : 'Back to songs';
    }
    if (el('songLink')) {
      el('songLink').href = 'songs.php?songId=' + songId;
      if (songName) {
        el('songLink').textContent = 'Open song details (' + songName + ')';
      }
    }

    try {
      await refreshNotesAndSections();
    } catch (e) {
      el('editorStatus').textContent = e.message || String(e);
      notify(e.message || String(e), 'error');
      return;
    }

    el('saveSectionBtn').addEventListener('click', async function () {
      try {
        await api('section-upsert.php', 'POST', {
          songId: songId,
          sectionId: Number(el('sectionId').value) || undefined,
          label: el('sectionLabel').value,
          colorHex: (el('sectionColor').value || '#2b7cff').toUpperCase(),
          barStart: Number(el('sectionStart').value),
          barEnd: Number(el('sectionEnd').value),
          sharedText: el('sectionSharedText').value,
          privateText: el('sectionPrivateText').value,
        });
        clearSectionForm();
        await refreshNotesAndSections();
        notify('Section saved.', 'success');
      } catch (err) {
        notify(err.message || String(err), 'error');
      }
    });
    el('clearSectionBtn').addEventListener('click', function () {
      clearSectionForm();
    });
  }

  init();
})();
