(function () {
  var api = window.SharedChartsApi.api;
  var requireAuth = window.SharedChartsAuth.requireAuth;
  var el = function (id) {
    return document.getElementById(id);
  };

  function qs(name) {
    var m = new RegExp('[?&]' + name + '=([^&]*)').exec(window.location.search);
    return m ? decodeURIComponent(m[1].replace(/\+/g, ' ')) : '';
  }

  async function loadSongList() {
    var data = await api('song-list.php');
    var songs = data.songs || [];
    var ul = el('songList');
    if (!ul) return;
    ul.innerHTML = songs
      .map(function (s) {
        return (
          '<li><a href="songs.php?songId=' +
          s.id +
          '">' +
          escapeHtml(s.name) +
          '</a> (ID ' +
          s.id +
          ', ' +
          s.bpm +
          ' BPM, ' +
          s.timeSignatureNum +
          '/' +
          s.timeSignatureDen +
          ') <a href="editor.php?songId=' +
          s.id +
          '">Open editor</a></li>'
        );
      })
      .join('');
  }

  function escapeHtml(input) {
    return window.SharedChartsApi.escapeHtml(input);
  }

  async function loadSongIntoForm(songId) {
    var s = await api('song-get.php?songId=' + encodeURIComponent(String(songId)));
    el('songName').value = s.name;
    el('songBpm').value = s.bpm;
    el('songSigNum').value = s.timeSignatureNum;
    el('songSigDen').value = String(s.timeSignatureDen);
    el('songInfo').textContent = 'Loaded song ID ' + s.id;
  }

  async function init() {
    var user = await requireAuth();
    if (!user) return;

    var songIdParam = parseInt(qs('songId'), 10);
    if (songIdParam > 0) {
      try {
        await loadSongIntoForm(songIdParam);
      } catch (e) {
        el('songInfo').textContent = e.message || String(e);
      }
    }

    await loadSongList();

    el('createSongBtn').addEventListener('click', async function () {
      try {
        var data = await api('song-create.php', 'POST', {
          name: el('songName').value,
          bpm: Number(el('songBpm').value),
          timeSignatureNum: Number(el('songSigNum').value),
          timeSignatureDen: Number(el('songSigDen').value),
        });
        el('songInfo').textContent = 'Song created (ID ' + data.songId + ')';
        window.history.replaceState({}, '', 'songs.php?songId=' + data.songId);
        await loadSongList();
      } catch (err) {
        el('songInfo').textContent = err.message || String(err);
      }
    });

    el('updateSongBtn').addEventListener('click', async function () {
      try {
        var sid = parseInt(qs('songId'), 10);
        if (!(sid > 0)) throw new Error('Open a song from the list or create one first');
        await api('song-update.php', 'POST', {
          songId: sid,
          name: el('songName').value,
          bpm: Number(el('songBpm').value),
          timeSignatureNum: Number(el('songSigNum').value),
          timeSignatureDen: Number(el('songSigDen').value),
        });
        el('songInfo').textContent = 'Song ' + sid + ' updated';
        await loadSongList();
      } catch (err) {
        el('songInfo').textContent = err.message || String(err);
      }
    });

    el('createSessionBtn').addEventListener('click', async function () {
      try {
        var sid = parseInt(qs('songId'), 10);
        if (!(sid > 0)) throw new Error('Select or create a song first');
        var data = await api('session-create.php', 'POST', {
          songId: sid,
          name: el('sessionName').value || 'Rehearsal Session',
        });
        el('inviteOut').textContent = 'Rehearsal created.';
        window.location.href =
          'rehearsal.php?sessionId=' + data.sessionId + '&songId=' + sid;
      } catch (err) {
        el('sessionInfo').textContent = err.message || String(err);
      }
    });
  }

  init();
})();
