(function () {
  var api = window.SharedChartsApi.api;
  var toast = window.SharedChartsToast;
  var el = function (id) {
    return document.getElementById(id);
  };

  function notify(message, type) {
    if (toast && typeof toast.show === 'function') {
      toast.show(message, type || 'info');
    }
  }

  async function boot() {
    try {
      var me = await api('auth-me.php');
      if (me && me.authenticated) {
        window.location.href = 'songs.php';
        return;
      }
      var data = await api('auth-users.php');
      var options = ['<option value="">Select a user</option>'];
      (data.users || []).forEach(function (user) {
        options.push(
          '<option value="' +
            String(user.displayName)
              .replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;') +
            '">' +
            String(user.displayName)
              .replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;') +
            '</option>'
        );
      });
      el('displayName').innerHTML = options.join('');
    } catch (_) {}
  }

  el('displayName').addEventListener('change', async function () {
    try {
      var displayName = el('displayName').value.trim();
      if (!displayName) {
        el('authStatus').textContent = '';
        return;
      }
      el('authStatus').textContent = 'Logging in...';
      notify('Logging in...', 'info');
      await api('auth-login.php', 'POST', { displayName: displayName });
      window.location.href = 'songs.php';
    } catch (err) {
      el('authStatus').textContent = err.message || String(err);
      notify(err.message || String(err), 'error');
    }
  });

  boot();
})();
