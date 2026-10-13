<article class="product-card">
<a class="product-media" href="<?= url('/product/'.$p['slug']) ?>"><img src="<?= e(image_url($p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy" width="400" height="260"></a>
<div class="product-card-body">
<div class="product-meta"><span><?= e($p['category_name']??'Каталог') ?></span><span>Цена по запросу</span></div>
<h3><a href="<?= url('/product/'.$p['slug']) ?>"><?= e($p['name']) ?></a></h3>
<p><?= e(truncate($p['short_description'],125)) ?></p>
<div class="product-spec-line">Артикул <?= e($p['sku']) ?> · Объем в <?= e($p['unit']) ?></div>
<div class="product-card-actions"><a class="text-link" href="<?= url('/product/'.$p['slug']) ?>">Подробнее <span>→</span></a><form class="add-form" action="<?= url('/api/cart') ?>" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="quantity" value="1"><button class="circle-add" type="submit" aria-label="Добавить <?= e($p['name']) ?> в заявку">+</button></form></div>
</div>
</article>
