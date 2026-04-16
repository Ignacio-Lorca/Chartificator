(function (global) {
  var api = global.SharedChartsApi.api;
  var THEME_KEY = 'sharedChartsTheme';

  function applyTheme(theme) {
    var root = document.documentElement;
    if (!root) return;
    if (theme === 'dark') {
      root.setAttribute('data-theme', 'dark');
    } else {
      root.removeAttribute('data-theme');
    }
    updateThemeToggleLabel(theme);
  }

  function getStoredTheme() {
    try {
      return global.localStorage.getItem(THEME_KEY) || 'light';
    } catch (_) {
      return 'light';
    }
  }

  function saveTheme(theme) {
    try {
      global.localStorage.setItem(THEME_KEY, theme);
    } catch (_) {}
  }

  function updateThemeToggleLabel(theme) {
    var button = document.getElementById('themeToggleBtn');
    if (!button) return;
    button.textContent = theme === 'dark' ? 'Light mode' : 'Dark mode';
  }

  function wireThemeToggle() {
    var button = document.getElementById('themeToggleBtn');
    if (!button || button.__sharedChartsThemeBound) return;
    button.__sharedChartsThemeBound = true;
    updateThemeToggleLabel(getStoredTheme());
    button.addEventListener('click', function () {
      var nextTheme = getStoredTheme() === 'dark' ? 'light' : 'dark';
      saveTheme(nextTheme);
      applyTheme(nextTheme);
    });
  }

  function initTheme() {
    applyTheme(getStoredTheme());
    wireThemeToggle();
  }

  async function authMe() {
    return api('auth-me.php');
  }

  async function logout() {
    return api('auth-logout.php', 'POST', {});
  }

  function wireUserChrome(user) {
    var display = document.getElementById('currentUserDisplay');
    if (display) {
      display.textContent = user && user.displayName ? 'Signed in as ' + user.displayName : '';
    }

    var logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
      logoutBtn.style.display = user && user.authenticated ? 'inline-block' : 'none';
      if (!logoutBtn.__sharedChartsBound) {
        logoutBtn.__sharedChartsBound = true;
        logoutBtn.addEventListener('click', async function () {
          try {
            await logout();
          } catch (_) {}
          window.location.href = 'index.php';
        });
      }
    }
  }

  /** Redirect to login if not authenticated. Returns user data or null after redirect. */
  async function requireAuth() {
    var me = await authMe();
    if (!me || !me.authenticated) {
      window.location.href = 'index.php';
      return null;
    }
    wireUserChrome(me);
    return me;
  }

  global.SharedChartsAuth = {
    authMe: authMe,
    logout: logout,
    requireAuth: requireAuth,
    wireUserChrome: wireUserChrome,
    initTheme: initTheme,
  };

  initTheme();
})(window);
