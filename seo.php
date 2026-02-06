<?php
declare(strict_types=1);

function seo_base_url(): string {
  if (defined('APP_BASE_URL') && APP_BASE_URL) {
    return rtrim((string)APP_BASE_URL, '/');
  }
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $scheme . '://' . $host;
}

function seo_current_url(): string {
  $base = seo_base_url();
  $uri = $_SERVER['REQUEST_URI'] ?? '/';
  if ($uri === '') $uri = '/';
  return $base . $uri;
}

function seo_escape($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function seo_og_locale(string $lang): string {
  $l = strtolower(trim($lang));
  if ($l === 'tr') return 'tr_TR';
  if ($l === 'en') return 'en_US';
  if (strpos($l, '-') !== false) return str_replace('-', '_', $l);
  return 'en_US';
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

function seo_alternate_links(array $langs, ?string $url = null): string {
  if (!$langs) return '';
  $url = $url ?: seo_current_url();
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

function seo_jsonld(array $opts): string {
  $base = seo_base_url();
  $siteName = (string)($opts['site_name'] ?? 'Prismatch');
  $title = (string)($opts['title'] ?? $siteName);
  $desc = (string)($opts['description'] ?? '');
  $url = (string)($opts['url'] ?? seo_current_url());
  $lang = (string)($opts['lang'] ?? 'en');
  $logo = seo_image_url($opts['logo'] ?? '/logo.png');

  $org = [
    '@type' => 'Organization',
    '@id' => $base . '/#organization',
    'name' => $siteName,
    'url' => $base . '/',
    'logo' => $logo,
  ];

  $site = [
    '@type' => 'WebSite',
    '@id' => $base . '/#website',
    'url' => $base . '/',
    'name' => $siteName,
    'inLanguage' => $lang,
    'publisher' => ['@id' => $base . '/#organization'],
  ];

  $page = [
    '@type' => 'WebPage',
    '@id' => $url . '#webpage',
    'url' => $url,
    'name' => $title,
    'inLanguage' => $lang,
    'isPartOf' => ['@id' => $base . '/#website'],
  ];
  if ($desc !== '') $page['description'] = $desc;

  $graph = [
    '@context' => 'https://schema.org',
    '@graph' => [$org, $site, $page],
  ];

  return '<script type="application/ld+json">' .
    json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) .
    '</script>';
}

function seo_meta(array $opts = []): string {
  $title = (string)($opts['title'] ?? '');
  $desc = (string)($opts['description'] ?? '');
  $url = (string)($opts['url'] ?? seo_current_url());
  $image = seo_image_url($opts['image'] ?? '/logo.png');
  $type = (string)($opts['type'] ?? 'website');
  $robots = (string)($opts['robots'] ?? 'index,follow');
  $lang = (string)($opts['lang'] ?? 'en');
  $siteName = (string)($opts['site_name'] ?? $title);
  $theme = (string)($opts['theme_color'] ?? '#081017');

  $out = [];
  if ($desc !== '') {
    $out[] = '<meta name="description" content="' . seo_escape($desc) . '" />';
  }
  $out[] = '<meta name="robots" content="' . seo_escape($robots) . '" />';
  $out[] = '<link rel="canonical" href="' . seo_escape($url) . '" />';
  $out[] = '<meta name="theme-color" content="' . seo_escape($theme) . '" />';

  $out[] = '<meta property="og:type" content="' . seo_escape($type) . '" />';
  if ($siteName !== '') $out[] = '<meta property="og:site_name" content="' . seo_escape($siteName) . '" />';
  if ($title !== '') $out[] = '<meta property="og:title" content="' . seo_escape($title) . '" />';
  if ($desc !== '') $out[] = '<meta property="og:description" content="' . seo_escape($desc) . '" />';
  $out[] = '<meta property="og:url" content="' . seo_escape($url) . '" />';
  $out[] = '<meta property="og:locale" content="' . seo_escape(seo_og_locale($lang)) . '" />';
  $out[] = '<meta property="og:image" content="' . seo_escape($image) . '" />';
  $out[] = '<meta property="og:image:alt" content="' . seo_escape($siteName ?: $title) . '" />';

  $out[] = '<meta name="twitter:card" content="summary" />';
  if ($title !== '') $out[] = '<meta name="twitter:title" content="' . seo_escape($title) . '" />';
  if ($desc !== '') $out[] = '<meta name="twitter:description" content="' . seo_escape($desc) . '" />';
  $out[] = '<meta name="twitter:image" content="' . seo_escape($image) . '" />';

  $out[] = seo_jsonld([
    'title' => $title,
    'description' => $desc,
    'url' => $url,
    'lang' => $lang,
    'site_name' => $siteName,
    'logo' => $image,
  ]);

  return implode("\n  ", $out);
}



