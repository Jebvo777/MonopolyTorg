</main>
<footer class="footer">
<div class="container footer-grid">
<div><a class="brand footer-brand" href="<?= url('/') ?>"><img class="brand-symbol" src="<?= e(asset(setting('logo','assets/img/logo-mark.png'))) ?>" alt="" width="48" height="48"><span class="brand-copy"><strong>МОНОПОЛИЯ</strong><small>торговая компания</small></span></a><p><?= e(setting('footer_text')) ?></p><span class="footer-since">Работаем с <?= e(setting('founded','2009')) ?> года</span></div>
<div><h3>Продукция</h3><?php foreach(roots() as $c): ?><a href="<?= url('/catalog/'.$c['slug']) ?>"><?= e($c['name']) ?></a><?php endforeach ?></div>
<div><h3>Информация</h3><a href="<?= url('/about') ?>">О компании</a><a href="<?= url('/delivery') ?>">Условия поставки</a><a href="<?= url('/documents') ?>">Документы</a><a href="<?= url('/information') ?>">Техническая информация</a><a href="<?= url('/calculators') ?>">Калькуляторы</a><a href="<?= url('/faq') ?>">Вопросы и ответы</a></div>
<div><h3>Связаться с нами</h3><a href="tel:<?= e(setting('phone_link')) ?>"><?= e(setting('phone')) ?></a><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a><p><?= e(setting('address')) ?></p><p><?= e(setting('hours')) ?></p><a class="text-link" href="<?= url('/contacts') ?>">Контакты →</a></div>
</div>
<div class="container footer-bottom"><span>© <?= date('Y') ?> <?= e(setting('brand_name')) ?></span><div><a href="<?= url('/privacy') ?>">Политика конфиденциальности</a><a href="<?= url('/sitemap') ?>">Карта сайта</a><button class="footer-privacy" type="button" data-analytics-toggle>Отключить статистику</button></div></div>
</footer>
<div class="toast" role="status" aria-live="polite" id="toast"></div>
</body></html>
