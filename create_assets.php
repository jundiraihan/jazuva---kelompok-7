<?php
/**
 * Create beautiful SVG artwork for all menus and coffee shop hero
 */

$assetsDir = __DIR__ . '/assets/images';
$uploadsDir = __DIR__ . '/uploads/menus';

if (!is_dir($assetsDir)) mkdir($assetsDir, 0777, true);
if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

function makeFoodSvg($title, $cat, $bgColor, $emoji) {
    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300" width="100%" height="100%">
  <defs>
    <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$bgColor}" stop-opacity="1" />
      <stop offset="100%" stop-color="#2C1810" stop-opacity="0.9" />
    </linearGradient>
    <filter id="shadow" x="-10%" y="-10%" width="120%" height="120%">
      <feDropShadow dx="0" dy="8" stdDeviation="12" flood-color="#000" flood-opacity="0.3"/>
    </filter>
  </defs>
  <rect width="400" height="300" fill="url(#grad)" />
  <circle cx="200" cy="130" r="85" fill="#FFF8F0" opacity="0.95" filter="url(#shadow)"/>
  <circle cx="200" cy="130" r="75" fill="#F4EDE4" stroke="#D7C4B7" stroke-width="3" />
  <text x="200" y="150" font-size="64" text-anchor="middle" dominant-baseline="middle">{$emoji}</text>
  <rect x="30" y="235" width="340" height="45" rx="10" fill="#2C1810" opacity="0.85" />
  <text x="200" y="255" font-family="'Plus Jakarta Sans', sans-serif" font-weight="700" font-size="16" fill="#FBF7F2" text-anchor="middle">{$title}</text>
  <text x="200" y="272" font-family="'Plus Jakarta Sans', sans-serif" font-weight="500" font-size="11" fill="#C69255" text-anchor="middle">JAZUVA SIGNATURE</text>
</svg>
SVG;
}

$items = [
    'nasi-goreng' => ['title' => 'Nasi Goreng Spesial', 'cat' => 'Makanan', 'bg' => '#C0392B', 'emoji' => '🍛'],
    'ayam-geprek' => ['title' => 'Ayam Geprek Sambal', 'cat' => 'Makanan', 'bg' => '#D35400', 'emoji' => '🍗'],
    'mie-goreng'  => ['title' => 'Mie Goreng Jawa', 'cat' => 'Makanan', 'bg' => '#E67E22', 'emoji' => '🍜'],
    'es-teh'      => ['title' => 'Es Teh Manis', 'cat' => 'Minuman', 'bg' => '#B7950B', 'emoji' => '🍹'],
    'cappuccino'  => ['title' => 'Cappuccino', 'cat' => 'Kopi', 'bg' => '#6E2C00', 'emoji' => '☕'],
    'matcha-latte'=> ['title' => 'Matcha Latte', 'cat' => 'Minuman', 'bg' => '#1E8449', 'emoji' => '🍵'],
    'es-kopi-susu'=> ['title' => 'Es Kopi Susu', 'cat' => 'Kopi', 'bg' => '#784212', 'emoji' => '🥤'],
    'kentang-goreng' => ['title' => 'Kentang Goreng Krispi', 'cat' => 'Snack', 'bg' => '#D4AC0D', 'emoji' => '🍟'],
];

foreach ($items as $key => $val) {
    $svg = makeFoodSvg($val['title'], $val['cat'], $val['bg'], $val['emoji']);
    file_put_contents("$uploadsDir/{$key}.jpg", $svg); // modern browsers render SVG even with .jpg if served or we can also save .svg
    file_put_contents("$uploadsDir/{$key}.svg", $svg);
}

// Hero banner artwork
$heroSvg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600" width="100%" height="100%">
  <defs>
    <radialGradient id="ambience" cx="50%" cy="40%" r="60%">
      <stop offset="0%" stop-color="#4A2818" />
      <stop offset="60%" stop-color="#24120A" />
      <stop offset="100%" stop-color="#150A05" />
    </radialGradient>
    <filter id="glow">
      <feGaussianBlur stdDeviation="8" result="coloredBlur"/>
      <feMerge>
        <feMergeNode in="coloredBlur"/>
        <feMergeNode in="SourceGraphic"/>
      </feMerge>
    </filter>
  </defs>
  <rect width="1200" height="600" fill="url(#ambience)" />
  <!-- Pendant warm lamps -->
  <line x1="200" y1="0" x2="200" y2="120" stroke="#8B5A2B" stroke-width="2"/>
  <ellipse cx="200" cy="120" rx="30" ry="15" fill="#C69255" />
  <circle cx="200" cy="130" r="16" fill="#F39C12" filter="url(#glow)"/>
  
  <line x1="450" y1="0" x2="450" y2="90" stroke="#8B5A2B" stroke-width="2"/>
  <ellipse cx="450" cy="90" rx="26" ry="12" fill="#C69255" />
  <circle cx="450" cy="98" r="14" fill="#F39C12" filter="url(#glow)"/>

  <line x1="850" y1="0" x2="850" y2="130" stroke="#8B5A2B" stroke-width="2"/>
  <ellipse cx="850" cy="130" rx="32" ry="16" fill="#C69255" />
  <circle cx="850" cy="142" r="18" fill="#F39C12" filter="url(#glow)"/>

  <!-- Coffee beans & warm aesthetic background elements -->
  <circle cx="1000" cy="400" r="180" fill="#2E180E" opacity="0.4" />
  <circle cx="950" cy="380" r="120" fill="#3D2013" opacity="0.6" />
</svg>
SVG;

file_put_contents("$assetsDir/hero-bg.svg", $heroSvg);
file_put_contents("$assetsDir/hero-bg.jpg", $heroSvg);

echo "Assets created successfully!\n";

