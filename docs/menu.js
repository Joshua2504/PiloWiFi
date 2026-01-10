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
