(function (window, document) {
  const DAY_IN_SECONDS = 86400;

  function setCookie(name, value, days) {
    const maxAge = Math.max(1, Math.floor(days * DAY_IN_SECONDS));
    document.cookie = [
      name + '=' + encodeURIComponent(value),
      'path=/',
      'max-age=' + maxAge,
      'SameSite=Lax'
    ].join('; ');
  }

  function getCookie(name) {
    const prefix = name + '=';
    return document.cookie
      .split('; ')
      .find((row) => row.indexOf(prefix) === 0)
      ?.slice(prefix.length) || '';
  }

  function applyTheme(isDark, logoId) {
    const logo = logoId ? document.getElementById(logoId) : null;
    document.body.classList.toggle('dark-mode', isDark);

    if (logo) {
      logo.src = isDark ? 'assets/logo-dark.png' : 'assets/logo-light.png';
    }
  }

  function initThemeToggle(options) {
    const button = document.getElementById(options.buttonId);
    const storedTheme = getCookie('bloodline_theme');
    let isDark = storedTheme === 'dark';

    applyTheme(isDark, options.logoId);

    if (!button) {
      return;
    }

    button.addEventListener('click', function () {
      isDark = !isDark;
      applyTheme(isDark, options.logoId);
      setCookie('bloodline_theme', isDark ? 'dark' : 'light', 365);
    });
  }

  function trackLastVisitedPage(pageName) {
    setCookie('bloodline_last_page', pageName, 30);
  }

  window.BloodlinePrefs = {
    getCookie,
    initThemeToggle,
    setCookie,
    trackLastVisitedPage
  };
})(window, document);
