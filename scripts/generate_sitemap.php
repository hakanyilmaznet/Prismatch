<?php
declare(strict_types=1);

require_once __DIR__ . '/../i18n.php';

$langs = array_keys(supported_languages());
$pages = [
  ['loc' => 'https://www.prismatch.online/', 'priority' => '1.0', 'changefreq' => 'daily'],
  ['loc' => 'https://www.prismatch.online/play.php', 'priority' => '0.9', 'changefreq' => 'weekly'],
  ['loc' => 'https://www.prismatch.online/play.php?daily=1', 'priority' => '0.9', 'changefreq' => 'daily'],
  ['loc' => 'https://www.prismatch.online/rooms.php', 'priority' => '0.8', 'changefreq' => 'daily'],
  ['loc' => 'https://www.prismatch.online/daily_leaderboard.php', 'priority' => '0.8', 'changefreq' => 'daily'],
  ['loc' => 'https://www.prismatch.online/privacy.php', 'priority' => '0.3', 'changefreq' => 'monthly'],
  ['loc' => 'https://www.prismatch.online/terms.php', 'priority' => '0.3', 'changefreq' => 'monthly'],
];

$date = date('Y-m-d');
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
$xml .= '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

foreach ($pages as $p) {
  $xml .= "  <url>\n";
  $xml .= "    <loc>" . htmlspecialchars($p['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
  $xml .= "    <lastmod>{$date}</lastmod>\n";
  $xml .= "    <changefreq>{$p['changefreq']}</changefreq>\n";
  $xml .= "    <priority>{$p['priority']}</priority>\n";
  $base = $p['loc'];
  $sep = strpos($base, '?') !== false ? '&' : '?';
  $xml .= "    <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"" . htmlspecialchars($base, ENT_XML1, 'UTF-8') . "\" />\n";
  foreach ($langs as $l) {
    $alt = $base . $sep . 'lang=' . $l;
    $xml .= "    <xhtml:link rel=\"alternate\" hreflang=\"{$l}\" href=\"" . htmlspecialchars($alt, ENT_XML1, 'UTF-8') . "\" />\n";
  }
  $xml .= "  </url>\n";
}
$xml .= "</urlset>\n";

$target = dirname(__DIR__) . '/sitemap.xml';
file_put_contents($target, $xml);
echo "Sitemap generated successfully: " . strlen($xml) . " bytes written to sitemap.xml\n";
