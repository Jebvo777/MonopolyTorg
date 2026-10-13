<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$rawPath=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
$path=rawurldecode($rawPath);
$base=config('base_path');if($base&&str_starts_with($path,$base.'/'))$path=substr($path,strlen($base));
$path='/'.trim($path,'/');
if($path==='/index.php')redirect('/',301);
if(str_starts_with($path,'/admin')){require ROOT.'/app/admin.php';exit;}
if(str_starts_with($path,'/api/')){require ROOT.'/app/requests.php';handle_api($path);exit;}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'&&($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD'){http_response_code(405);header('Allow: GET, HEAD');exit;}
if($path==='/robots.txt'){
 header('Content-Type: text/plain; charset=utf-8');echo "User-agent: *\nAllow: /\nDisallow: ".url('/admin')."\nDisallow: ".url('/api')."\nDisallow: ".url('/request')."\nDisallow: ".url('/success')."\nDisallow: ".url('/search')."\nSitemap: ".absolute_url('/sitemap.xml')."\n";exit;
}
if($path==='/sitemap.xml'){
 header('Content-Type: application/xml; charset=utf-8');
 echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
 $paths=['/'];foreach(rows("SELECT slug FROM pages WHERE active=1 AND slug NOT IN ('home','request','search','success','404')") as $p)$paths[]='/'.$p['slug'];
 $paths[]='/catalog';foreach(rows('SELECT slug FROM categories WHERE active=1') as $c)$paths[]='/catalog/'.$c['slug'];
 foreach(rows('SELECT p.slug FROM products p JOIN categories c ON c.id=p.category_id WHERE p.active=1 AND c.active=1') as $p)$paths[]='/product/'.$p['slug'];
 foreach($paths as $p)echo '<url><loc>'.e(absolute_url($p)).'</loc></url>';
 echo '</urlset>';exit;
}
$redirects=json_value(file_get_contents(ROOT.'/data/redirects.json'));
if(isset($redirects[$path])&&$redirects[$path]!==$path)redirect($redirects[$path],301);
$legacy=['/index.html'=>'/','/catalog.html'=>'/catalog','/about.html'=>'/about','/delivery.html'=>'/delivery','/documents.html'=>'/documents','/faq.html'=>'/faq','/contacts.html'=>'/contacts','/search.html'=>'/search','/request.html'=>'/request','/success.html'=>'/success','/empty-request.html'=>'/request','/form-error.html'=>'/request','/category-cable.html'=>'/catalog/cable','/category-metal.html'=>'/catalog/metal','/category-profile.html'=>'/catalog/profile','/category-transformers.html'=>'/catalog/transformers'];
if(isset($legacy[$path]))redirect($legacy[$path],301);
$template='content';$noindex=false;$category=null;$productId=null;
if($path==='/catalog'||str_starts_with($path,'/catalog/')){
 $category=$path==='/catalog'?null:row('SELECT * FROM categories WHERE slug=? AND active=1',[substr($path,9)]);
 if($path!=='/catalog'&&!$category){http_response_code(404);$page=page_by_slug('404');$template='error';$noindex=true;}
 else {
  $page=['seo_title'=>$category['seo_title']??'Каталог кабеля, металлопроката, профнастила, трансформаторов | Монополия','seo_description'=>$category['seo_description']??'Полный каталог торговой компании «Монополия». Подбор по марке, размеру и техническим параметрам. Расчет и поставка по заявке.'];
  $q=trim(mb_substr((string)($_GET['q']??''),0,120));$sort=in_array($_GET['sort']??'default',['default','name','new'],true)?$_GET['sort']??'default':'default';
  $where=['p.active=1','c.active=1'];$args=[];
  if($category){$ids=category_ids((int)$category['id']);$where[]='p.category_id IN ('.placeholders($ids).')';$args=$ids;}
  if($q!==''){$where[]='(p.name LIKE ? OR p.sku LIKE ? OR p.short_description LIKE ? OR p.specs_json LIKE ?)';$term='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$q).'%';array_push($args,$term,$term,$term,$term);$noindex=true;}
  $condition=implode(' AND ',$where);$total=(int)query('SELECT COUNT(*) FROM products p JOIN categories c ON c.id=p.category_id WHERE '.$condition,$args)->fetchColumn();
  $perPage=24;$lastPage=max(1,(int)ceil($total/$perPage));$currentPage=max(1,(int)($_GET['page']??1));
  if($currentPage>$lastPage){http_response_code(404);$page=page_by_slug('404');$template='error';$noindex=true;}
  else {
   $order=['default'=>'p.sort_order,p.id','name'=>'p.name,p.id','new'=>'p.id DESC'][$sort];$offset=($currentPage-1)*$perPage;
   $products=rows('SELECT p.*,c.name category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE '.$condition.' ORDER BY '.$order.' LIMIT '.$perPage.' OFFSET '.$offset,$args);$template='catalog';
   if($currentPage>1){$page['seo_title'].=' — страница '.$currentPage;$canonical=absolute_url($path).'?page='.$currentPage;}
  }
 }
}elseif(str_starts_with($path,'/product/')){
 $product=row('SELECT p.*,c.name category_name,c.slug category_slug FROM products p JOIN categories c ON c.id=p.category_id WHERE p.slug=? AND p.active=1 AND c.active=1',[substr($path,9)]);
 if(!$product){http_response_code(404);$page=page_by_slug('404');$template='error';$noindex=true;}
 else {
  $page=['seo_title'=>$product['seo_title'],'seo_description'=>$product['seo_description'],'image'=>$product['image']];$template='product';$productId=(int)$product['id'];$specs=json_value($product['specs_json']);$gallery=json_value($product['gallery_json']);
  $related=rows('SELECT p.*,c.name category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.category_id=? AND p.id<>? AND p.active=1 ORDER BY p.featured DESC,p.sort_order LIMIT 5',[$product['category_id'],$product['id']]);
  $schema=['@context'=>'https://schema.org','@type'=>'Product','name'=>$product['name'],'sku'=>$product['sku'],'description'=>$product['short_description'],'image'=>absolute_url($product['image']),'category'=>$product['category_name'],'brand'=>['@type'=>'Brand','name'=>setting('brand_name')]];
 }
}else{
 $slug=$path==='/'?'home':ltrim($path,'/');$page=page_by_slug($slug);
 if(!$page){http_response_code(404);$page=page_by_slug('404');$template='error';$noindex=true;}
 else{
  $template=$page['template'];
  switch($template){
   case 'home':$popular=rows('SELECT p.*,c.name category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.active=1 AND c.active=1 ORDER BY p.featured DESC,p.sort_order LIMIT 10');break;
   case 'documents':$documents=rows('SELECT * FROM documents WHERE active=1 ORDER BY sort_order,id');break;
   case 'faq':$faqs=rows('SELECT * FROM faqs WHERE active=1 ORDER BY sort_order,id');$schema=['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>array_map(fn($f)=>['@type'=>'Question','name'=>$f['question'],'acceptedAnswer'=>['@type'=>'Answer','text'=>$f['answer']]],$faqs)];break;
   case 'request':$cartItems=cart();$noindex=true;break;
   case 'success':$noindex=true;break;
   case 'search':
    $q=trim(mb_substr((string)($_GET['q']??''),0,120));$noindex=true;$currentPage=max(1,(int)($_GET['page']??1));$term='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$q).'%';
    $args=[$term,$term,$term];$total=$q!==''?(int)query('SELECT COUNT(*) FROM products p JOIN categories c ON c.id=p.category_id WHERE p.active=1 AND c.active=1 AND (p.name LIKE ? OR p.sku LIKE ? OR p.short_description LIKE ?)',$args)->fetchColumn():0;
    $lastPage=max(1,(int)ceil($total/24));$currentPage=min($currentPage,$lastPage);$offset=($currentPage-1)*24;
    $products=$q!==''?rows('SELECT p.*,c.name category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.active=1 AND c.active=1 AND (p.name LIKE ? OR p.sku LIKE ? OR p.short_description LIKE ?) ORDER BY p.name LIMIT 24 OFFSET '.$offset,$args):[];break;
   case 'information':$articles=rows("SELECT * FROM pages WHERE slug LIKE 'information/%' AND active=1 ORDER BY sort_order,id");break;
   case 'sitemap':$allCategories=rows('SELECT * FROM categories WHERE active=1 ORDER BY parent_id,sort_order,id');break;
  }
 }
}
if(!$page){throw new RuntimeException('Base page data not installed');}
$templates=['home','content','catalog','product','documents','faq','contacts','search','request','success','error','information','sitemap','calculators','article'];
if(!in_array($template,$templates,true))$template='content';
track_visit($path,$productId);
require ROOT.'/app/views/header.php';
require ROOT.'/app/views/'.($template==='article'?'content':$template).'.php';
require ROOT.'/app/views/footer.php';
