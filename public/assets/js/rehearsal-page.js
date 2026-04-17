(function () {
  var api = window.SharedChartsApi.api;
  var escapeHtml = window.SharedChartsApi.escapeHtml;
  var computeBarNow = window.SharedChartsApi.computeBarNow;
  var requireAuth = window.SharedChartsAuth.requireAuth;
  var toast = window.SharedChartsToast;

  var el = function (id) {
    return document.getElementById(id);
  };

  function qs(name) {
    var m = new RegExp('[?&]' + name + '=([^&]*)').exec(window.location.search);
    return m ? decodeURIComponent(m[1].replace(/\+/g, ' ')) : '';
  }

  var FALLBACK_BAR_PIXEL_HEIGHT = 64;
  var FALLBACK_TIMELINE_NOW_Y = 120;
  var NOW_LINE_BAR_ANCHOR = 0;
  var PANEL_STATE_KEY = 'sharedCharts.rehearsalPanels';

  var state = {
    sessionId: null,
    songId: null,
    activeSong: null,
    rehearsals: [],
    setlist: [],
    availableSongs: [],
    members: [],
    currentUser: null,
    transport: null,
    sections: [],
    pollingId: null,
    pollingDelayMs: 300,
    pollingFailures: 0,
    timelineBars: [],
    endPromptShownForSongId: null,
    autoPauseRequestedForSongId: null,
    contentPollId: null,
    contentPollDelayMsPlaying: 2500,
    contentPollDelayMsPaused: 10000,
    contentFreshForSongId: null,
    contentRefreshPending: false,
    visualRafId: null,
    noteEditorSectionId: null,
    noteEditorSaving: false,
    navActionInFlight: false,
    transportActionInFlight: false,
    heartbeatTimerId: null,
    heartbeatIntervalMs: 15000,
  };

  function setStatus(id, text) {
    el(id).textContent = text;
    if (id === 'pageStatus' && toast && typeof toast.show === 'function' && text) {
      toast.show(text, 'info');
    }
  }

  function notify(message, type) {
    if (toast && typeof toast.show === 'function') {
      toast.show(message, type || 'info');
    }
  }

  function closeTimelineNoteEditor() {
    state.noteEditorSectionId = null;
    if (el('timelineNoteEditor')) {
      el('timelineNoteEditor').classList.add('hidden');
    }
    if (el('timelineSharedNoteInput')) {
      el('timelineSharedNoteInput').value = '';
    }
    if (el('timelinePrivateNoteInput')) {
      el('timelinePrivateNoteInput').value = '';
    }
    if (el('timelineNoteSaveBtn')) {
      el('timelineNoteSaveBtn').disabled = false;
    }
  }

  function openTimelineNoteEditor(sectionId) {
    var section = findSectionById(sectionId);
    if (!state.songId || !section) {
      return;
    }
    state.noteEditorSectionId = Number(section.id);
    if (el('timelineNoteEditorTitle')) {
      el('timelineNoteEditorTitle').textContent = 'Edit notes for section ' + section.label;
    }
    if (el('timelineSharedNoteInput')) {
      el('timelineSharedNoteInput').value = section.sharedText || '';
    }
    if (el('timelinePrivateNoteInput')) {
      el('timelinePrivateNoteInput').value = section.privateText || '';
    }
    if (el('timelineNoteEditor')) {
      el('timelineNoteEditor').classList.remove('hidden');
    }
  }

  async function saveTimelineNoteEditor() {
    if (state.noteEditorSaving || !state.songId || !state.noteEditorSectionId) {
      return;
    }
    state.noteEditorSaving = true;
    if (el('timelineNoteSaveBtn')) {
      el('timelineNoteSaveBtn').disabled = true;
    }
    try {
      var section = findSectionById(state.noteEditorSectionId);
      if (!section) {
        throw new Error('Section not found.');
      }
      var sharedText = el('timelineSharedNoteInput') ? el('timelineSharedNoteInput').value : '';
      var privateText = el('timelinePrivateNoteInput') ? el('timelinePrivateNoteInput').value : '';
      await api('section-upsert.php', 'POST', {
        songId: state.songId,
        sectionId: Number(section.id),
        label: section.label,
        colorHex: section.colorHex || '#2B7CFF',
        barStart: Number(section.barStart),
        barEnd: Number(section.barEnd),
        sortOrder: Number(section.sortOrder),
        sharedText: sharedText,
        privateText: privateText,
      });
      await refreshSongContent();
      closeTimelineNoteEditor();
      notify('Section notes saved.', 'success');
    } catch (err) {
      notify(err.message || String(err), 'error');
      if (el('timelineNoteSaveBtn')) {
        el('timelineNoteSaveBtn').disabled = false;
      }
    } finally {
      state.noteEditorSaving = false;
    }
  }

  function stampTransport(transport) {
    if (!transport) return null;
    transport.clientReceivedAtMs = Date.now();
    return transport;
  }

  function getPanelState() {
    try {
      return JSON.parse(window.localStorage.getItem(PANEL_STATE_KEY) || '{}');
    } catch (err) {
      return {};
    }
  }

  function savePanelState(nextState) {
    window.localStorage.setItem(PANEL_STATE_KEY, JSON.stringify(nextState));
  }

  function applyPanelState(panelName, expanded) {
    var buttonId = panelName === 'rehearsalList' ? 'toggleRehearsalListBtn' : 'toggleSetlistBtn';
    var bodyId = panelName === 'rehearsalList' ? 'rehearsalListPanelBody' : 'setlistPanelBody';
    var button = el(buttonId);
    var body = el(bodyId);
    if (!button || !body) return;
    body.classList.toggle('hidden', !expanded);
    button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    button.textContent = expanded ? 'Hide' : 'Show';
  }

  function wirePanelToggle(panelName) {
    var buttonId = panelName === 'rehearsalList' ? 'toggleRehearsalListBtn' : 'toggleSetlistBtn';
    var button = el(buttonId);
    if (!button) return;
    button.addEventListener('click', function () {
      var stateMap = getPanelState();
      var expanded = stateMap[panelName] !== false;
      stateMap[panelName] = !expanded;
      savePanelState(stateMap);
      applyPanelState(panelName, !expanded);
    });
  }

  function initPanelState() {
    var stateMap = getPanelState();
    applyPanelState('rehearsalList', stateMap.rehearsalList !== false);
    applyPanelState('setlist', stateMap.setlist !== false);
    wirePanelToggle('rehearsalList');
    wirePanelToggle('setlist');
  }

  function setSessionContentVisible(visible) {
    var node = el('sessionContent');
    if (!node) return;
    node.classList.toggle('hidden', !visible);
  }

  function renderRehearsalList() {
    if (!el('rehearsalList')) return;
    el('rehearsalList').innerHTML = state.rehearsals
      .map(function (item) {
        var active = state.sessionId === Number(item.sessionId);
        var songInfo = item.activeSongName
          ? escapeHtml(item.activeSongName) +
            ' (' +
            item.activeSongBpm +
            ' BPM, ' +
            item.activeSongTimeSignatureNum +
            '/' +
            item.activeSongTimeSignatureDen +
            ')'
          : 'No active song';
        return (
          '<li><strong>' +
          escapeHtml(item.name) +
          '</strong> - ' +
          songInfo +
          ' - ' +
          item.memberCount +
          ' user(s)' +
          '<div class="listActions">' +
          '<button type="button" class="rehearsalOpenBtn" data-session-id="' +
          item.sessionId +
          '" data-song-id="' +
          (item.songId || '') +
          '">' +
          (active ? 'Current rehearsal' : 'Open rehearsal') +
          '</button>' +
          '<button type="button" class="rehearsalDeleteBtn" data-session-id="' +
          item.sessionId +
          '">Delete</button>' +
          '</div></li>'
        );
      })
      .join('');
  }

  function renderNotesLists() {
    if (!el('sharedNotesList')) return;
    el('sharedNotesList').innerHTML = state.sections
      .map(function (section) {
        return (
          '<li><strong>' +
          escapeHtml(section.label) +
          ':</strong> ' +
          escapeHtml(section.sharedText || '') +
          '</li>'
        );
      })
      .join('');
    el('privateNotesList').innerHTML = state.sections
      .map(function (section) {
        return (
          '<li><strong>' +
          escapeHtml(section.label) +
          ':</strong> ' +
          escapeHtml(section.privateText || '') +
          '</li>'
        );
      })
      .join('');
    buildTimelineBars();
    renderTimelineRows();
  }

  function renderSetlist() {
    if (!el('setlistList')) return;
    el('setlistList').innerHTML = state.setlist
      .map(function (item) {
        var active = state.songId === Number(item.songId);
        return (
          '<li>' +
          (active ? '<strong>[Active]</strong> ' : '') +
          escapeHtml(item.name) +
          ' (' +
          item.bpm +
          ' BPM, ' +
          item.timeSignatureNum +
          '/' +
          item.timeSignatureDen +
          ')' +
          '<div class="listActions">' +
          '<button type="button" class="setlistSelectBtn" data-song-id="' +
          item.songId +
          '">' +
          (active ? 'Current song' : 'Open song') +
          '</button>' +
          '<button type="button" class="setlistMoveBtn" data-direction="up" data-song-id="' +
          item.songId +
          '"' +
          (item.sortOrder === 0 ? ' disabled' : '') +
          '>Up</button>' +
          '<button type="button" class="setlistMoveBtn" data-direction="down" data-song-id="' +
          item.songId +
          '"' +
          (item.sortOrder === state.setlist.length - 1 ? ' disabled' : '') +
          '>Down</button>' +
          '<button type="button" class="setlistRemoveBtn" data-song-id="' +
          item.songId +
          '">Remove</button>' +
          '<a href="editor.php?songId=' +
          item.songId +
          '">Edit song</a>' +
          '</div>' +
          '</li>'
        );
      })
      .join('');

    if (el('activeSongInfo')) {
      if (state.activeSong) {
        el('activeSongInfo').textContent =
          'Active song: ' +
          state.activeSong.name +
          ' | ' +
          state.activeSong.bpm +
          ' BPM | ' +
          state.activeSong.timeSignatureNum +
          '/' +
          state.activeSong.timeSignatureDen;
      } else {
        el('activeSongInfo').textContent = 'No active song selected.';
      }
    }
  }

  function renderMembers() {
    if (el('membersList')) {
      el('membersList').innerHTML = '';
    }
    if (el('membersInfo')) {
      var names = state.members.map(function (member) {
        var current = state.currentUser && Number(state.currentUser.userId) === Number(member.userId);
        return member.displayName + (current ? ' (you)' : '');
      });
      el('membersInfo').textContent =
        state.members.length > 0
          ? 'Users online now (' + state.members.length + '): ' + names.join('; ')
          : 'Users online now (0)';
    }
  }

  async function sendPresenceHeartbeat(silent) {
    if (!state.sessionId) return;
    try {
      await api('session-presence-heartbeat.php', 'POST', {
        sessionId: state.sessionId,
      });
    } catch (err) {
      if (!silent) {
        notify('Presence sync failed: ' + (err.message || String(err)), 'warning');
      }
    }
  }

  function stopHeartbeatLoop() {
    if (state.heartbeatTimerId) {
      clearTimeout(state.heartbeatTimerId);
      state.heartbeatTimerId = null;
    }
  }

  function startHeartbeatLoop() {
    stopHeartbeatLoop();
    var tick = function () {
      sendPresenceHeartbeat(true).finally(function () {
        state.heartbeatTimerId = setTimeout(tick, state.heartbeatIntervalMs);
      });
    };
    state.heartbeatTimerId = setTimeout(tick, state.heartbeatIntervalMs);
  }

  function wirePresenceLifecycle() {
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible') {
        sendPresenceHeartbeat(true);
      }
    });
    window.addEventListener('beforeunload', function () {
      stopHeartbeatLoop();
    });
  }

  function renderAvailableSongs() {
    if (!el('addSongSelect')) return;
    var currentSongIds = {};
    state.setlist.forEach(function (item) {
      currentSongIds[String(item.songId)] = true;
    });
    var options = ['<option value="">Select song to add</option>'];
    state.availableSongs.forEach(function (song) {
      if (currentSongIds[String(song.id)]) return;
      options.push(
        '<option value="' +
          song.id +
          '">' +
          escapeHtml(song.name) +
          ' (' +
          song.bpm +
          ' BPM)</option>'
      );
    });
    el('addSongSelect').innerHTML = options.join('');
    if (el('newRehearsalSongSelect')) {
      var createOptions = ['<option value="">Select first song</option>'];
      state.availableSongs.forEach(function (song) {
        createOptions.push(
          '<option value="' +
            song.id +
            '">' +
            escapeHtml(song.name) +
            ' (' +
            song.bpm +
            ' BPM)</option>'
        );
      });
      el('newRehearsalSongSelect').innerHTML = createOptions.join('');
    }
  }

  function renderSectionsList() {
    if (!el('sectionsList')) {
      return;
    }
    el('sectionsList').innerHTML = state.sections
      .map(function (s) {
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
          '</li>'
        );
      })
      .join('');
    buildTimelineBars();
    renderTimelineRows();
  }

  function buildTimelineBars() {
    var sectionBars = [];
    state.sections.forEach(function (s) {
      sectionBars.push(Number(s.barStart), Number(s.barEnd));
    });
    sectionBars = sectionBars.filter(function (n) {
      return Number.isFinite(n) && n > 0;
    });
    var current = Math.ceil(computeBarNow(state.transport));
    var maxKnown = Math.max.apply(
      null,
      [32, current + 24].concat(sectionBars)
    );
    state.timelineBars = [];
    for (var i = 1; i <= maxKnown; i++) state.timelineBars.push(i);
  }

  function findSectionForBar(barNumber) {
    return state.sections.find(function (s) {
      return barNumber >= Number(s.barStart) && barNumber <= Number(s.barEnd);
    });
  }

  function findSectionById(sectionId) {
    return state.sections.find(function (s) {
      return Number(s.id) === Number(sectionId);
    });
  }

  function renderTimelineRows() {
    var track = el('timelineTrack');
    if (!track) return;
    if (state.timelineBars.length === 0) buildTimelineBars();
    track.innerHTML = '';
    appendTimelineBars(1, state.timelineBars.length);
    renderTimelineSectionBlocks();
    renderTimelinePosition();
  }

  function buildTimelineBarMarkup(barNumber, currentBar) {
    var section = findSectionForBar(barNumber);
    var sectionColor = section && section.colorHex ? section.colorHex : '#2B7CFF';
    var sectionStart = section && Number(section.barStart) === barNumber;
    var activeClass = barNumber === currentBar ? ' timelineBarCurrent' : '';
    var railClass = section ? ' timelineBarSection' : '';
    var railStyle = section ? ' style="--section-color:' + escapeHtml(sectionColor) + ';"' : '';
    var sectionHeader = sectionStart ? '<div class="timelineSectionStartMarker"></div>' : '';
    return (
      '<div class="timelineBar' +
      activeClass +
      railClass +
      '" data-bar-number="' +
      barNumber +
      '"' +
      railStyle +
      '>' +
      sectionHeader +
      '<div class="timelineBarNum">' +
      barNumber +
      '</div>' +
      '</div>'
    );
  }

  function renderTimelineSectionBlocks() {
    var track = el('timelineTrack');
    if (!track) return;
    var barPixelHeight = getBarPixelHeight();
    var blocksHtml = state.sections
      .map(function (section) {
        var startBar = Number(section.barStart);
        var endBar = Number(section.barEnd);
        if (!Number.isFinite(startBar) || !Number.isFinite(endBar)) {
          return '';
        }
        startBar = Math.max(1, Math.floor(startBar));
        endBar = Math.max(startBar, Math.floor(endBar));
        var top = (startBar - 1) * barPixelHeight + 6;
        var height = (endBar - startBar + 1) * barPixelHeight - 12;
        var color = section.colorHex || '#2B7CFF';
        return (
          '<div class="timelineSectionBlock" data-section-id="' +
          Number(section.id || 0) +
          '" style="top:' +
          top +
          'px;height:' +
          height +
          'px;--section-color:' +
          escapeHtml(color) +
          ';">' +
          '<div class="timelineSectionBlockHeader">' +
          escapeHtml(section.label || 'Section') +
          '</div>' +
          '<div class="timelineSectionColumns">' +
          '<div class="timelineSectionColumn timelineSectionColumnShared">' +
          '<div class="timelineSectionColumnLabel">Shared</div>' +
          '<div class="timelineSectionText">' +
          escapeHtml(section.sharedText || '') +
          '</div>' +
          '</div>' +
          '<div class="timelineSectionColumn timelineSectionColumnPrivate">' +
          '<div class="timelineSectionColumnLabel">Private</div>' +
          '<div class="timelineSectionText">' +
          escapeHtml(section.privateText || '') +
          '</div>' +
          '</div>' +
          '</div>' +
          '</div>'
        );
      })
      .join('');

    track.insertAdjacentHTML(
      'beforeend',
      '<div class="timelineSectionBlocks" aria-hidden="true">' + blocksHtml + '</div>'
    );
  }

  function appendTimelineBars(startBar, endBar) {
    var track = el('timelineTrack');
    if (!track || endBar < startBar) return;
    var currentBar = Math.max(1, Math.floor(computeBarNow(state.transport)));
    var html = [];
    for (var bar = startBar; bar <= endBar; bar++) {
      html.push(buildTimelineBarMarkup(bar, currentBar));
    }
    track.insertAdjacentHTML('beforeend', html.join(''));
  }

  function getBarPixelHeight() {
    var rootStyle = window.getComputedStyle(document.documentElement);
    var cssHeight = parseFloat(rootStyle.getPropertyValue('--timeline-bar-height') || '');
    if (Number.isFinite(cssHeight) && cssHeight > 0) {
      return cssHeight;
    }
    return FALLBACK_BAR_PIXEL_HEIGHT;
  }

  function getTimelineNowY() {
    var now = el('timelineNow');
    if (!now) return FALLBACK_TIMELINE_NOW_Y;
    var y = now.offsetTop;
    return Number.isFinite(y) ? y : FALLBACK_TIMELINE_NOW_Y;
  }

  function ensureTimelineHasBar(barNumber) {
    if (!Number.isFinite(barNumber) || barNumber < 1) {
      return false;
    }
    var neededMaxBar = Math.ceil(barNumber) + 24;
    var previousLength = state.timelineBars.length;
    if (previousLength >= neededMaxBar) {
      return false;
    }
    var start = previousLength + 1;
    for (var i = start; i <= neededMaxBar; i++) {
      state.timelineBars.push(i);
    }
    appendTimelineBars(start, neededMaxBar);
    return true;
  }

  function renderTimelinePosition() {
    if (!state.transport || !el('timelineTrack')) return;
    var barNow = Math.max(1, computeBarNow(state.transport));
    if (ensureTimelineHasBar(barNow)) {
      // New rows appended; continue with current frame positioning.
    }
    var barPixelHeight = getBarPixelHeight();
    var timelineNowY = getTimelineNowY();
    var barAnchorPx = barPixelHeight * NOW_LINE_BAR_ANCHOR;
    var translateY = timelineNowY - (barNow - 1) * barPixelHeight - barAnchorPx;
    el('timelineTrack').style.transform = 'translateY(' + translateY + 'px)';
    updateCurrentBarHighlight(barNow);
  }

  function updateCurrentBarHighlight(barNow) {
    var currentBar = Math.max(1, Math.floor(barNow));
    var previous = el('timelineTrack').querySelector('.timelineBarCurrent');
    if (previous) previous.classList.remove('timelineBarCurrent');
    var target = el('timelineTrack').querySelector('[data-bar-number="' + currentBar + '"]');
    if (target) target.classList.add('timelineBarCurrent');
  }

  function renderTransport() {
    if (!state.transport) return;
    var t = state.transport;
    if (el('transportSongInfo')) {
      el('transportSongInfo').textContent = state.activeSong
        ? 'Song: ' + state.activeSong.name
        : 'No active song selected.';
    }
    setStatus(
      'transportInfo',
      'State: ' +
        t.playState +
        ' | BPM: ' +
        t.currentBpm +
        ' | Beats/bar: ' +
        t.beatsPerBar +
        ' | Bar now: ' +
        computeBarNow(t).toFixed(2)
    );
    renderTimelinePosition();
    maybeShowEndOfSongPrompt();
  }

  async function refreshSongContent() {
    if (!state.songId || state.contentRefreshPending) {
      return;
    }
    state.contentRefreshPending = true;
    try {
      var sectionData = await api('sections.php?songId=' + encodeURIComponent(String(state.songId)));
      state.sections = sectionData.sections || [];
      state.contentFreshForSongId = state.songId;
      renderSectionsList();
      renderNotesLists();
    } catch (err) {
      setStatus('transportInfo', err.message || String(err));
    } finally {
      state.contentRefreshPending = false;
    }
  }

  async function pollSongContent() {
    if (!state.sessionId || !state.songId) {
      return;
    }
    await syncSessionSongSelection();
    await refreshSongContent();
  }

  async function syncSessionSongSelection() {
    if (!state.sessionId) {
      return;
    }
    var snapshot = await api(
      'session-snapshot.php?sessionId=' + encodeURIComponent(String(state.sessionId))
    );
    var nextSongId = snapshot.songId ? Number(snapshot.songId) : null;
    var songChanged = nextSongId !== state.songId;
    state.songId = nextSongId;
    state.activeSong = snapshot.activeSong || null;
    state.setlist = snapshot.setlist || [];
    if (songChanged) {
      state.sections = snapshot.sections || [];
      state.contentFreshForSongId = state.songId;
      if (window.history && window.history.replaceState) {
        var nextUrl = 'rehearsal.php?sessionId=' + state.sessionId;
        if (state.songId) {
          nextUrl += '&songId=' + state.songId;
        }
        window.history.replaceState({}, '', nextUrl);
      }
      if (el('editorLink')) {
        el('editorLink').href = state.songId ? 'editor.php?songId=' + state.songId : 'songs.php';
        el('editorLink').textContent = state.songId ? 'Open notes editor for current song' : 'Choose a song first';
      }
      renderSectionsList();
      renderNotesLists();
      buildTimelineBars();
      renderTimelineRows();
      if (el('endOfSongPrompt')) {
        el('endOfSongPrompt').classList.add('hidden');
      }
      state.endPromptShownForSongId = null;
      state.autoPauseRequestedForSongId = null;
    }
    renderSetlist();
    renderTransport();
  }

  function startContentPolling() {
    if (state.contentPollId) clearTimeout(state.contentPollId);
    var delay =
      state.transport && state.transport.playState === 'playing'
        ? state.contentPollDelayMsPlaying
        : state.contentPollDelayMsPaused;
    state.contentPollId = setTimeout(async function () {
      await pollSongContent();
      startContentPolling();
    }, delay);
  }

  function startVisualLoop() {
    if (state.visualRafId) {
      cancelAnimationFrame(state.visualRafId);
    }
    var tick = function () {
      renderTimelinePosition();
      state.visualRafId = requestAnimationFrame(tick);
    };
    state.visualRafId = requestAnimationFrame(tick);
  }

  function wireDelegatedListHandlers() {
    if (el('rehearsalList')) {
      el('rehearsalList').addEventListener('click', function (event) {
        var openBtn = event.target.closest('.rehearsalOpenBtn');
        if (openBtn) {
          var nextSessionId = Number(openBtn.getAttribute('data-session-id'));
          var nextSongId = Number(openBtn.getAttribute('data-song-id')) || '';
          window.location.href =
            'rehearsal.php?sessionId=' + nextSessionId + (nextSongId ? '&songId=' + nextSongId : '');
          return;
        }
        var deleteBtn = event.target.closest('.rehearsalDeleteBtn');
        if (deleteBtn) {
          deleteRehearsal(Number(deleteBtn.getAttribute('data-session-id'))).catch(function (e) {
            setStatus('pageStatus', e.message || String(e));
          });
        }
      });
    }

    if (el('setlistList')) {
      el('setlistList').addEventListener('click', function (event) {
        var selectBtn = event.target.closest('.setlistSelectBtn');
        if (selectBtn) {
          var nextSongId = Number(selectBtn.getAttribute('data-song-id'));
          if (nextSongId === state.songId) return;
          selectActiveSong(nextSongId).catch(function (e) {
            setStatus('pageStatus', e.message || String(e));
          });
          return;
        }
        var moveBtn = event.target.closest('.setlistMoveBtn');
        if (moveBtn) {
          reorderSetlistSong(
            Number(moveBtn.getAttribute('data-song-id')),
            moveBtn.getAttribute('data-direction')
          ).catch(function (e) {
            setStatus('pageStatus', e.message || String(e));
          });
          return;
        }
        var removeBtn = event.target.closest('.setlistRemoveBtn');
        if (removeBtn) {
          removeSetlistSong(Number(removeBtn.getAttribute('data-song-id'))).catch(function (e) {
            setStatus('pageStatus', e.message || String(e));
          });
        }
      });
    }

    if (el('timelineTrack')) {
      el('timelineTrack').addEventListener('dblclick', function (event) {
        var sectionBlockNode = event.target.closest('.timelineSectionBlock');
        if (sectionBlockNode) {
          var sectionId = Number(sectionBlockNode.getAttribute('data-section-id'));
          if (sectionId > 0) {
            openTimelineNoteEditor(sectionId);
          }
          return;
        }
        var barNode = event.target.closest('.timelineBar');
        if (!barNode) {
          return;
        }
        var barNumber = Number(barNode.getAttribute('data-bar-number'));
        if (!(barNumber > 0)) {
          return;
        }
        var section = findSectionForBar(barNumber);
        if (!section) {
          return;
        }
        openTimelineNoteEditor(Number(section.id));
      });
    }
  }

  function getSongEndBar() {
    var sectionEndBars = state.sections.map(function (s) {
      return Number(s.barEnd);
    });
    var bars = sectionEndBars.filter(function (n) {
      return Number.isFinite(n) && n > 0;
    });
    if (!bars.length) return null;
    return Math.max.apply(null, bars);
  }

  function getSongCompletionBar() {
    var endBar = getSongEndBar();
    if (!endBar) return null;
    return endBar + 1;
  }

  function maybeShowEndOfSongPrompt() {
    var prompt = el('endOfSongPrompt');
    if (!prompt || !state.songId) return;
    var completionBar = getSongCompletionBar();
    if (!completionBar) {
      prompt.classList.add('hidden');
      return;
    }
    var barNow = computeBarNow(state.transport);
    if (barNow >= completionBar && state.endPromptShownForSongId !== state.songId) {
      state.endPromptShownForSongId = state.songId;
      prompt.classList.remove('hidden');
      var currentIndex = state.setlist.findIndex(function (item) {
        return Number(item.songId) === Number(state.songId);
      });
      var hasNext = currentIndex >= 0 && currentIndex < state.setlist.length - 1;
      if (el('endOfSongMessage')) {
        el('endOfSongMessage').textContent = hasNext
          ? 'Song finished. Replay or play the next song in the setlist.'
          : 'Song finished. Replay or stay on the last song in the setlist.';
      }
      if (el('nextSongBtn')) {
        el('nextSongBtn').disabled = !hasNext;
      }
    }
    if (barNow < completionBar) {
      prompt.classList.add('hidden');
      state.endPromptShownForSongId = null;
    }
  }

  async function loadRehearsals() {
    var data = await api('rehearsal-list.php');
    state.rehearsals = data.rehearsals || [];
    renderRehearsalList();
  }

  async function joinSession(sessionId) {
    return api('session-join.php', 'POST', {
      sessionId: sessionId,
    });
  }

  async function refreshSnapshot() {
    if (!state.sessionId) return;
    var data = await api('session-snapshot.php?sessionId=' + encodeURIComponent(String(state.sessionId)));
    state.transport = stampTransport(data.transport);
    state.sections = data.sections || [];
    state.songId = data.songId;
    state.activeSong = data.activeSong || null;
    state.setlist = data.setlist || [];
    state.endPromptShownForSongId = null;
    state.autoPauseRequestedForSongId = null;
    state.contentFreshForSongId = null;
    closeTimelineNoteEditor();
    if (window.history && window.history.replaceState) {
      var nextUrl = 'rehearsal.php?sessionId=' + state.sessionId;
      if (state.songId) {
        nextUrl += '&songId=' + state.songId;
      }
      window.history.replaceState(
        {},
        '',
        nextUrl
      );
    }
    if (el('editorLink')) {
      el('editorLink').href = state.songId ? 'editor.php?songId=' + state.songId : 'songs.php';
      el('editorLink').textContent = state.songId ? 'Open notes editor for current song' : 'Choose a song first';
    }
    renderTransport();
    renderSetlist();
    renderRehearsalList();
    renderAvailableSongs();
    renderSectionsList();
    renderNotesLists();
    buildTimelineBars();
    renderTimelineRows();
  }

  async function refreshMembers() {
    if (!state.sessionId) return;
    var data = await api('session-members.php?sessionId=' + encodeURIComponent(String(state.sessionId)));
    state.members = data.members || [];
    renderMembers();
  }

  async function pollTransport() {
    if (!state.sessionId) return;
    try {
      state.transport = stampTransport(await api(
        'transport-state.php?sessionId=' + encodeURIComponent(String(state.sessionId))
      ));
      await refreshMembers();
      await maybeAutoPauseAtSongEnd();
      state.pollingFailures = 0;
      state.pollingDelayMs = 300;
      renderTransport();
    } catch (err) {
      state.pollingFailures += 1;
      state.pollingDelayMs = Math.min(1500, 300 + state.pollingFailures * 200);
      setStatus('transportInfo', err.message || String(err));
    } finally {
      if (state.pollingId) clearTimeout(state.pollingId);
      state.pollingId = setTimeout(pollTransport, state.pollingDelayMs);
    }
  }

  function startPolling() {
    if (state.pollingId) clearTimeout(state.pollingId);
    state.pollingDelayMs = 300;
    state.pollingFailures = 0;
    state.pollingId = setTimeout(pollTransport, state.pollingDelayMs);
  }

  async function updateTransport(action, value) {
    value = value || {};
    if (!state.sessionId) throw new Error('Missing session');
    state.transport = stampTransport(await api('transport-update.php', 'POST', {
      sessionId: state.sessionId,
      action: action,
      value: value,
    }));
    renderTransport();
    startContentPolling();
  }

  async function loadAvailableSongs() {
    var data = await api('song-list.php');
    state.availableSongs = data.songs || [];
    renderAvailableSongs();
  }

  async function selectActiveSong(songId) {
    await api('session-song-select.php', 'POST', {
      sessionId: state.sessionId,
      songId: songId,
    });
    state.songId = songId;
    await refreshSnapshot();
    await refreshSongContent();
    await refreshMembers();
    await updateTransport('pause');
    await seekToSongStart();
  }

  async function addSongToSetlist(songId) {
    await api('session-song-add.php', 'POST', {
      sessionId: state.sessionId,
      songId: songId,
    });
    await refreshSnapshot();
    await loadRehearsals();
  }

  async function reorderSetlistSong(songId, direction) {
    await api('session-song-reorder.php', 'POST', {
      sessionId: state.sessionId,
      songId: songId,
      direction: direction,
    });
    await refreshSnapshot();
    await loadRehearsals();
  }

  async function removeSetlistSong(songId) {
    await api('session-song-remove.php', 'POST', {
      sessionId: state.sessionId,
      songId: songId,
    });
    await refreshSnapshot();
    await loadRehearsals();
  }

  async function createRehearsal(songId, name) {
    return api('session-create.php', 'POST', {
      songId: songId,
      name: name,
    });
  }

  async function deleteRehearsal(sessionId) {
    await api('session-delete.php', 'POST', {
      sessionId: sessionId,
    });
    if (state.sessionId === sessionId) {
      if (state.pollingId) clearTimeout(state.pollingId);
      stopHeartbeatLoop();
      state.sessionId = null;
      state.songId = null;
      state.activeSong = null;
      state.setlist = [];
      state.members = [];
      state.sections = [];
      state.transport = null;
      closeTimelineNoteEditor();
      setSessionContentVisible(false);
      if (window.history && window.history.replaceState) {
        window.history.replaceState({}, '', 'rehearsal.php');
      }
    }
    await loadRehearsals();
    setStatus('pageStatus', 'Rehearsal deleted.');
  }

  async function replayCurrentSong() {
    await updateTransport('seekBar', { bar: 1 });
    await refreshSongContent();
    await updateTransport('play');
    if (el('endOfSongPrompt')) {
      el('endOfSongPrompt').classList.add('hidden');
    }
    state.endPromptShownForSongId = null;
    state.autoPauseRequestedForSongId = null;
  }

  async function seekToSongStart() {
    await updateTransport('seekBar', { bar: 1 });
    await refreshSongContent();
    if (el('endOfSongPrompt')) {
      el('endOfSongPrompt').classList.add('hidden');
    }
    state.endPromptShownForSongId = null;
    state.autoPauseRequestedForSongId = null;
  }

  async function playNextSong() {
    var currentIndex = state.setlist.findIndex(function (item) {
      return Number(item.songId) === Number(state.songId);
    });
    if (currentIndex < 0 || currentIndex >= state.setlist.length - 1) {
      throw new Error('No next song in the setlist.');
    }
    var next = state.setlist[currentIndex + 1];
    await selectActiveSong(Number(next.songId));
  }

  async function playPreviousSong() {
    var currentIndex = state.setlist.findIndex(function (item) {
      return Number(item.songId) === Number(state.songId);
    });
    if (currentIndex <= 0) {
      throw new Error('No previous song in the setlist.');
    }
    var previous = state.setlist[currentIndex - 1];
    await selectActiveSong(Number(previous.songId));
  }

  function isTypingTarget(node) {
    if (!node) return false;
    var tag = node.tagName ? node.tagName.toLowerCase() : '';
    return tag === 'input' || tag === 'textarea' || tag === 'select' || node.isContentEditable;
  }

  function wireKeyboardShortcuts() {
    document.addEventListener('keydown', function (event) {
      if (!state.sessionId || isTypingTarget(event.target)) {
        return;
      }
      if (event.repeat) {
        return;
      }
      if (event.code === 'Space') {
        event.preventDefault();
        if (state.transportActionInFlight) {
          return;
        }
        var action =
          state.transport && state.transport.playState === 'playing'
            ? 'pause'
            : 'play';
        state.transportActionInFlight = true;
        updateTransport(action)
          .catch(function (e) {
            setStatus('transportInfo', e.message || String(e));
            notify(e.message || String(e), 'error');
          })
          .finally(function () {
            state.transportActionInFlight = false;
          });
        return;
      }
      if (event.code === 'ArrowRight') {
        event.preventDefault();
        if (state.navActionInFlight) {
          return;
        }
        state.navActionInFlight = true;
        playNextSong()
          .catch(function (e) {
            setStatus('pageStatus', e.message || String(e));
          })
          .finally(function () {
            state.navActionInFlight = false;
          });
        return;
      }
      if (event.code === 'ArrowLeft') {
        event.preventDefault();
        if (state.navActionInFlight) {
          return;
        }
        state.navActionInFlight = true;
        playPreviousSong()
          .catch(function (e) {
            setStatus('pageStatus', e.message || String(e));
          })
          .finally(function () {
            state.navActionInFlight = false;
          });
        return;
      }
      if (event.code === 'ArrowUp') {
        event.preventDefault();
        if (!state.transport || state.transportActionInFlight) {
          return;
        }
        var currentBarUp = Math.max(1, Math.floor(computeBarNow(state.transport)));
        var previousBar = Math.max(1, currentBarUp - 1);
        state.transportActionInFlight = true;
        updateTransport('seekBar', { bar: previousBar })
          .catch(function (e) {
            setStatus('transportInfo', e.message || String(e));
            notify(e.message || String(e), 'error');
          })
          .finally(function () {
            state.transportActionInFlight = false;
          });
        return;
      }
      if (event.code === 'ArrowDown') {
        event.preventDefault();
        if (!state.transport || state.transportActionInFlight) {
          return;
        }
        var currentBarDown = Math.max(1, Math.floor(computeBarNow(state.transport)));
        var nextBar = currentBarDown + 1;
        state.transportActionInFlight = true;
        updateTransport('seekBar', { bar: nextBar })
          .catch(function (e) {
            setStatus('transportInfo', e.message || String(e));
            notify(e.message || String(e), 'error');
          })
          .finally(function () {
            state.transportActionInFlight = false;
          });
        return;
      }
      if (event.code === 'Home') {
        event.preventDefault();
        if (state.transportActionInFlight) {
          return;
        }
        state.transportActionInFlight = true;
        seekToSongStart()
          .catch(function (e) {
            setStatus('transportInfo', e.message || String(e));
            notify(e.message || String(e), 'error');
          })
          .finally(function () {
            state.transportActionInFlight = false;
          });
      }
    });
  }

  async function maybeAutoPauseAtSongEnd() {
    if (!state.transport || !state.songId) return;
    if (state.transport.playState !== 'playing') return;
    if (state.contentFreshForSongId !== state.songId) {
      return;
    }
    var completionBar = getSongCompletionBar();
    if (!completionBar) return;
    var barNow = computeBarNow(state.transport);
    if (barNow < completionBar) {
      state.autoPauseRequestedForSongId = null;
      return;
    }
    if (state.autoPauseRequestedForSongId === state.songId) return;
    state.autoPauseRequestedForSongId = state.songId;
    state.transport = stampTransport(await api('transport-update.php', 'POST', {
      sessionId: state.sessionId,
      action: 'pause',
      value: {},
    }));
    renderTransport();
  }

  async function init() {
    var user = await requireAuth();
    if (!user) return;
    state.currentUser = user;

    var sessionId = parseInt(qs('sessionId'), 10);
    var songId = parseInt(qs('songId'), 10);
    await loadAvailableSongs();
    await loadRehearsals();
    initPanelState();
    wireDelegatedListHandlers();

    if (!(sessionId > 0)) {
      setSessionContentVisible(false);
      setStatus('pageStatus', 'Select a rehearsal from the list or create one from Songs.');
      return;
    }
    state.sessionId = sessionId;
    state.songId = songId > 0 ? songId : null;
    setSessionContentVisible(true);

    await joinSession(state.sessionId);
    await sendPresenceHeartbeat(true);
    await refreshSnapshot();
    await refreshSongContent();
    await refreshMembers();
    await loadRehearsals();
    startPolling();
    startContentPolling();
    startHeartbeatLoop();
    startVisualLoop();
    wirePresenceLifecycle();
    wireKeyboardShortcuts();

    el('playBtn').addEventListener('click', function () {
      updateTransport('play').catch(function (e) {
        setStatus('transportInfo', e.message);
        notify(e.message || String(e), 'error');
      });
    });
    el('pauseBtn').addEventListener('click', function () {
      updateTransport('pause').catch(function (e) {
        setStatus('transportInfo', e.message);
        notify(e.message || String(e), 'error');
      });
    });
    el('songStartBtn').addEventListener('click', function () {
      seekToSongStart().catch(function (e) {
        setStatus('transportInfo', e.message);
        notify(e.message || String(e), 'error');
      });
    });
    el('seekBtn').addEventListener('click', function () {
      updateTransport('seekBar', { bar: Number(el('seekBar').value) }).catch(function (e) {
        setStatus('transportInfo', e.message);
        notify(e.message || String(e), 'error');
      });
    });
    el('setBpmBtn').addEventListener('click', function () {
      updateTransport('setBpm', { bpm: Number(el('setBpm').value) }).catch(function (e) {
        setStatus('transportInfo', e.message);
        notify(e.message || String(e), 'error');
      });
    });
    if (el('addSongBtn')) {
      el('addSongBtn').addEventListener('click', function () {
        var newSongId = Number(el('addSongSelect').value);
        if (!(newSongId > 0)) {
          setStatus('pageStatus', 'Select a song to add to the setlist.');
          return;
        }
        addSongToSetlist(newSongId)
          .then(function () {
            setStatus('pageStatus', 'Song added to setlist.');
          })
          .catch(function (e) {
            setStatus('pageStatus', e.message || String(e));
          });
      });
    }
    if (el('replaySongBtn')) {
      el('replaySongBtn').addEventListener('click', function () {
        replayCurrentSong().catch(function (e) {
          setStatus('pageStatus', e.message || String(e));
        });
      });
    }
    if (el('createRehearsalBtn')) {
      el('createRehearsalBtn').addEventListener('click', function () {
        var newSongId = Number(el('newRehearsalSongSelect').value);
        if (!(newSongId > 0)) {
          setStatus('pageStatus', 'Select the first song for the new rehearsal.');
          return;
        }
        createRehearsal(newSongId, el('newRehearsalName').value || 'Rehearsal Session')
          .then(function (data) {
            window.location.href = 'rehearsal.php?sessionId=' + data.sessionId + '&songId=' + newSongId;
          })
          .catch(function (e) {
            setStatus('pageStatus', e.message || String(e));
          });
      });
    }
    if (el('nextSongBtn')) {
      el('nextSongBtn').addEventListener('click', function () {
        playNextSong().catch(function (e) {
          setStatus('pageStatus', e.message || String(e));
        });
      });
    }
    if (el('timelineNoteSaveBtn')) {
      el('timelineNoteSaveBtn').addEventListener('click', function () {
        saveTimelineNoteEditor().catch(function (e) {
          setStatus('pageStatus', e.message || String(e));
        });
      });
    }
    if (el('timelineNoteCancelBtn')) {
      el('timelineNoteCancelBtn').addEventListener('click', function () {
        closeTimelineNoteEditor();
      });
    }
  }

  init();
})();
