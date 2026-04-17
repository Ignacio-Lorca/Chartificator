(function (global) {
  var ACTIVE_TOASTS = new Map();

  function getRoot() {
    var root = document.getElementById('appToastRoot');
    if (root) return root;
    root = document.createElement('div');
    root.id = 'appToastRoot';
    root.className = 'appToastRoot';
    root.setAttribute('aria-live', 'polite');
    root.setAttribute('aria-atomic', 'true');
    document.body.appendChild(root);
    return root;
  }

  function normalizeType(type) {
    if (type === 'success' || type === 'error' || type === 'info') return type;
    return 'info';
  }

  function show(message, type, timeoutMs) {
    var text = String(message || '').trim();
    if (!text) return;
    var tone = normalizeType(type);
    var ttl = Number(timeoutMs) > 0 ? Number(timeoutMs) : 3200;
    var key = tone + '::' + text;
    var existing = ACTIVE_TOASTS.get(key);
    if (existing) {
      clearTimeout(existing.timerId);
      existing.timerId = setTimeout(function () {
        remove(existing.node, key);
      }, ttl);
      return;
    }

    var node = document.createElement('div');
    node.className = 'appToast appToast' + tone.charAt(0).toUpperCase() + tone.slice(1);
    node.textContent = text;
    getRoot().appendChild(node);

    var timerId = setTimeout(function () {
      remove(node, key);
    }, ttl);

    ACTIVE_TOASTS.set(key, { node: node, timerId: timerId });
  }

  function remove(node, key) {
    if (!node || !node.parentNode) {
      ACTIVE_TOASTS.delete(key);
      return;
    }
    node.classList.add('appToastExit');
    setTimeout(function () {
      if (node.parentNode) node.parentNode.removeChild(node);
      ACTIVE_TOASTS.delete(key);
    }, 160);
  }

  global.SharedChartsToast = {
    show: show,
  };
})(typeof window !== 'undefined' ? window : this);
