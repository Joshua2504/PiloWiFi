/**
 * I18nManager - Handles internationalization for the JarnoWiFi site
 */
export class I18nManager {
  constructor() {
    this.translations = null;
    this.currentLang = 'nl';
    this.supportedLanguages = ['nl', 'en', 'de'];
  }

  /**
   * Load translations from external JSON file
   */
  async loadTranslations() {
    try {
      const response = await fetch('/locales/translations.json');
      if (!response.ok) {
        throw new Error(`Failed to load translations: ${response.status}`);
      }
      this.translations = await response.json();
      return this.translations;
    } catch (error) {
      console.error('Error loading translations:', error);
      throw error;
    }
  }

  /**
   * Normalize language code (e.g., 'en-US' -> 'en')
   */
  normalizeLang(lang) {
    if (!lang) return null;
    const normalized = lang.toLowerCase().split('-')[0];
    return this.supportedLanguages.includes(normalized) ? normalized : null;
  }

  /**
   * Get language from URL path (e.g., /en/, /de/, /nl/)
   */
  getLanguageFromPath() {
    const path = window.location.pathname;
    const match = path.match(/^\/([a-z]{2})(?:\/|$)/);
    if (match) {
      const lang = match[1];
      return this.normalizeLang(lang);
    }
    return null;
  }

  /**
   * Get language from browser navigator
   */
  getLanguageFromNavigator() {
    if (navigator.language) {
      return this.normalizeLang(navigator.language);
    }
    if (navigator.languages && navigator.languages.length > 0) {
      return this.normalizeLang(navigator.languages[0]);
    }
    return null;
  }

  /**
   * Get language from localStorage
   */
  getLanguageFromStorage() {
    const stored = localStorage.getItem('preferredLanguage');
    return this.normalizeLang(stored);
  }

  /**
   * Save language preference to localStorage
   */
  saveLanguageToStorage(lang) {
    localStorage.setItem('preferredLanguage', lang);
  }

  /**
   * Determine which language to use
   */
  determineLanguage() {
    // Priority: URL path > localStorage > browser > default
    return (
      this.getLanguageFromPath() ||
      this.getLanguageFromStorage() ||
      this.getLanguageFromNavigator() ||
      'nl'
    );
  }

  /**
   * Set the current language and translate the page
   */
  setLanguage(lang, options = {}) {
    const { updateUrl = true, saveToStorage = true } = options;

    // Normalize and validate language
    const normalized = this.normalizeLang(lang);
    if (!normalized) {
      console.warn(`Unsupported language: ${lang}`);
      return;
    }

    this.currentLang = normalized;

    // Save to storage
    if (saveToStorage) {
      this.saveLanguageToStorage(normalized);
    }

    // Update URL if requested - use path-based routing
    if (updateUrl) {
      const path = window.location.pathname;
      // Replace or add the language prefix
      let newPath = path.replace(/^\/[a-z]{2}(?:\/|$)/, `/${normalized}/`);
      if (newPath === path) {
        // No language prefix found, add it
        newPath = `/${normalized}${path === '/' ? '/' : path}`;
      }
      window.history.replaceState({}, '', newPath);
    }

    // Update language selector dropdown if present
    this.updateLanguageDropdown(normalized);

    // Translate the page
    this.translatePage(normalized);

    // Update meta tags
    this.updateMetaTags(normalized);
  }

  /**
   * Update the language dropdown UI
   */
  updateLanguageDropdown(lang) {
    const dropdown = document.getElementById('languageDropdown');
    if (!dropdown) return;

    const flags = { nl: '🇳🇱', en: '🇬🇧', de: '🇩🇪' };
    const labels = { nl: 'NL', en: 'EN', de: 'DE' };

    dropdown.innerHTML = `${flags[lang]} ${labels[lang]}`;
  }

  /**
   * Translate all elements with data-i18n attribute
   */
  translatePage(lang) {
    if (!this.translations || !this.translations[lang]) {
      console.error(`Translations not loaded for language: ${lang}`);
      return;
    }

    const dict = this.translations[lang];
    const elements = document.querySelectorAll('[data-i18n]');

    elements.forEach((el) => {
      const key = el.getAttribute('data-i18n');
      const translation = this.getNestedTranslation(dict, key);

      if (translation) {
        el.textContent = translation;
      } else {
        console.warn(`Translation missing for key: ${key} in language: ${lang}`);
      }
    });
  }

  /**
   * Get nested translation value (e.g., "meta.title" or "nav.services")
   * Handles both nested objects and flat dotted keys
   */
  getNestedTranslation(dict, key) {
    // First, try to get the key as a flat string
    if (dict[key] !== undefined) {
      return dict[key];
    }
    
    // If not found as flat key, try nested lookup
    return key.split('.').reduce((obj, k) => (obj && obj[k] !== undefined ? obj[k] : null), dict);
  }

  /**
   * Update meta tags with translated content
   */
  updateMetaTags(lang) {
    if (!this.translations || !this.translations[lang]) return;

    const dict = this.translations[lang];
    const meta = dict.meta;

    if (meta) {
      // Update title
      if (meta.title) {
        document.title = meta.title;
      }

      // Update meta description
      if (meta.description) {
        const metaDesc = document.querySelector('meta[name="description"]');
        if (metaDesc) {
          metaDesc.setAttribute('content', meta.description);
        }
      }

      // Update Open Graph tags
      const ogTitle = document.querySelector('meta[property="og:title"]');
      if (ogTitle && meta.title) {
        ogTitle.setAttribute('content', meta.title);
      }

      const ogDesc = document.querySelector('meta[property="og:description"]');
      if (ogDesc && meta.description) {
        ogDesc.setAttribute('content', meta.description);
      }
    }
  }

  /**
   * Initialize i18n system
   */
  async init() {
    await this.loadTranslations();
    const lang = this.determineLanguage();
    this.setLanguage(lang, { updateUrl: false, saveToStorage: true });
    this.setupLanguageDropdown();
  }

  /**
   * Setup event listeners for language dropdown
   */
  setupLanguageDropdown() {
    const dropdown = document.querySelector('.dropdown-menu');
    if (!dropdown) return;

    dropdown.addEventListener('click', (e) => {
      const link = e.target.closest('.dropdown-item');
      if (!link) return;

      e.preventDefault();
      const lang = link.getAttribute('data-lang');
      if (lang) {
        this.setLanguage(lang, { updateUrl: true, saveToStorage: true });
      }
    });
  }

  /**
   * Get current language
   */
  getCurrentLanguage() {
    return this.currentLang;
  }

  /**
   * Get translation for a specific key
   */
  t(key, lang = null) {
    const targetLang = lang || this.currentLang;
    if (!this.translations || !this.translations[targetLang]) {
      return key;
    }
    return this.getNestedTranslation(this.translations[targetLang], key) || key;
  }
}

// Export singleton instance
export const i18n = new I18nManager();
