<?php
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
$config = require __DIR__ . '/config.php';
define('PUBLIC_ROOT', rtrim((string)$config['public_root'], '/\\'));
date_default_timezone_set($config['timezone']);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/php-error.log');
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (PHP_SAPI !== 'cli') {
    session_name('monopoly_session');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['lifetime'=>0, 'path'=>$config['base_path'].'/', 'secure'=>$isHttps, 'httponly'=>true, 'samesite'=>'Lax']);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self' 'nonce-" . csrf() . "'; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
    if ($isHttps) header('Strict-Transport-Security: max-age=31536000');
}
function config(string $key, mixed $default=null): mixed { global $config; return $config[$key] ?? $default; }
function db(): PDO {
    static $pdo;
    if (!$pdo) { $pdo = new PDO('mysql:host='.config('db_host').';port='.config('db_port').';dbname='.config('db_name').';charset=utf8mb4',config('db_user'),config('db_password'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec("SET time_zone = ".$pdo->quote(date('P'))); }
    return $pdo;
}
function query(string $sql, array $args=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($args); return $s; }
function rows(string $sql, array $args=[]): array { return query($sql,$args)->fetchAll(); }
function row(string $sql, array $args=[]): ?array { return query($sql,$args)->fetch() ?: null; }
function e(mixed $text): string { return htmlspecialchars((string)($text??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function url(string $path='/'): string { return config('base_path').'/'.ltrim($path,'/'); }
function asset(string $path): string { return url($path); }
function absolute_url(string $path='/'): string { return config('base_url').url($path); }
function setting(string $key, string $default=''): string { static $all; if ($all===null) $all=array_column(rows('SELECT name,value FROM settings'),'value','name'); return (string)($all[$key]??$default); }
function json_value(?string $value, mixed $default=[]): mixed { $v=json_decode($value?:'',true); return json_last_error()===JSON_ERROR_NONE ? $v : $default; }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf()).'">'; }
function validate_csrf(): void { if (!hash_equals(csrf(),(string)($_POST['csrf']??$_SERVER['HTTP_X_CSRF_TOKEN']??''))) throw new InvalidArgumentException('Обновите страницу и повторите отправку.'); }
function flash(string $type, string $text): void { $_SESSION['flash']=['type'=>$type,'text'=>$text]; }
function get_flash(): ?array { $v=$_SESSION['flash']??null; unset($_SESSION['flash']);return $v; }
function redirect(string $path, int $status=303): never { header('Location: '.url($path),true,$status);exit; }
function json_response(array $data, int $status=200): never { http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit; }
function truncate(string $text,int $length=180): string { return mb_strlen($text)<=$length ? $text : mb_substr($text,0,$length-1).'…'; }
function rich(string $html): string {
    if ($html==='')return '';
    $doc=new DOMDocument();$prior=libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?><div id="safe-content">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();libxml_use_internal_errors($prior);
    // Keep the wrapper reference before removing attributes: libxml can reuse
    // the removed ID entry for a later element.
    $wrapper=$doc->getElementsByTagName('div')->item(0);
    $allowed=['div','p','h2','h3','h4','ul','ol','li','table','thead','tbody','tr','th','td','strong','b','em','i','br','a','img','sub','sup','caption','blockquote'];
    foreach (iterator_to_array($doc->getElementsByTagName('*')) as $node) {
        if (!in_array($node->nodeName,$allowed,true)) { $node->parentNode?->removeChild($node);continue; }
        $attrs=[];foreach ($node->attributes as $a)$attrs[$a->name]=$a->value;
        foreach ($attrs as $k=>$v)$node->removeAttribute($k);
        if (in_array($node->nodeName,['td','th'],true))foreach(['colspan','rowspan'] as $k) if(isset($attrs[$k])&&ctype_digit($attrs[$k])&&(int)$attrs[$k]<=30)$node->setAttribute($k,$attrs[$k]);
        if ($node->nodeName==='img') {
            $src=$attrs['src']??'';
            if (!preg_match('~^(?:'.preg_quote(url('assets/'),'~').'|/?assets/)[a-zA-Z0-9_./-]+\.(?:jpg|jpeg|png|gif|webp|svg)$~i',$src) || str_contains($src,'..')) { $node->parentNode?->removeChild($node);continue; }
            if(str_starts_with(ltrim($src,'/'),'assets/'))$src=asset(ltrim($src,'/'));
            $node->setAttribute('src',$src);$node->setAttribute('alt',$attrs['alt']??'Продукция торговой компании «Монополия»');$node->setAttribute('loading','lazy');
        }
        if ($node->nodeName==='a') {
            $href=$attrs['href']??'';
            if (preg_match('~^(https?://|mailto:|tel:|/|assets/)~i',$href)&&!str_starts_with($href,'//')){
                if(str_starts_with($href,'assets/'))$href=asset($href);
                elseif(str_starts_with($href,'/')&&!(config('base_path')&&($href===config('base_path')||str_starts_with($href,config('base_path').'/'))))$href=url($href);
                $node->setAttribute('href',$href);
            }
            $node->setAttribute('rel','noopener');
        }
    }
    $out='';
    if ($wrapper) foreach($wrapper->childNodes as $n)$out.=$doc->saveHTML($n);
    return $out;
}
function cart(): array { return $_SESSION['cart']??[]; }
function cart_count(): int { return count(cart()); }
function roots(): array { return rows('SELECT * FROM categories WHERE parent_id IS NULL AND active=1 ORDER BY sort_order,id'); }
function category_ids(int $id): array { $ids=[$id];foreach(rows('SELECT id,parent_id FROM categories WHERE active=1') as $c)if(in_array((int)$c['parent_id'],$ids,true))$ids[]=(int)$c['id'];return $ids; }
function placeholders(array $items): string { return implode(',',array_fill(0,count($items),'?')); }
function page_by_slug(string $slug): ?array { return row('SELECT * FROM pages WHERE slug=? AND active=1',[$slug]); }
function image_url(?string $path): string { return asset($path?:'assets/img/product-placeholder.svg'); }
function product_card(array $p): void { include __DIR__.'/views/product-card.php'; }
function breadcrumbs(array $items): void { echo '<nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="'.url('/').'">Главная</a>';foreach($items as $label=>$path){echo '<span> / </span>';echo $path?'<a href="'.e(url($path)).'">'.e($label).'</a>':'<span aria-current="page">'.e($label).'</span>';}echo '</nav>'; }
function track_visit(string $path,?int $productId=null): void {
    if (PHP_SAPI==='cli'||setting('analytics_enabled','1')!=='1'||($_COOKIE['monopoly_analytics']??'on')==='off')return;
    if (preg_match('/bot|crawler|spider|headless/i',$_SERVER['HTTP_USER_AGENT']??''))return;
    $device=preg_match('/mobile|android|iphone/i',$_SERVER['HTTP_USER_AGENT']??'')?'mobile':'desktop';
    $ref=substr(parse_url($_SERVER['HTTP_REFERER']??'',PHP_URL_HOST)?:'',0,190);
    $hash=hash('sha256',session_id().csrf().date('Y-m-d'));
    try { query('INSERT INTO visits (visitor_hash,path,product_id,referrer,device,utm_source,created_at) VALUES (?,?,?,?,?,?,NOW())',[$hash,substr($path,0,255),$productId,$ref,$device,substr((string)($_GET['utm_source']??''),0,100)]); }catch(Throwable $ex){error_log('Analytics: '.$ex->getMessage());}
}
set_exception_handler(function(Throwable $ex):void {
    error_log((string)$ex);
    if(PHP_SAPI==='cli'){fwrite(STDERR,"Ошибка: ".$ex->getMessage()."\n");exit(1);}
    if(str_starts_with($_SERVER['REQUEST_URI']??'',config('base_path').'/api/'))json_response(['ok'=>false,'error'=>'Не удалось обработать запрос. Повторите попытку или напишите на monopoly@list.ru.'],500);
    http_response_code(503);header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Связаться с торговой компанией «Монополия»</title><link rel="stylesheet" href="'.e(asset('assets/css/system.css')).'"><body><main class="service-error"><h1>Свяжитесь с нами напрямую</h1><p>Сервис заявок временно недоступен. Отправьте спецификацию по электронной почте, и мы подготовим предложение.</p><a href="mailto:monopoly@list.ru">monopoly@list.ru</a><p><a href="tel:+78129328648">+7 (812) 932-86-48</a></p></main></body></html>';
});
