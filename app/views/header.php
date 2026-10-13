<?php
$brand=setting('brand_name','торговая компания «Монополия»');
$metaTitle=$metaTitle??($page['seo_title']??$brand);
$metaDescription=$metaDescription??($page['seo_description']??'Кабельная продукция, металлопрокат, профнастил, трансформаторы и КТП. Подбор и расчет по спецификации.');
$canonical=$canonical??absolute_url($path??'/');
$logo=setting('logo','assets/img/logo-mark.png');
$nav=json_value(setting('header_menu'),[]);
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($metaTitle) ?></title>
<meta name="description" content="<?= e(truncate(strip_tags($metaDescription),320)) ?>">
<meta name="theme-color" content="#10243e">
<meta name="robots" content="<?= !empty($noindex)?'noindex,follow':'index,follow' ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="ru_RU">
<meta property="og:title" content="<?= e($metaTitle) ?>">
<meta property="og:description" content="<?= e(truncate(strip_tags($metaDescription),200)) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:site_name" content="<?= e($brand) ?>">
<meta property="og:image" content="<?= e(absolute_url($ogImage??$page['image']??'assets/img/cable.webp')) ?>">
<link rel="icon" type="image/x-icon" href="<?= e(asset(setting('favicon','favicon.ico'))) ?>">
<link rel="icon" type="image/svg+xml" href="<?= e(asset('assets/img/favicon.svg')) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('apple-touch-icon.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/fonts/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/styles.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
<script defer src="<?= e(asset('assets/js/site.js')) ?>"></script>
<script type="application/ld+json" nonce="<?= e(csrf()) ?>"><?= json_encode(['@context'=>'https://schema.org','@type'=>'Organization','name'=>$brand,'url'=>absolute_url('/'),'logo'=>absolute_url($logo),'telephone'=>setting('phone'),'email'=>setting('email'),'foundingDate'=>setting('founded','2009'),'address'=>['@type'=>'PostalAddress','streetAddress'=>'Киришская ул., д. 2А, помещение 8Н','addressLocality'=>'Санкт-Петербург','postalCode'=>'195299','addressCountry'=>'RU']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG) ?></script>
<?php if(isset($schema)): ?><script type="application/ld+json" nonce="<?= e(csrf()) ?>"><?= json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG) ?></script><?php endif ?>
</head>
<body class="font-pair-3 palette-1" data-base="<?= e(config('base_path')) ?>" data-csrf="<?= e(csrf()) ?>">
<a class="skip-link" href="#main">Перейти к содержанию</a>
<header class="site-header container">
<a class="brand" href="<?= url('/') ?>" aria-label="<?= e($brand) ?> — главная">
<img class="brand-symbol" src="<?= e(asset($logo)) ?>" alt="" width="48" height="48">
<span class="brand-copy"><strong>МОНОПОЛИЯ</strong><small>торговая компания</small></span>
</a>
<nav class="main-nav" id="mainNav" aria-label="Основное меню">
<?php foreach($nav as $link): ?><a class="nav-link <?= ($path??'')===$link['path']?'is-active':'' ?>" href="<?= e(url($link['path'])) ?>"><?= e($link['label']) ?></a><?php endforeach ?>
</nav>
<div class="header-actions">
<a class="icon-btn search-link" href="<?= url('/search') ?>" aria-label="Поиск по каталогу"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10.5" cy="10.5" r="7"/><path d="m16 16 5 5"/></svg></a>
<a class="request-pill" href="<?= url('/request') ?>"><span>Моя заявка</span><b data-cart-count><?= cart_count() ?></b></a>
<button class="menu-toggle" type="button" aria-controls="mainNav" aria-expanded="false">Меню</button>
</div>
</header>
<main id="main">
<?php if($notice=get_flash()): ?><div class="container notice <?= e($notice['type']) ?>" role="status"><?= e($notice['text']) ?></div><?php endif ?>
