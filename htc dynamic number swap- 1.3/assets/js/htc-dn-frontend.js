/**
 * HTC Dynamic Number Frontend Logic
 * Version: 1.3.0
 */
(() => {
  const CFG = window.HTC_DN_LITE || {};
  const RULES = Array.isArray(CFG.rules) ? CFG.rules : [];

  const getParam = (name) => {
    try {
      return new URL(window.location.href).searchParams.get(name);
    } catch (e) {
      return null;
    }
  };

  const getCookie = (name) => {
    const m = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
    return m ? decodeURIComponent(m.pop()) : null;
  };

  const setCookie = (name, value, days) => {
    const d = new Date();
    d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
    const expires = 'expires=' + d.toUTCString();
    const secure = window.location.protocol === 'https:' ? ';Secure' : '';
    document.cookie = name + '=' + encodeURIComponent(value) + ';' + expires + ';Path=/;SameSite=Lax' + secure;
  };

  const wildcardMatch = (pattern, value) => {
    const p = (pattern ?? '*').toString().trim();
    const v = (value ?? '').toString();

    const escaped = p.replace(/[.+?^${}()|[\]\\]/g, '\\$&');
    const re = new RegExp('^' + escaped.replace(/\*/g, '.*') + '$', 'i');

    return re.test(v);
  };

  const refHost = () => {
    try {
      if (!document.referrer) return '';
      return new URL(document.referrer).host || '';
    } catch (e) {
      return '';
    }
  };

  const resolveRule = () => {
    const host = refHost();

    for (const r of RULES) {
      if (!r || !r.id) continue;

      const type = (r.type || 'PARAM').toUpperCase();

      if (type === 'CLICKID') {
        const v = getParam(r.param);
        if (v) return r;
      }

      if (type === 'PARAM') {
        const v = getParam(r.param);
        if (v && wildcardMatch(r.pattern, v)) return r;
      }

      if (type === 'REFERRER') {
        const target = (r.param || '').toString().trim();
        if (!host) continue;
        if (target && !host.toLowerCase().includes(target.toLowerCase())) continue;
        if (wildcardMatch(r.pattern || '*', host)) return r;
      }
    }

    return null;
  };

  const applyNumber = (num) => {
    const nodes = document.querySelectorAll('[data-htc-dn-phone]');

    nodes.forEach((node) => {
      if (num.display) {
        node.textContent = num.display;
      }

      if (node.tagName.toLowerCase() === 'a' && num.tel) {
        node.setAttribute('href', 'tel:' + num.tel);
      }
    });
  };

  const boot = () => {
    const match = resolveRule();

    if (match && match.id) {
      setCookie(CFG.cookieName, match.id, CFG.cookieDays || 30);
    }

    const rid = getCookie(CFG.cookieName);
    const chosen = rid ? RULES.find((r) => r.id === rid) : null;
    const num = chosen
      ? { display: chosen.display, tel: chosen.tel }
      : (CFG.default || {});

    applyNumber(num);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();