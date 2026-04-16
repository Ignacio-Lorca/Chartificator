/**
 * Shared API client. Assumes pages live under public/ and API is at ../api/
 */
(function (global) {
  function apiBase() {
    var m = document.querySelector('meta[name="api-base"]');
    var base = m && m.getAttribute('content') ? m.getAttribute('content').trim() : '../api';
    return base.replace(/\/$/, '');
  }

  async function api(path, method, body) {
    var url = apiBase() + '/' + path.replace(/^\//, '');
    var options = { method: method || 'GET', credentials: 'same-origin', headers: {} };
    if (body !== undefined) {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(body);
    }
    var res = await fetch(url, options);
    var text = await res.text();
    var json = null;
    try {
      json = text ? JSON.parse(text) : null;
    } catch (e) {
      throw new Error(res.status + ' ' + (text.slice(0, 120) || 'Invalid response'));
    }
    if (!json || !json.ok) {
      throw new Error((json && json.error) || 'Request failed');
    }
    return json.data;
  }

  function escapeHtml(input) {
    return String(input)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function computeBarNow(transport) {
    if (!transport) return 1;
    if (transport.playState !== 'playing') return transport.barOffset;
    var msPerBar = (60000 / transport.currentBpm) * transport.beatsPerBar;
    var nowMs = Date.now();
    var clientReceivedAtMs = Number(transport.clientReceivedAtMs || 0);
    var serverNowMs = Number(transport.serverNowMs || 0);
    var baselineNowMs = serverNowMs > 0 ? serverNowMs : nowMs;
    var elapsedSincePayloadMs =
      clientReceivedAtMs > 0 ? Math.max(0, nowMs - clientReceivedAtMs) : 0;
    var syncedNowMs = baselineNowMs + elapsedSincePayloadMs;
    return transport.barOffset + ((syncedNowMs - transport.startedAtMs) / msPerBar);
  }

  global.SharedChartsApi = {
    api: api,
    apiBase: apiBase,
    escapeHtml: escapeHtml,
    computeBarNow: computeBarNow,
  };
})(typeof window !== 'undefined' ? window : this);
