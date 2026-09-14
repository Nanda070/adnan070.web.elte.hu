/**
 * Quack site cookie consent — charcoal + scarlet banner (RU/EN/HU).
 * Essential prefs (language) stay in localStorage either way; Accept sets quack_cookie_ok.
 */
(function () {
  'use strict';

  var COOKIE_NAME = 'quack_cookie_ok';
  var CHOICE_KEY = 'quack_cookie_choice';
  var LANG_KEY = 'quack_lang';

  var copy = {
    en: {
      title: 'Cookies & local prefs',
      body: 'This site does not sell your data. We may store: language preference (localStorage <code>quack_lang</code>), this consent choice, and duck UI prefs on your device. No ad trackers.',
      accept: 'Accept',
      essential: 'Essential only',
      motto: 'privacy is a right not a privilege'
    },
    ru: {
      title: 'Cookies и локальные настройки',
      body: 'Мы не продаём ваши данные. Можем хранить: язык (localStorage <code>quack_lang</code>), выбор согласия и настройки утки на вашем устройстве. Без рекламных трекеров.',
      accept: 'Принять',
      essential: 'Только необходимое',
      motto: 'приватность — право, а не привилегия'
    },
    hu: {
      title: 'Sütik és helyi beállítások',
      body: 'Nem adjuk el az adataidat. Tárolhatjuk: nyelvi preferencia (localStorage <code>quack_lang</code>), ez a hozzájárulás, és kacsa UI-beállítások az eszközödön. Nincs hirdetéskövető.',
      accept: 'Elfogadom',
      essential: 'Csak lényeges',
      motto: 'a magánélet jog, nem kiváltság'
    }
  };

  function detectLang() {
    try {
      var params = new URLSearchParams(window.location.search);
      var q = params.get('lang');
      if (q && copy[q]) return q;
      var saved = localStorage.getItem(LANG_KEY);
      if (saved && copy[saved]) return saved;
    } catch (_) {}
    var htmlLang = (document.documentElement.lang || '').slice(0, 2);
    if (copy[htmlLang]) return htmlLang;
    return 'en';
  }

  function hasChoice() {
    try {
      if (localStorage.getItem(CHOICE_KEY)) return true;
    } catch (_) {}
    return /(?:^|;\s*)quack_cookie_ok=1(?:;|$)/.test(document.cookie);
  }

  function setCookie(name, value, days) {
    var maxAge = days * 24 * 60 * 60;
    var secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = name + '=' + value + '; Path=/; Max-Age=' + maxAge + '; SameSite=Lax' + secure;
  }

  function clearConsentCookie() {
    document.cookie = COOKIE_NAME + '=; Path=/; Max-Age=0; SameSite=Lax';
  }

  function hideBanner(el) {
    if (!el) return;
    el.classList.add('quack-cookie-hide');
    setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 280);
  }

  function injectStyles() {
    if (document.getElementById('quack-cookie-styles')) return;
    var style = document.createElement('style');
    style.id = 'quack-cookie-styles';
    style.textContent = [
      '.quack-cookie-banner{position:fixed;left:12px;right:12px;bottom:calc(12px + env(safe-area-inset-bottom,0px));z-index:9999;',
      'max-width:520px;margin:0 auto;padding:14px 16px;border-radius:16px;',
      'background:rgba(14,14,16,0.96);border:1px solid rgba(225,29,72,0.35);',
      'box-shadow:0 18px 50px rgba(0,0,0,0.55),0 0 0 1px rgba(255,255,255,0.04);',
      'color:#e4e4e7;font-family:inherit;backdrop-filter:blur(12px);',
      'transition:opacity .25s ease,transform .25s ease;}',
      '.quack-cookie-banner.quack-cookie-hide{opacity:0;transform:translateY(12px);pointer-events:none;}',
      '.quack-cookie-title{font-size:.78rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#fb7185;margin:0 0 6px;}',
      '.quack-cookie-body{font-size:.82rem;line-height:1.45;color:#a1a1aa;margin:0 0 8px;}',
      '.quack-cookie-body code{font-family:ui-monospace,monospace;font-size:.75rem;color:#fda4af;}',
      '.quack-cookie-motto{font-size:.65rem;font-style:italic;color:#64748b;opacity:.62;margin:0 0 12px;}',
      '.quack-cookie-actions{display:flex;flex-wrap:wrap;gap:8px;}',
      '.quack-cookie-btn{border:none;border-radius:10px;padding:8px 14px;font:inherit;font-size:.82rem;font-weight:700;cursor:pointer;touch-action:manipulation;}',
      '.quack-cookie-accept{background:linear-gradient(135deg,#e11d48,#9f1239);color:#fff;}',
      '.quack-cookie-essential{background:rgba(255,255,255,.06);color:#e4e4e7;border:1px solid rgba(255,255,255,.12);}',
      '@media (max-width:520px){.quack-cookie-banner{bottom:calc(78px + env(safe-area-inset-bottom,0px));}}'
    ].join('');
    document.head.appendChild(style);
  }

  function render(lang) {
    lang = copy[lang] ? lang : 'en';
    var d = copy[lang];
    var existing = document.getElementById('quackCookieBanner');
    if (existing) existing.remove();
    if (hasChoice()) return;

    injectStyles();
    var banner = document.createElement('aside');
    banner.id = 'quackCookieBanner';
    banner.className = 'quack-cookie-banner';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-label', d.title);
    banner.innerHTML =
      '<div class="quack-cookie-title"></div>' +
      '<p class="quack-cookie-body"></p>' +
      '<p class="quack-cookie-motto"></p>' +
      '<div class="quack-cookie-actions">' +
      '<button type="button" class="quack-cookie-btn quack-cookie-accept" id="quackCookieAccept"></button>' +
      '<button type="button" class="quack-cookie-btn quack-cookie-essential" id="quackCookieEssential"></button>' +
      '</div>';
    banner.querySelector('.quack-cookie-title').textContent = d.title;
    banner.querySelector('.quack-cookie-body').innerHTML = d.body;
    banner.querySelector('.quack-cookie-motto').textContent = d.motto;
    banner.querySelector('#quackCookieAccept').textContent = d.accept;
    banner.querySelector('#quackCookieEssential').textContent = d.essential;
    document.body.appendChild(banner);

    banner.querySelector('#quackCookieAccept').addEventListener('click', function () {
      try { localStorage.setItem(CHOICE_KEY, 'accepted'); } catch (_) {}
      setCookie(COOKIE_NAME, '1', 365);
      hideBanner(banner);
    });
    banner.querySelector('#quackCookieEssential').addEventListener('click', function () {
      try { localStorage.setItem(CHOICE_KEY, 'essential'); } catch (_) {}
      clearConsentCookie();
      hideBanner(banner);
    });
  }

  window.quackApplyCookieLang = function (lang) {
    if (!hasChoice()) render(lang || detectLang());
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { render(detectLang()); });
  } else {
    render(detectLang());
  }
})();
