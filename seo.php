<?php
declare(strict_types=1);

/**
 * Modern Google SEO & Rich Snippets Manager for Prismatch
 * Compliant with Google Search Central Guidelines, Schema.org Graph,
 * Core Web Vitals, Mobile PWA Standards, and Multi-language Hreflang.
 */

function seo_base_url(): string {
  if (defined('APP_BASE_URL') && APP_BASE_URL) {
    return rtrim((string)APP_BASE_URL, '/');
  }
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $scheme . '://' . $host;
}

/**
 * Returns a normalized, canonical URL for search engines.
 * Strips tracking query parameters (utm_*, gclid, fbclid, ref, etc.)
 * Normalizes /index.php to root /
 */
function seo_canonical_url(?string $customPath = null, ?array $allowedParams = null): string {
  $base = seo_base_url();
  if ($customPath !== null) {
    if (preg_match('~^https?://~i', $customPath)) {
      return $customPath;
    }
    $path = '/' . ltrim($customPath, '/');
    if ($path === '/index.php') $path = '/';
    return $base . $path;
  }

  $uri = $_SERVER['REQUEST_URI'] ?? '/';
  $parsed = parse_url($uri);
  $path = $parsed['path'] ?? '/';
  if ($path === '/index.php') {
    $path = '/';
  }

  $allowed = $allowedParams ?? ['lang', 'daily', 'mode'];
  $filteredQuery = [];
  if (!empty($parsed['query'])) {
    parse_str($parsed['query'], $rawParams);
    foreach ($allowed as $k) {
      if (isset($rawParams[$k]) && $rawParams[$k] !== '') {
        $filteredQuery[$k] = $rawParams[$k];
      }
    }
  }

  $qs = !empty($filteredQuery) ? '?' . http_build_query($filteredQuery) : '';
  return $base . $path . $qs;
}

function seo_current_url(): string {
  return seo_canonical_url();
}

