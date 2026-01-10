(function (window, document) {
  function setActiveNav(placeholder, activeKey) {
    if (!placeholder || !activeKey) {
      return;
    }
    const activeLink = placeholder.querySelector(`[data-nav-key="${activeKey}"]`);
    if (activeLink) {
      activeLink.classList.add("active");
      activeLink.setAttribute("aria-current", "page");
    }
  }

  function getCurrentLanguage() {
    const urlParams = new URLSearchParams(window.location.search);
    const lang = urlParams.get("lang") || "nl";
    return lang.toLowerCase();
  }

  function setCurrentLanguage(placeholder) {
    if (!placeholder) return;

    const langMap = {
      nl: { flag: "🇳🇱", code: "NL" },
      en: { flag: "🇬🇧", code: "EN" },
      de: { flag: "🇩🇪", code: "DE" }
    };

    const currentLang = getCurrentLanguage();
    const langData = langMap[currentLang] || langMap.nl;

    const flagElement = document.querySelector("#currentFlag");
    const langElement = document.querySelector("#currentLang");

    console.log("Setting language:", currentLang, "Flag:", langData.flag, "Code:", langData.code);
    console.log("Flag element:", flagElement, "Lang element:", langElement);

    if (flagElement) {
      flagElement.textContent = langData.flag;
      flagElement.innerHTML = langData.flag;
    }
    if (langElement) {
      langElement.textContent = langData.code;
    }
  }

  function loadHeader(options = {}) {
    const placeholder = document.querySelector('[data-include="header"]');
    if (!placeholder) {
      return Promise.resolve(null);
    }

    const activeKey = options.active || placeholder.dataset.active || "";

    return fetch("/partials/header.html", { cache: "no-store" })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`Header request failed with status ${response.status}`);
        }
        return response.text();
      })
      .then((html) => {
        placeholder.innerHTML = html;
        setActiveNav(placeholder, activeKey);
        setCurrentLanguage(placeholder);
        document.dispatchEvent(new CustomEvent("header:loaded", { detail: { active: activeKey } }));
        return placeholder;
      })
      .catch((error) => {
        console.error("Unable to load header:", error);
        return null;
      });
  }

  window.loadHeader = loadHeader;
})(window, document);
