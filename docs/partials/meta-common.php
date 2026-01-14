<?php
// Detect language from URL path for server-side rendering
$path = $_SERVER['REQUEST_URI'];
preg_match('#^/([a-z]{2})(?:/|$)#', $path, $matches);
$currentLang = isset($matches[1]) && in_array($matches[1], ['nl', 'en', 'de']) ? $matches[1] : 'nl';

// Load translations for server-side rendering
$translationsFile = __DIR__ . '/../locales/translations.json';
$translations = [];
if (file_exists($translationsFile)) {
  $translationsData = json_decode(file_get_contents($translationsFile), true);
  $translations = $translationsData[$currentLang] ?? [];
}

// Helper function to get translation
function t($key, $fallback = '') {
  global $translations;
  if (isset($translations[$key])) {
    return $translations[$key];
  }
  // Handle nested keys like meta.title
  $keys = explode('.', $key);
  $value = $translations;
  foreach ($keys as $k) {
    if (isset($value[$k])) {
      $value = $value[$k];
    } else {
      return $fallback ?: $key;
    }
  }
  return is_string($value) ? $value : ($fallback ?: $key);
}
?>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />

<!-- Set language in localStorage on page load -->
<script>
  localStorage.setItem('preferredLanguage', '<?= $currentLang ?>');
  document.cookie = 'preferredLanguage=<?= $currentLang ?>;path=/;max-age=31536000;SameSite=Lax';
</script>

<!-- SEO Meta Tags -->
<meta name="description" content="<?= htmlspecialchars(t('meta.description', 'JarnoWiFi levert professionele wifi voor markten, kampen en festivals met Starlink en 5G-back-up.')) ?>" />
<?php
  $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
  
  // Extract current page without language prefix
  $page = preg_replace('#^/[a-z]{2}(/|$)#', '/', $path);
  $page = $page === '/' ? '/' : rtrim($page, '/');
?>
<link rel="alternate" hreflang="nl" href="<?= $baseUrl ?>/nl<?= $page ?>" />
<link rel="alternate" hreflang="en" href="<?= $baseUrl ?>/en<?= $page ?>" />
<link rel="alternate" hreflang="de" href="<?= $baseUrl ?>/de<?= $page ?>" />
<link rel="alternate" hreflang="x-default" href="<?= $baseUrl ?>/nl<?= $page ?>" />

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Work+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />

<!-- Site CSS -->
<link href="/site.css" rel="stylesheet" />

<!-- Menu Script -->
<script src="/menu.js"></script>

<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-D6BR389F7B"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-D6BR389F7B');
</script>