function seo_escape($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Maps all 19 supported language codes to standard OpenGraph locales.
 */
function seo_og_locale(string $lang): string {
  $l = strtolower(trim($lang));
  if (function_exists('locale_for_lang')) {
    $loc = locale_for_lang($lang);
    if ($loc) return $loc;
  }
  $map = [
    'tr' => 'tr_TR',
    'en' => 'en_US',
    'es' => 'es_ES',
    'pt' => 'pt_PT',
    'fr' => 'fr_FR',
    'de' => 'de_DE',
    'it' => 'it_IT',
    'ru' => 'ru_RU',
    'ar' => 'ar_SA',
    'fa' => 'fa_IR',
    'hi' => 'hi_IN',
    'bn' => 'bn_BD',
    'id' => 'id_ID',
    'vi' => 'vi_VN',
    'ja' => 'ja_JP',
    'ko' => 'ko_KR',
    'zh-cn' => 'zh_CN',
    'yue' => 'zh_HK',
    'wuu' => 'zh_CN',
  ];
  return $map[$l] ?? 'en_US';
}

function seo_image_url(?string $path = null): string {
  $path = $path ?: '/logo.png';
  if (preg_match('~^https?://~i', $path)) return $path;
  $path = '/' . ltrim($path, '/');
  return seo_base_url() . $path;
}

function seo_url_with_query(string $url, array $query): string {
  $parts = parse_url($url);
  $scheme = $parts['scheme'] ?? '';
  $host = $parts['host'] ?? '';
  $port = isset($parts['port']) ? ':' . $parts['port'] : '';
  $path = $parts['path'] ?? '';
  $frag = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
  $qs = http_build_query($query);
  $base = ($scheme && $host) ? ($scheme . '://' . $host . $port) : '';
  return $base . $path . ($qs ? '?' . $qs : '') . $frag;
}

function seo_remove_query_param(string $url, string $param): string {
  $parts = parse_url($url);
  $query = [];
  if (!empty($parts['query'])) parse_str($parts['query'], $query);
  unset($query[$param]);
  return seo_url_with_query($url, $query);
}

function seo_with_lang(string $url, string $lang): string {
  $parts = parse_url($url);
  $query = [];
  if (!empty($parts['query'])) parse_str($parts['query'], $query);
  $query['lang'] = $lang;
  return seo_url_with_query($url, $query);
}

/**
 * Generates Google-compliant rel="alternate" hreflang links.
 * Includes x-default and self-referencing hreflang tags for all supported languages.
 */
function seo_alternate_links(array $langs, ?string $url = null): string {
  if (!$langs) return '';
  $url = $url ? seo_canonical_url($url) : seo_canonical_url();
  $clean = seo_remove_query_param($url, 'lang');
  $out = [];
  $out[] = '<link rel="alternate" hreflang="x-default" href="' . seo_escape($clean) . '" />';
  foreach ($langs as $code) {
    $code = trim((string)$code);
    if ($code === '') continue;
    $out[] = '<link rel="alternate" hreflang="' . seo_escape($code) . '" href="' . seo_escape(seo_with_lang($clean, $code)) . '" />';
  }
  return implode("\n  ", $out);
}

/**
 * Builds Schema.org JSON-LD graph tailored for Google Rich Results.
 * Supports WebSite (with SearchAction), Organization, WebApplication/VideoGame,
 * WebPage, BreadcrumbList, and optional FAQPage / HowTo.
 */
function seo_jsonld(array $opts): string {
  $base = seo_base_url();
  $siteName = (string)($opts['site_name'] ?? 'Prismatch');
  $title = (string)($opts['title'] ?? $siteName);
  $desc = (string)($opts['description'] ?? '');
  $url = (string)($opts['url'] ?? seo_canonical_url());
  $lang = (string)($opts['lang'] ?? 'en');
  $logo = seo_image_url($opts['logo'] ?? '/logo.png');

  $graph = [];

  // 1. Organization Schema
  $graph[] = [
    '@type' => 'Organization',
    '@id' => $base . '/#organization',
    'name' => $siteName,
    'url' => $base . '/',
    'logo' => [
      '@type' => 'ImageObject',
      'url' => $logo,
      'width' => 512,
      'height' => 512,
    ],
  ];

  // 2. WebSite Schema with potentialAction
  $graph[] = [
    '@type' => 'WebSite',
    '@id' => $base . '/#website',
    'url' => $base . '/',
    'name' => $siteName,
    'inLanguage' => $lang,
    'publisher' => ['@id' => $base . '/#organization'],
    'potentialAction' => [
      '@type' => 'SearchAction',
      'target' => $base . '/play.php?q={search_term_string}',
      'query-input' => 'required name=search_term_string',
    ],
  ];

  // 3. WebApplication & VideoGame Schema (Google Rich Game Result)
  $graph[] = [
    '@type' => ['WebApplication', 'VideoGame'],
    '@id' => $base . '/#game',
    'name' => 'Prismatch',
    'url' => $base . '/',
    'description' => $desc !== '' ? $desc : 'Fast-paced brain training and color memory puzzle game.',
    'applicationCategory' => 'GameApplication',
    'genre' => ['Memory Game', 'Color Puzzle', 'Brain Training', 'Casual Game'],
    'gamePlatform' => ['Web Browser', 'Mobile Browser', 'Desktop'],
    'operatingSystem' => 'All',
    'browserRequirements' => 'Requires JavaScript, HTML5 Canvas/CSS3',
    'offers' => [
      '@type' => 'Offer',
      'price' => '0',
      'priceCurrency' => 'USD',
      'availability' => 'https://schema.org/InStock',
    ],
    'playMode' => ['SinglePlayer', 'MultiPlayer'],
    'inLanguage' => $lang,
    'author' => ['@id' => $base . '/#organization'],
    'image' => $logo,
  ];

  // 4. WebPage Schema
  $webpage = [
    '@type' => 'WebPage',
    '@id' => $url . '#webpage',
    'url' => $url,
    'name' => $title,
    'inLanguage' => $lang,
    'isPartOf' => ['@id' => $base . '/#website'],
    'about' => ['@id' => $base . '/#game'],
  ];
  if ($desc !== '') $webpage['description'] = $desc;
  $graph[] = $webpage;

  // 5. BreadcrumbList Schema (if provided)
  if (!empty($opts['breadcrumbs']) && is_array($opts['breadcrumbs'])) {
    $listElements = [];
    $pos = 1;
    foreach ($opts['breadcrumbs'] as $bc) {
      if (empty($bc['name'])) continue;
      $itemUrl = isset($bc['url']) ? (preg_match('~^https?://~i', $bc['url']) ? $bc['url'] : $base . '/' . ltrim($bc['url'], '/')) : $url;
      $listElements[] = [
        '@type' => 'ListItem',
        'position' => $pos++,
        'name' => (string)$bc['name'],
        'item' => $itemUrl,
      ];
    }
    if ($listElements) {
      $graph[] = [
        '@type' => 'BreadcrumbList',
        '@id' => $url . '#breadcrumb',
        'itemListElement' => $listElements,
      ];
    }
  }

  // 6. FAQPage Schema (if provided)
  if (!empty($opts['faq']) && is_array($opts['faq'])) {
    $mainEntity = [];
    foreach ($opts['faq'] as $qa) {
      if (empty($qa['q']) || empty($qa['a'])) continue;
      $mainEntity[] = [
        '@type' => 'Question',
        'name' => (string)$qa['q'],
        'acceptedAnswer' => [
          '@type' => 'Answer',
          'text' => (string)$qa['a'],
        ],
      ];
    }
    if ($mainEntity) {
      $graph[] = [
        '@type' => 'FAQPage',
        '@id' => $url . '#faq',
        'mainEntity' => $mainEntity,
      ];
    }
  }

  // 7. HowTo Schema (if provided)
  if (!empty($opts['howTo']) && is_array($opts['howTo'])) {
    $steps = [];
    $stepNum = 1;
    foreach (($opts['howTo']['steps'] ?? []) as $st) {
      if (empty($st['name'])) continue;
      $steps[] = [
        '@type' => 'HowToStep',
        'position' => $stepNum++,
        'name' => (string)$st['name'],
        'text' => (string)($st['text'] ?? $st['name']),
      ];
    }
    if ($steps) {
      $graph[] = [
        '@type' => 'HowTo',
        '@id' => $url . '#howto',
        'name' => (string)($opts['howTo']['name'] ?? 'How to Play Prismatch'),
        'description' => (string)($opts['howTo']['description'] ?? $desc),
        'step' => $steps,
      ];
    }
  }

  $root = [
    '@context' => 'https://schema.org',
    '@graph' => $graph,
  ];

  return '<script type="application/ld+json">' .
    json_encode($root, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) .
    '</script>';
}

/**
 * Emits comprehensive SEO metadata conforming to Google's Search Central guidelines:
 * - Directives: max-image-preview:large, max-snippet:-1, max-video-preview:-1
 * - PWA Web App Manifest: /site.webmanifest
 * - Mobile web app capable, theme-color, apple-touch-icon
 * - OpenGraph & Twitter Large Image Card with explicit dimensions
 * - Google Rich Results JSON-LD Graph
 */
function seo_meta(array $opts = []): string {
  $title = (string)($opts['title'] ?? '');
  $desc = (string)($opts['description'] ?? '');
  $url = seo_canonical_url($opts['url'] ?? null);
  $image = seo_image_url($opts['image'] ?? '/logo.png');
  $type = (string)($opts['type'] ?? 'website');
  $lang = (string)($opts['lang'] ?? 'en');
  $siteName = (string)($opts['site_name'] ?? 'Prismatch');
  $theme = (string)($opts['theme_color'] ?? '#081017');

  // Google Robots Directive
  $rawRobots = (string)($opts['robots'] ?? 'index,follow');
  if (str_contains(strtolower($rawRobots), 'noindex')) {
    $robots = 'noindex, follow';
  } else {
    // Google recommended modern snippet directives
    $robots = 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
  }

  $ogLocale = seo_og_locale($lang);

  $out = [];

  // Basic Meta
  if ($desc !== '') {
    $out[] = '<meta name="description" content="' . seo_escape($desc) . '" />';
  }
  $out[] = '<meta name="robots" content="' . seo_escape($robots) . '" />';
  $out[] = '<meta name="googlebot" content="' . seo_escape($robots) . '" />';
  $out[] = '<link rel="canonical" href="' . seo_escape($url) . '" />';

  // Mobile, PWA & Core Web Vitals Resource Hints
  $out[] = '<link rel="manifest" href="/site.webmanifest" />';
  $out[] = '<meta name="theme-color" content="' . seo_escape($theme) . '" />';
  $out[] = '<meta name="mobile-web-app-capable" content="yes" />';
  $out[] = '<meta name="apple-mobile-web-app-capable" content="yes" />';
  $out[] = '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />';
  $out[] = '<meta name="apple-mobile-web-app-title" content="' . seo_escape($siteName) . '" />';
  $out[] = '<meta name="application-name" content="' . seo_escape($siteName) . '" />';
  $out[] = '<link rel="apple-touch-icon" href="/logo.png" />';

  // Open Graph
  $out[] = '<meta property="og:site_name" content="' . seo_escape($siteName) . '" />';
  $out[] = '<meta property="og:type" content="' . seo_escape($type) . '" />';
  if ($title !== '') $out[] = '<meta property="og:title" content="' . seo_escape($title) . '" />';
  if ($desc !== '') $out[] = '<meta property="og:description" content="' . seo_escape($desc) . '" />';
  $out[] = '<meta property="og:url" content="' . seo_escape($url) . '" />';
  $out[] = '<meta property="og:locale" content="' . seo_escape($ogLocale) . '" />';
  $out[] = '<meta property="og:image" content="' . seo_escape($image) . '" />';
  $out[] = '<meta property="og:image:secure_url" content="' . seo_escape($image) . '" />';
  $out[] = '<meta property="og:image:type" content="image/png" />';
  $out[] = '<meta property="og:image:width" content="1200" />';
  $out[] = '<meta property="og:image:height" content="630" />';
  $out[] = '<meta property="og:image:alt" content="' . seo_escape($title ?: $siteName) . '" />';

  // Twitter Card (Large Image)
  $out[] = '<meta name="twitter:card" content="summary_large_image" />';
  if ($title !== '') $out[] = '<meta name="twitter:title" content="' . seo_escape($title) . '" />';
  if ($desc !== '') $out[] = '<meta name="twitter:description" content="' . seo_escape($desc) . '" />';
  $out[] = '<meta name="twitter:image" content="' . seo_escape($image) . '" />';
  $out[] = '<meta name="twitter:image:alt" content="' . seo_escape($title ?: $siteName) . '" />';

  // JSON-LD Schema.org Structured Data Graph
  $jsonLdOpts = array_merge($opts, [
    'title' => $title,
    'description' => $desc,
    'url' => $url,
    'lang' => $lang,
    'site_name' => $siteName,
    'logo' => $image,
  ]);
  $out[] = seo_jsonld($jsonLdOpts);

  return implode("\n  ", $out);
}
