/**
 * i18n.js — DIY Lab Inventory Localization Engine
 * Zero-dependency ES6 module.
 * Usage:  await localizationController.init();
 *         localizationController.t('nav.dashboard')
 *         localizationController.t('inventory.location_status', { bin: 'A1', coord: 'AA-3' })
 */
const localizationController = (() => {
  // ── State ──────────────────────────────────────────────────────────────────
  let _dict     = {};   // active locale dictionary
  let _fallback = {};   // en.json used as fallback when key is missing
  let _lang     = 'en';

  const STORAGE_KEY = 'diy_inventory_lang';
  const LOCALES_PATH = 'assets/locales/';
  const RTL_LANGS   = ['he', 'ar', 'fa', 'ur'];
  const SUPPORTED   = ['en', 'he', 'es', 'uk'];

  // ── Helpers ────────────────────────────────────────────────────────────────

  /** Resolve a dot-notation key against a dictionary object */
  function _resolve(obj, path) {
    return path.split('.').reduce((o, k) => (o != null ? o[k] : undefined), obj);
  }

  /** Replace {{param}} placeholders with values from a params object */
  function _interpolate(str, params) {
    if (!params || typeof str !== 'string') return str;
    return Object.entries(params).reduce(
      (s, [k, v]) => s.replace(new RegExp(`\\{\\{${k}\\}\\}`, 'g'), v),
      str
    );
  }

  /** Fetch a locale JSON file. Returns parsed object or {} on failure. */
  async function _fetchLocale(langCode) {
    try {
      const res = await fetch(`${LOCALES_PATH}${langCode}.json?v=2`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return await res.json();
    } catch (e) {
      console.warn(`[i18n] Failed to load locale '${langCode}':`, e.message);
      return {};
    }
  }

  // ── Core API ───────────────────────────────────────────────────────────────

  /**
   * t(path, params?)
   * Resolves a translation key with optional interpolation.
   * Falls back to en.json, then to the key path itself.
   */
  function t(path, params) {
    const val = _resolve(_dict, path) ?? _resolve(_fallback, path) ?? path;
    return _interpolate(val, params);
  }

  /**
   * loadLocale(langCode)
   * Fetches and activates the given locale. Updates dir, lang attributes,
   * persists to localStorage, then re-applies all data-i18n attributes.
   */
  async function loadLocale(langCode) {
    if (!SUPPORTED.includes(langCode)) {
      console.warn(`[i18n] '${langCode}' not in supported list. Falling back to 'en'.`);
      langCode = 'en';
    }

    _lang = langCode;

    // Load target locale (and en fallback if not already English)
    const [targetDict, fallbackDict] = await Promise.all([
      _fetchLocale(langCode),
      langCode !== 'en' ? _fetchLocale('en') : Promise.resolve({}),
    ]);

    _dict     = targetDict;
    _fallback = langCode !== 'en' ? fallbackDict : {};

    // Set HTML dir + lang
    const isRTL = RTL_LANGS.includes(langCode);
    document.documentElement.dir  = isRTL ? 'rtl' : 'ltr';
    document.documentElement.lang = langCode;

    // Persist selection
    localStorage.setItem(STORAGE_KEY, langCode);

    // Patch sidebar open/close functions to work correctly in RTL
    _patchSidebar(isRTL);

    // Isolate English-only content from RTL BiDi reordering
    _isolateLTRContent(isRTL);

    // Re-render all annotated elements
    applyTranslations();

    // Sync the language selector if it exists on this page
    const sel = document.getElementById('lang-select');
    if (sel) sel.value = langCode;

    return langCode;
  }

  /**
   * _patchSidebar(isRTL)
   * In RTL mode the Tailwind -translate-x-full / lg:translate-x-0 classes
   * conflict with our CSS right-anchoring. We replace the global openSidebar /
   * closeSidebar functions with RTL-aware versions that use .sidebar-open.
   */
  function _patchSidebar(isRTL) {
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebar-overlay');
    if (!sidebar) return;

    if (isRTL) {
      // Remove Tailwind translation classes — CSS takes over positioning
      sidebar.classList.remove('-translate-x-full', 'lg:translate-x-0');
      sidebar.classList.add('rtl-anchored');

      window.openSidebar = function () {
        sidebar.classList.add('sidebar-open');
        if (overlay) overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
      };
      window.closeSidebar = function () {
        sidebar.classList.remove('sidebar-open');
        if (overlay) overlay.classList.add('hidden');
        document.body.style.overflow = '';
      };
    } else {
      // Restore LTR Tailwind classes
      sidebar.classList.remove('rtl-anchored', 'sidebar-open');
      sidebar.classList.add('-translate-x-full');
      // Restore original open/close functions
      window.openSidebar = function () {
        sidebar.classList.remove('-translate-x-full');
        if (overlay) overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
      };
      window.closeSidebar = function () {
        sidebar.classList.add('-translate-x-full');
        if (overlay) overlay.classList.add('hidden');
        document.body.style.overflow = '';
      };
    }
  }

  /**
   * _isolateLTRContent(isRTL)
   * Chat bubbles, code blocks, and suggestion chips contain English (or mixed)
   * content that should not be BiDi-reordered when the page is RTL.
   * Add dir="ltr" to these nodes so punctuation stays on the correct side.
   */
  function _isolateLTRContent(isRTL) {
    const ltrSelectors = [
      '.msg-user', '.msg-ai', '.chip',
      'pre', 'code',
      '.enrich-log', '.spec-block',
    ];
    ltrSelectors.forEach(sel => {
      document.querySelectorAll(sel).forEach(el => {
        if (isRTL) {
          el.setAttribute('dir', 'ltr');
        } else {
          el.removeAttribute('dir');
        }
      });
    });
  }

  /**
   * applyTranslations()
   * Walks the DOM and fills in translated text for all data-i18n* attributes.
   * Safe to call multiple times.
   */
  function applyTranslations() {
    // Text content
    document.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.dataset.i18n;
      const translated = t(key);
      // Preserve child elements (e.g. SVG icons inside nav links)
      // Only update the last text node if element has children
      const nodes = Array.from(el.childNodes);
      const textNodes = nodes.filter(n => n.nodeType === Node.TEXT_NODE);
      if (textNodes.length > 0) {
        // Replace the last (or only) text node
        textNodes[textNodes.length - 1].textContent = ' ' + translated;
      } else if (el.children.length === 0) {
        el.textContent = translated;
      }
    });

    // Standalone text (elements with only text, no children to preserve)
    document.querySelectorAll('[data-i18n-text]').forEach(el => {
      el.textContent = t(el.dataset.i18nText);
    });

    // Placeholder attributes
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
      el.placeholder = t(el.dataset.i18nPlaceholder);
    });

    // Title / tooltip attributes
    document.querySelectorAll('[data-i18n-title]').forEach(el => {
      el.title = t(el.dataset.i18nTitle);
    });

    // aria-label attributes
    document.querySelectorAll('[data-i18n-aria]').forEach(el => {
      el.setAttribute('aria-label', t(el.dataset.i18nAria));
    });
  }

  /**
   * init()
   * Called once on page load. Reads saved preference from localStorage,
   * falls back to browser language then 'en'.
   */
  async function init() {
    const saved   = localStorage.getItem(STORAGE_KEY);
    const browser = (navigator.language || 'en').split('-')[0].toLowerCase();
    const lang    = SUPPORTED.includes(saved) ? saved
                  : SUPPORTED.includes(browser) ? browser
                  : 'en';
    await loadLocale(lang);
  }

  /** getCurrentLang() — returns the active locale code */
  function getCurrentLang() { return _lang; }

  /** getSupportedLangs() — returns the list of supported locale codes */
  function getSupportedLangs() { return [...SUPPORTED]; }

  // ── Public API ─────────────────────────────────────────────────────────────
  return { init, t, loadLocale, applyTranslations, getCurrentLang, getSupportedLangs };
})();
