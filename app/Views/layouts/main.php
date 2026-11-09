<?php
$pageTitle = $pageTitle ?? 'Jazuva Restaurant & Coffee Shop';
?>
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= Security::escape($pageTitle) ?></title><link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/responsive.css"></head>
<body><main class="mvc-shell"><?php if (isset($content)) { echo $content; } ?></main><script src="<?= BASE_URL ?>/assets/js/app.js" defer></script></body>
</html>
