<?php
declare(strict_types=1);
require_once __DIR__.'/requests.php';
header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow');
$entities=require __DIR__.'/admin-schema.php';
$section=(string)($_GET['section']??'dashboard');
$allowedSections=array_merge(['dashboard','requests','settings','statistics','password','audit'],array_keys($entities));
if(!in_array($section,$allowedSections,true))$section='dashboard';
$adminError='';
function audit(string $action,string $entity,string $id=''): void { query('INSERT INTO audit_log (user_id,action,entity,entity_id) VALUES (?,?,?,?)',[$_SESSION['admin_id']??null,$action,$entity,$id]); }
function admin_url(string $section='dashboard',array $args=[]): string { return url('/admin').'?'.http_build_query(array_merge(['section'=>$section],$args)); }
function admin_redirect(string $section='dashboard',array $args=[]): never { header('Location: '.admin_url($section,$args),true,303);exit; }
function slugify(string $text): string {
    $map=['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
    $s=strtr(mb_strtolower($text),$map);$s=preg_replace('/[^a-z0-9]+/','-',$s);return substr(trim($s,'-'),0,150)?:'item-'.bin2hex(random_bytes(4));
}
function admin_asset(string $path,bool $required=false): string {
    $path=trim($path);if($path===''){if($required)throw new InvalidArgumentException('Выберите файл.');return '';}
    if(!preg_match('~^(?:assets/|favicon\.ico$|apple-touch-icon\.png$)[A-Za-z0-9_./-]*$~',$path)||str_contains($path,'..')||!is_file(PUBLIC_ROOT.'/'.$path))throw new InvalidArgumentException('Выберите существующий файл из медиатеки.');
    return $path;
}
function register_media(array $file): void { query('INSERT INTO media (file_path,original_name,alt,mime,bytes) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE original_name=VALUES(original_name),mime=VALUES(mime),bytes=VALUES(bytes)',[$file['path'],$file['name'],'',$file['mime'],$file['bytes']]); }
function admin_date_range(): array {
    $from=(string)($_GET['from']??date('Y-m-d',strtotime('-29 days')));$to=(string)($_GET['to']??date('Y-m-d'));
    foreach([$from,$to] as $date){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$d||$d->format('Y-m-d')!==$date)throw new InvalidArgumentException('Укажите корректный период.');}
    if($from>$to)throw new InvalidArgumentException('Начало периода должно быть раньше окончания.');
    return [$from,$to,$from.' 00:00:00',$to.' 23:59:59'];
}
function csv_export(string $type): never {
    [$from,$to,$start,$end]=admin_date_range();$params=[$start,$end];
    $exports=[
     'requests'=>['Заявки',"SELECT r.public_id,r.created_at,r.status,r.email_status,r.name,r.company,r.phone,r.email,r.city,r.message,r.source,r.utm_source FROM requests r WHERE r.created_at BETWEEN ? AND ? ORDER BY r.id",['Номер','Дата','Статус','Письмо','Имя','Компания','Телефон','Email','Город','Сообщение','Страница','Источник']],
     'items'=>['Позиции заявок',"SELECT r.public_id,r.created_at,i.sku,i.name,i.quantity,i.unit,i.parameters FROM request_items i JOIN requests r ON r.id=i.request_id WHERE r.created_at BETWEEN ? AND ? ORDER BY r.id,i.id",['Заявка','Дата','Артикул','Товар','Количество','Единица','Параметры']],
     'daily'=>['Посещения по дням',"SELECT DATE(created_at) day,COUNT(*) views,COUNT(DISTINCT visitor_hash) visitors FROM visits WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY day",['Дата','Просмотры','Посетители']],
     'pages'=>['Популярные страницы',"SELECT path,COUNT(*) views,COUNT(DISTINCT visitor_hash) visitors FROM visits WHERE created_at BETWEEN ? AND ? GROUP BY path ORDER BY views DESC",['Страница','Просмотры','Посетители']],
     'sources'=>['Источники переходов',"SELECT referrer,utm_source,device,COUNT(*) views FROM visits WHERE created_at BETWEEN ? AND ? GROUP BY referrer,utm_source,device ORDER BY views DESC",['Источник','UTM source','Устройство','Просмотры']],
     'popular'=>['Популярность товаров',"SELECT p.sku,p.name,COALESCE(v.views,0) views,COALESCE(i.requests_count,0) requests_count FROM products p LEFT JOIN (SELECT product_id,COUNT(*) views FROM visits WHERE created_at BETWEEN ? AND ? AND product_id IS NOT NULL GROUP BY product_id) v ON v.product_id=p.id LEFT JOIN (SELECT x.product_id,COUNT(DISTINCT x.request_id) requests_count FROM request_items x JOIN requests r ON r.id=x.request_id WHERE r.created_at BETWEEN ? AND ? GROUP BY x.product_id) i ON i.product_id=p.id ORDER BY requests_count DESC,views DESC,p.name",['Артикул','Товар','Просмотры','Заявки']],
     'products'=>['Каталог',"SELECT p.sku,p.name,c.name,p.unit,p.active,p.featured,p.slug,p.image FROM products p JOIN categories c ON c.id=p.category_id ORDER BY p.id",['Артикул','Товар','Раздел','Единица','Опубликован','На главной','Адрес','Изображение']],
    ];
    if(!isset($exports[$type]))throw new InvalidArgumentException('Неизвестный отчет.');
    [$title,$sql,$headers]=$exports[$type];if($type==='products')$params=[];if($type==='popular')$params=[$start,$end,$start,$end];
    header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="monopoly-'.$type.'-'.$from.'-'.$to.'.csv"');
    echo "\xEF\xBB\xBF";$fp=fopen('php://output','w');fputcsv($fp,$headers,';','"','');
    foreach(rows($sql,$params) as $r){$values=array_values($r);foreach($values as &$v){$v=(string)($v??'');if(preg_match('/^[\s]*[=+@-]/u',$v))$v="'".$v;}unset($v);fputcsv($fp,$values,';','"','');}
    fclose($fp);audit('export',$type,$from.'..'.$to);exit;
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&($_POST['action']??'')==='login'){
 try{
  validate_csrf();$ip=ip_hash();$attempts=(int)query("SELECT COUNT(*) FROM login_attempts WHERE ip_hash=? AND success=0 AND created_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)",[$ip])->fetchColumn();
  if($attempts>=5)throw new InvalidArgumentException('Слишком много попыток. Повторите вход через 15 минут.');
  $login=trim((string)($_POST['username']??''));$password=(string)($_POST['password']??'');
  $user=row('SELECT * FROM admin_users WHERE username=? AND active=1',[$login]);$ok=$user&&password_verify($password,$user['password_hash']);
  query('INSERT INTO login_attempts (ip_hash,success) VALUES (?,?)',[$ip,$ok?1:0]);
  if(!$ok)throw new InvalidArgumentException('Неверный логин или пароль.');
  session_regenerate_id(true);$_SESSION['admin_id']=(int)$user['id'];$_SESSION['admin_seen']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));query('UPDATE admin_users SET last_login=NOW() WHERE id=?',[$user['id']]);audit('login','admin_users',(string)$user['id']);admin_redirect();
 }catch(InvalidArgumentException $ex){$adminError=$ex->getMessage();}
}
$admin=null;
if(isset($_SESSION['admin_id'])){
 if(time()-(int)($_SESSION['admin_seen']??0)>7200){unset($_SESSION['admin_id'],$_SESSION['admin_seen']);$adminError='Сессия завершена. Войдите повторно.';}
 else{$admin=row('SELECT id,username,name,active FROM admin_users WHERE id=? AND active=1',[$_SESSION['admin_id']]);if($admin)$_SESSION['admin_seen']=time();else unset($_SESSION['admin_id']);}
}
if(!$admin){require __DIR__.'/views/admin-login.php';exit;}
if(isset($_GET['export'])){try{csv_export((string)$_GET['export']);}catch(InvalidArgumentException $ex){$adminError=$ex->getMessage();}}
if(isset($_GET['download'])){
 $r=row('SELECT attachment_path,attachment_name,attachment_mime FROM requests WHERE id=?',[(int)$_GET['download']]);
 if(!$r||!$r['attachment_path']||!str_starts_with($r['attachment_path'],'storage/specifications/')||!is_file(ROOT.'/'.$r['attachment_path'])){http_response_code(404);exit;}
 header('Content-Type: '.$r['attachment_mime']);header('Content-Disposition: attachment; filename="specification.'.pathinfo($r['attachment_name'],PATHINFO_EXTENSION).'"; filename*=UTF-8\'\''.rawurlencode($r['attachment_name']));header('Content-Length: '.filesize(ROOT.'/'.$r['attachment_path']));readfile(ROOT.'/'.$r['attachment_path']);audit('download','requests',(string)$_GET['download']);exit;
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 try{
  validate_csrf();$action=(string)($_POST['action']??'');
  if($action==='logout'){audit('logout','admin_users',(string)$admin['id']);unset($_SESSION['admin_id'],$_SESSION['admin_seen']);session_regenerate_id(true);redirect('/admin');}
  if($action==='save_entity'&&isset($entities[$section])){
   $def=$entities[$section];$id=(int)($_POST['id']??0);$old=$id?row('SELECT * FROM '.$def['table'].' WHERE id=?',[$id]):null;
   if($id&&!$old)throw new InvalidArgumentException('Запись не найдена.');
   $data=[];
   foreach($def['fields'] as $key=>$field){[$label,$type]=$field;$required=$field[2]??false;$max=$field[3]??null;
    if($type==='readonly'){if($old)$data[$key]=$old[$key];continue;}
    if($type==='password'){if(($_POST[$key]??'')!==''){if(mb_strlen((string)$_POST[$key])<8)throw new InvalidArgumentException('Пароль должен содержать не менее 8 символов.');$data['password_hash']=password_hash((string)$_POST[$key],PASSWORD_DEFAULT);}elseif(!$old)throw new InvalidArgumentException('Укажите пароль нового администратора.');continue;}
    if($type==='checkbox'){$data[$key]=isset($_POST[$key])?1:0;continue;}
    if(in_array($type,['number','category','parent'],true)){$v=(int)($_POST[$key]??0);if($type==='parent'){$data[$key]=$v?:null;if($id&&$v===$id)throw new InvalidArgumentException('Раздел не может быть родителем самого себя.');if($v&&row('SELECT parent_id FROM categories WHERE id=?',[$v])['parent_id'])throw new InvalidArgumentException('Подраздел можно разместить только в основном направлении.');}else $data[$key]=$v;continue;}
    $value=trim((string)($_POST[$key]??''));
    if($type==='image'||$type==='document'){
     if($upload=receive_upload($key.'_upload',true)){if(($type==='document'&&$upload['mime']!=='application/pdf')||($type==='image'&&!str_starts_with($upload['mime'],'image/')))throw new InvalidArgumentException('Выберите файл нужного типа.');register_media($upload);$value=$upload['path'];}
     $value=admin_asset($value,$required);if($type==='document'&&!str_ends_with(strtolower($value),'.pdf'))throw new InvalidArgumentException('Выберите PDF-документ.');
    }elseif($type==='rich'){$value=rich($value);}
    elseif($type==='specs'){$specs=[];foreach(preg_split('/\R/u',$value) as $line){if(trim($line)==='')continue;$parts=explode('|',$line,2);if(count($parts)!==2)throw new InvalidArgumentException('Характеристики: каждая строка должна иметь вид «Параметр | Значение».');$specs[]=[trim($parts[0]),trim($parts[1])];}$value=json_encode($specs,JSON_UNESCAPED_UNICODE);}
    elseif($type==='gallery'){$gallery=[];foreach(preg_split('/\R/u',$value) as $line)if(trim($line)!=='')$gallery[]=admin_asset($line,true);$value=json_encode($gallery,JSON_UNESCAPED_UNICODE);}
    elseif(in_array($type,['slug','page_slug'],true)){
     if($value==='')$value=slugify((string)($_POST['name']??$_POST['title']??'page'));
     $pattern=$type==='page_slug'?'~^[a-z0-9][a-z0-9/-]*$~':'~^[a-z0-9][a-z0-9-]*$~';if(!preg_match($pattern,$value)||str_contains($value,'//')||str_ends_with($value,'/'))throw new InvalidArgumentException('Адрес должен содержать латинские буквы, цифры и дефисы.');
     if($section==='pages'&&(!$old||$value!==$old['slug'])&&in_array(explode('/',$value)[0],['admin','api','catalog','product','index.php','sitemap.xml','robots.txt'],true))throw new InvalidArgumentException('Этот адрес используется системой.');
     if($section==='pages'&&$old&&in_array($old['template'],['home','search','request','success','error','contacts','faq','documents','information','calculators','sitemap'],true)&&$value!==$old['slug'])throw new InvalidArgumentException('Адрес служебной страницы сохраняется; содержимое можно изменить.');
    }elseif($type==='doc_kind'&&!in_array($value,['archive','technical','certificate','price'],true))throw new InvalidArgumentException('Выберите тип документа.');
    if($required&&$value==='')throw new InvalidArgumentException('Заполните поле «'.$label.'».');if($max&&mb_strlen($value)>$max)throw new InvalidArgumentException('Поле «'.$label.'» слишком длинное.');if(strlen($value)>1500000)throw new InvalidArgumentException('Текст слишком большой.');
    $data[$key]=$value;
   }
   if($section==='users'){
    if(!preg_match('/^[a-zA-Z0-9_.-]{3,80}$/',$data['username']))throw new InvalidArgumentException('Логин: от 3 символов, латинские буквы, цифры, точка, дефис или подчеркивание.');
    if($id===$admin['id']&&!$data['active'])throw new InvalidArgumentException('Нельзя отключить свою учетную запись.');
   }
   if($section==='pages'&&$old&&in_array($old['slug'],['home','404','request','success','search'],true)&&!$data['active'])throw new InvalidArgumentException('Эта служебная страница должна оставаться опубликованной.');
   if(isset($data['seo_title'])&&$data['seo_title']==='')$data['seo_title']=($data['name']??$data['title']).' | Торговая компания «Монополия»';
   if(isset($data['seo_description'])&&$data['seo_description']==='')$data['seo_description']=truncate($data['short_description']??$data['lead']??'',300);
   if($section==='products'){
    if(!row('SELECT id FROM categories WHERE id=?',[$data['category_id']]))throw new InvalidArgumentException('Выберите существующий раздел.');
    if($data['sku']==='')$data['sku']='MNP-'.strtoupper(bin2hex(random_bytes(4)));
    if(!$old){$data['root_category']='';$data['old_urls_json']='[]';$data['source_file']='';}
   }
   if($section==='pages'&&!$old)$data['template']='content';
   if($id){$set=implode(',',array_map(fn($k)=>'`'.$k.'`=?',array_keys($data)));query('UPDATE '.$def['table'].' SET '.$set.' WHERE id=?',[...array_values($data),$id]);}
   else{$cols=implode(',',array_map(fn($k)=>'`'.$k.'`',array_keys($data)));query('INSERT INTO '.$def['table'].' ('.$cols.') VALUES ('.placeholders($data).')',array_values($data));$id=(int)db()->lastInsertId();}
   audit('save',$section,(string)$id);flash('success','Изменения сохранены.');admin_redirect($section,['id'=>$id]);
  }
  if($action==='delete_entity'&&isset($entities[$section])){
   $id=(int)($_POST['id']??0);$def=$entities[$section];$old=row('SELECT * FROM '.$def['table'].' WHERE id=?',[$id]);if(!$old)throw new InvalidArgumentException('Запись не найдена.');
   if($section==='pages'&&$old['template']!=='content'&&$old['template']!=='article')throw new InvalidArgumentException('Служебную страницу нельзя удалить. Ее содержимое редактируется в карточке.');
   if($section==='users'&&$id===$admin['id'])throw new InvalidArgumentException('Нельзя удалить свою учетную запись.');
   if($section==='media')throw new InvalidArgumentException('Файлы могут использоваться в карточках. Измените ссылки в карточках перед удалением файла вручную.');
   query('DELETE FROM '.$def['table'].' WHERE id=?',[$id]);audit('delete',$section,(string)$id);flash('success','Запись удалена.');admin_redirect($section);
  }
  if($action==='upload_media'){
   $file=receive_upload('media_upload',true);if(!$file)throw new InvalidArgumentException('Выберите файл для загрузки.');register_media($file);audit('upload','media',$file['path']);flash('success','Файл загружен. Его можно выбрать в карточке товара или страницы.');admin_redirect('media');
  }
  if($action==='save_request'){
   $id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'');if(!in_array($status,['new','processing','quoted','completed','cancelled'],true))throw new InvalidArgumentException('Неизвестный статус.');
   query('UPDATE requests SET status=?,manager_note=? WHERE id=?',[$status,clean_input('manager_note',8000),$id]);audit('status','requests',(string)$id);flash('success','Заявка обновлена.');admin_redirect('requests',['id'=>$id]);
  }
  if($action==='retry_mail'){
   $id=(int)($_POST['id']??0);query("UPDATE email_outbox SET status='pending',attempts=0,available_at=NOW(),locked_at=NULL WHERE request_id=?",[$id]);query("UPDATE requests SET email_status='queued' WHERE id=?",[$id]);$sent=send_outbox($id);audit('retry_mail','requests',(string)$id);flash($sent?'success':'error',$sent?'Уведомление отправлено.':'Письмо не отправлено. Проверьте настройки почты и сообщение об ошибке.');admin_redirect('requests',['id'=>$id]);
  }
  if($action==='delete_request'){
   $id=(int)($_POST['id']??0);$r=row('SELECT attachment_path FROM requests WHERE id=?',[$id]);query('DELETE FROM requests WHERE id=?',[$id]);if($r&&$r['attachment_path']&&str_starts_with($r['attachment_path'],'storage/specifications/'))@unlink(ROOT.'/'.$r['attachment_path']);audit('delete','requests',(string)$id);flash('success','Заявка и ее файл удалены.');admin_redirect('requests');
  }
  if($action==='save_settings'){
   db()->beginTransaction();
   $allowed=['brand_name','phone','phone_link','email','address','hours','founded','logo','favicon','home_kicker','home_directions_title','home_directions_lead','home_popular_title','home_popular_lead','home_spec_title','home_spec_text','home_steps_title','home_about_title','home_about_text','cta_title','cta_text','footer_text','analytics_enabled','mail_to','mail_transport','mail_from','smtp_host','smtp_port','smtp_encryption','smtp_user','smtp_password','theme_accent','theme_warm','theme_background'];
   foreach($allowed as $k){if(!array_key_exists($k,$_POST))continue;$v=trim((string)$_POST[$k]);if(mb_strlen($v)>8000)throw new InvalidArgumentException('Одно из полей слишком длинное.');if($k==='smtp_password'&&$v==='')continue;
    if(in_array($k,['mail_to','mail_from','email'],true))safe_email($v);
    if(in_array($k,['logo','favicon'],true)){if($file=receive_upload($k.'_upload',true)){register_media($file);$v=$file['path'];}$v=admin_asset($v,true);}
    if($k==='brand_name'&&preg_match('/\bтк\b/iu',$v))throw new InvalidArgumentException('Используйте полное название: торговая компания «Монополия».');
    if(str_starts_with($k,'theme_')&&!preg_match('/^#[0-9a-fA-F]{6}$/',$v))throw new InvalidArgumentException('Выберите цвет.');
    if($k==='smtp_port'&&((int)$v<1||(int)$v>65535))throw new InvalidArgumentException('Проверьте порт SMTP.');
    if($k==='mail_transport'&&!in_array($v,['mail','smtp'],true))throw new InvalidArgumentException('Выберите способ отправки почты.');
    if($k==='smtp_encryption'&&!in_array($v,['tls','ssl','none'],true))throw new InvalidArgumentException('Выберите шифрование SMTP.');
    query('INSERT INTO settings (name,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$k,$v]);
   }
   if(isset($_POST['header_menu'])){$menu=[];foreach(preg_split('/\R/u',(string)$_POST['header_menu']) as $line){if(trim($line)==='')continue;[$label,$link]=array_pad(explode('|',$line,2),2,'');$label=trim($label);$link=trim($link);if($label===''||!preg_match('~^/[a-zA-Z0-9/_-]*$~',$link))throw new InvalidArgumentException('Меню: укажите название и внутренний адрес через вертикальную черту.');$menu[]=['label'=>$label,'path'=>$link];}query("UPDATE settings SET value=? WHERE name='header_menu'",[json_encode($menu,JSON_UNESCAPED_UNICODE)]);}
   if(isset($_POST['home_facts'])){$facts=array_values(array_filter(array_map('trim',preg_split('/\R/u',(string)$_POST['home_facts']))));query("UPDATE settings SET value=? WHERE name='home_facts'",[json_encode($facts,JSON_UNESCAPED_UNICODE)]);}
   if(isset($_POST['home_steps'])){$steps=[];foreach(preg_split('/\R/u',(string)$_POST['home_steps']) as $line){if(trim($line)==='')continue;[$title,$text]=array_pad(explode('|',$line,2),2,'');$steps[]=['title'=>trim($title),'text'=>trim($text)];}query("UPDATE settings SET value=? WHERE name='home_steps'",[json_encode($steps,JSON_UNESCAPED_UNICODE)]);}
   db()->commit();audit('save','settings');flash('success','Настройки сохранены.');admin_redirect('settings');
  }
  if($action==='change_password'){
   $u=row('SELECT * FROM admin_users WHERE id=?',[$admin['id']]);if(!password_verify((string)($_POST['current_password']??''),$u['password_hash']))throw new InvalidArgumentException('Текущий пароль неверен.');
   $new=(string)($_POST['new_password']??'');if(strlen($new)<8||strlen($new)>200)throw new InvalidArgumentException('Новый пароль должен содержать от 8 до 200 символов.');if($new!==($_POST['confirm_password']??''))throw new InvalidArgumentException('Новые пароли не совпадают.');
   query('UPDATE admin_users SET password_hash=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$admin['id']]);session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));audit('password_change','admin_users',(string)$admin['id']);flash('success','Пароль изменен.');admin_redirect('password');
  }
 }catch(InvalidArgumentException $ex){if(db()->inTransaction())db()->rollBack();$adminError=$ex->getMessage();}
 catch(PDOException $ex){if(db()->inTransaction())db()->rollBack();error_log($ex->getMessage());$adminError=$ex->getCode()==='23000'?'Такой адрес или артикул уже существует, либо запись используется в других разделах.':'Не удалось сохранить изменения. Проверьте поля и повторите.';}
}
$edit=null;$list=[];$q=trim(mb_substr((string)($_GET['q']??''),0,120));$id=(int)($_GET['id']??0);$isNew=isset($_GET['new']);$pageNumber=max(1,(int)($_GET['page']??1));$totalRows=0;
if(isset($entities[$section])){
 $def=$entities[$section];if($id)$edit=row('SELECT * FROM '.$def['table'].' WHERE id=?',[$id]);elseif($isNew)$edit=['id'=>0,'active'=>1,'sort_order'=>0,'unit'=>'шт.'];
 if(!$edit){$where='1=1';$args=[];if($q!==''){$where='('.implode(' OR ',array_map(fn($c)=>'`'.$c.'` LIKE ?',$def['search'])).')';$args=array_fill(0,count($def['search']),'%'.$q.'%');}$totalRows=(int)query('SELECT COUNT(*) FROM '.$def['table'].' WHERE '.$where,$args)->fetchColumn();$list=rows('SELECT * FROM '.$def['table'].' WHERE '.$where.' ORDER BY id DESC LIMIT 40 OFFSET '.(($pageNumber-1)*40),$args);}
}
if($section==='requests'){
 if($id){$request=row('SELECT * FROM requests WHERE id=?',[$id]);if($request){$requestItems=rows('SELECT * FROM request_items WHERE request_id=? ORDER BY id',[$id]);$outbox=row('SELECT * FROM email_outbox WHERE request_id=?',[$id]);}}
 else{$where='1=1';$args=[];$filter=(string)($_GET['status']??'');if(in_array($filter,['new','processing','quoted','completed','cancelled'],true)){$where.=' AND status=?';$args[]=$filter;}if($q!==''){$where.=' AND (public_id LIKE ? OR company LIKE ? OR name LIKE ? OR email LIKE ?)';array_push($args,'%'.$q.'%','%'.$q.'%','%'.$q.'%','%'.$q.'%');}$totalRows=(int)query('SELECT COUNT(*) FROM requests WHERE '.$where,$args)->fetchColumn();$list=rows('SELECT * FROM requests WHERE '.$where.' ORDER BY id DESC LIMIT 40 OFFSET '.(($pageNumber-1)*40),$args);}
}
if(in_array($section,['dashboard','statistics'],true)){
 try{[$from,$to,$start,$end]=admin_date_range();}catch(InvalidArgumentException $ex){$from=date('Y-m-d',strtotime('-29 days'));$to=date('Y-m-d');$start=$from.' 00:00:00';$end=$to.' 23:59:59';$adminError=$ex->getMessage();}
 $visitSummary=row('SELECT COUNT(*) views,COUNT(DISTINCT visitor_hash) visitors FROM visits WHERE created_at BETWEEN ? AND ?',[$start,$end]);
 $requestCount=(int)query('SELECT COUNT(*) FROM requests WHERE created_at BETWEEN ? AND ?',[$start,$end])->fetchColumn();
 $daily=rows('SELECT DATE(created_at) day,COUNT(*) views,COUNT(DISTINCT visitor_hash) visitors FROM visits WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY day',[$start,$end]);
 $topPages=rows('SELECT path,COUNT(*) views FROM visits WHERE created_at BETWEEN ? AND ? GROUP BY path ORDER BY views DESC LIMIT 12',[$start,$end]);
 $topProducts=rows('SELECT p.name,p.sku,COUNT(DISTINCT i.request_id) requests_count,SUM(i.quantity) quantity,p.unit FROM request_items i JOIN products p ON p.id=i.product_id JOIN requests r ON r.id=i.request_id WHERE r.created_at BETWEEN ? AND ? GROUP BY p.id,p.name,p.sku,p.unit ORDER BY requests_count DESC LIMIT 12',[$start,$end]);
 $deviceStats=rows('SELECT device,COUNT(*) views FROM visits WHERE created_at BETWEEN ? AND ? GROUP BY device',[$start,$end]);
 $recentRequests=rows('SELECT * FROM requests ORDER BY id DESC LIMIT 8');
}
require __DIR__.'/views/admin-header.php';
if(isset($entities[$section]))require __DIR__.'/views/admin-entity.php';
else require __DIR__.'/views/admin-'.$section.'.php';
require __DIR__.'/views/admin-footer.php';
