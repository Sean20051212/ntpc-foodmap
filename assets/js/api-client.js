(function () {
  const DEFAULT_BASE = "../api";

  function apiBase() {
    const script = document.currentScript;
    return script?.dataset.apiBase || DEFAULT_BASE;
  }

  const BASE_URL = apiBase().replace(/\/+$/, "");

  function normalizePath(path) {
    return String(path).replace(/^\/+/, "");
  }

  async function apiRequest(path, options = {}) {
    const init = {
      credentials: "same-origin",
      ...options,
      headers: {
        Accept: "application/json",
        ...(options.headers || {}),
      },
    };

    if (init.body && typeof init.body === "object" && !(init.body instanceof FormData)) {
      init.headers["Content-Type"] = init.headers["Content-Type"] || "application/json";
      init.body = JSON.stringify(init.body);
    }

    const response = await fetch(`${BASE_URL}/${normalizePath(path)}`, init);
    const text = await response.text();
    let payload = null;

    if (text) {
      try {
        payload = JSON.parse(text);
      } catch (error) {
        const parseError = new Error("API 回傳格式不是 JSON");
        parseError.status = response.status;
        throw parseError;
      }
    }

    if (!response.ok || payload?.ok === false) {
      const error = new Error(payload?.error || `API request failed: ${response.status}`);
      error.status = response.status;
      error.payload = payload;
      throw error;
    }

    return payload?.data || {};
  }

  function redirectToLogin(next = window.location.pathname + window.location.search) {
    window.location.href = `login.php?next=${encodeURIComponent(next)}`;
  }

  async function requireCurrentUser() {
    try {
      const data = await apiRequest("auth/me.php");
      return data.user;
    } catch (error) {
      if (error.status === 401) {
        redirectToLogin();
        return null;
      }
      throw error;
    }
  }

  function formatDateTime(value) {
    if (!value) return "";
    const date = new Date(String(value).replace(" ", "T"));
    if (Number.isNaN(date.getTime())) return String(value);
    const pad = (n) => String(n).padStart(2, "0");
    return `${date.getFullYear()}/${pad(date.getMonth() + 1)}/${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
  }

  function parseMaybeJson(value, fallback = {}) {
    if (!value) return fallback;
    if (typeof value === "object") return value;
    try {
      return JSON.parse(value);
    } catch (error) {
      return fallback;
    }
  }

  function readableError(error, fallback = "操作失敗，請稍後再試") {
    if (!error) return fallback;
    if (error.status === 401) return "請先登入後再操作";
    return error.message || fallback;
  }

  Object.assign(window, {
    apiRequest,
    redirectToLogin,
    requireCurrentUser,
    formatDateTime,
    parseMaybeJson,
    readableError,
  });
})();
