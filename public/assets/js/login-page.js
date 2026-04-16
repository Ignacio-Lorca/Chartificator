(function () {
  var api = window.SharedChartsApi.api;
  var el = function (id) {
    return document.getElementById(id);
  };

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
      await api('auth-login.php', 'POST', { displayName: displayName });
      window.location.href = 'songs.php';
    } catch (err) {
      el('authStatus').textContent = err.message || String(err);
    }
  });

  boot();
})();
