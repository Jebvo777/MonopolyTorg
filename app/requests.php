<?php
declare(strict_types=1);
require_once __DIR__.'/mail.php';
function ip_hash(): string { return hash_hmac('sha256',$_SERVER['REMOTE_ADDR']??'cli',hash('sha256',(string)config('db_password').config('base_url'))); }
function clean_input(string $key,int $max,bool $required=false): string {
    $value=trim((string)($_POST[$key]??''));
    if($required&&$value==='')throw new InvalidArgumentException('Заполните обязательные контактные данные.');
    if(mb_strlen($value)>$max)throw new InvalidArgumentException('Одно из полей превышает допустимую длину.');
    if(str_contains($value,"\0"))throw new InvalidArgumentException('Недопустимые символы в форме.');
    return $value;
}
function quantity(mixed $value): string {
    if(!is_numeric($value)||!is_finite((float)$value)||(float)$value<0.001||(float)$value>10000000)throw new InvalidArgumentException('Укажите количество от 0,001 до 10 000 000.');
    return number_format((float)$value,3,'.','');
}
function cart_update_fields(array $item,array $values): array {
    $item['quantity']=quantity($values['quantity']??$item['quantity']);
    $unit=trim((string)($values['unit']??$item['unit']));$params=trim((string)($values['parameters']??$item['parameters']??''));
    if($unit===''||mb_strlen($unit)>20||mb_strlen($params)>1000)throw new InvalidArgumentException('Проверьте единицу измерения и параметры позиции.');
    $item['unit']=$unit;$item['parameters']=$params;return $item;
}
function process_cart(): array {
    $items=cart();$action=clean_input('action',30,true);
    if($action==='add'){
        $p=row('SELECT p.* FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=? AND p.active=1 AND c.active=1',[(int)($_POST['product_id']??0)]);
        if(!$p)throw new InvalidArgumentException('Эта позиция недоступна. Выберите товар в каталоге.');
        $params=clean_input('parameters',1000);$q=quantity($_POST['quantity']??1);$key='p'.$p['id'].'-'.substr(hash('sha256',$params),0,12);
        if(isset($items[$key]))$q=quantity((float)$items[$key]['quantity']+(float)$q);
        $items[$key]=['product_id'=>(int)$p['id'],'name'=>$p['name'],'sku'=>$p['sku'],'slug'=>$p['slug'],'image'=>$p['image'],'quantity'=>$q,'unit'=>$p['unit'],'parameters'=>$params];
    }elseif($action==='manual'){
        $name=clean_input('name',255,true);$unit=clean_input('unit',20,true);$q=quantity($_POST['quantity']??1);
        $key='m'.bin2hex(random_bytes(8));$items[$key]=['product_id'=>null,'name'=>$name,'sku'=>'','slug'=>'','image'=>'assets/img/product-placeholder.svg','quantity'=>$q,'unit'=>$unit,'parameters'=>''];
    }elseif(in_array($action,['update','remove'],true)){
        $key=clean_input('key',80,true);if(!isset($items[$key]))throw new InvalidArgumentException('Позиция уже удалена из заявки.');
        if($action==='remove')unset($items[$key]);else $items[$key]=cart_update_fields($items[$key],$_POST);
    }elseif($action==='clear')$items=[];
    else throw new InvalidArgumentException('Неизвестное действие с заявкой.');
    if(count($items)>200)throw new InvalidArgumentException('В заявку можно добавить до 200 строк. Для большего списка приложите спецификацию.');
    $_SESSION['cart']=$items;return ['ok'=>true,'count'=>count($items)];
}
function receive_upload(string $field,bool $public=false): ?array {
    $f=$_FILES[$field]??null;if(!$f||($f['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;
    if($f['error']!==UPLOAD_ERR_OK)throw new InvalidArgumentException('Файл не удалось загрузить. Выберите файл до 10 МБ.');
    if($f['size']>config('max_upload_bytes')||$f['size']<1)throw new InvalidArgumentException('Файл должен быть непустым и размером до 10 МБ.');
    if(!is_uploaded_file($f['tmp_name']))throw new InvalidArgumentException('Недопустимая загрузка файла.');
    $name=basename(str_replace('\\','/',(string)$f['name']));$name=preg_replace('/[\x00-\x1f\x7f]/u','',$name);$name=mb_substr($name,0,240);
    $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
    $allowed=['pdf'=>['application/pdf'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'png'=>['image/png'],'webp'=>['image/webp'],'gif'=>['image/gif'],'ico'=>['image/vnd.microsoft.icon','image/x-icon'],'csv'=>['text/plain','text/csv','application/csv','application/vnd.ms-excel'],'xls'=>['application/vnd.ms-excel','application/x-ole-storage','application/CDFV2','application/octet-stream'],'doc'=>['application/msword','application/x-ole-storage','application/CDFV2','application/octet-stream'],'xlsx'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip','application/octet-stream'],'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip','application/octet-stream']];
    $privateAllowed=['pdf','jpg','jpeg','png','csv','xls','doc','xlsx','docx'];
    if(!isset($allowed[$ext])||(!$public&&!in_array($ext,$privateAllowed,true))||($public&&!in_array($ext,['pdf','jpg','jpeg','png','webp','gif','ico'],true)))throw new InvalidArgumentException('Недопустимый формат. Поддерживаются PDF, XLS/XLSX, CSV, DOC/DOCX, JPG и PNG.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if(!in_array($mime,$allowed[$ext],true))throw new InvalidArgumentException('Содержимое файла не соответствует его расширению.');
    $first=file_get_contents($f['tmp_name'],false,null,0,8);
    if($ext==='pdf'&&!str_starts_with($first,'%PDF-'))throw new InvalidArgumentException('Некорректный PDF.');
    if(in_array($ext,['xls','doc'],true)&&$first!==hex2bin('d0cf11e0a1b11ae1'))throw new InvalidArgumentException('Некорректный файл Office.');
    if(in_array($ext,['xlsx','docx'],true)&&!str_starts_with($first,"PK\x03\x04"))throw new InvalidArgumentException('Некорректный файл Office.');
    if(in_array($ext,['jpg','jpeg','png','webp','gif','ico'],true)){
        $size=@getimagesize($f['tmp_name']);if(!$size||$size[0]>12000||$size[1]>12000)throw new InvalidArgumentException('Некорректное изображение или слишком большие размеры.');
    }
    if($ext==='csv'){
        $bytes=file_get_contents($f['tmp_name']);if(str_contains($bytes,"\0")||preg_match('/<\?(?:php|=)/i',$bytes))throw new InvalidArgumentException('Некорректный CSV.');
    }
    $filename=bin2hex(random_bytes(20)).'.'.$ext;
    $relative=$public?'assets/'.($ext==='pdf'?'documents':'img/uploads').'/'.$filename:'storage/specifications/'.$filename;
    $absolute=$public?PUBLIC_ROOT.'/'.$relative:ROOT.'/'.$relative;
    if(!is_dir(dirname($absolute))&&!mkdir(dirname($absolute),0750,true))throw new RuntimeException('Cannot create upload directory');
    if(!move_uploaded_file($f['tmp_name'],$absolute))throw new RuntimeException('Cannot store uploaded file');
    return ['path'=>$relative,'name'=>$name,'mime'=>$mime,'bytes'=>(int)$f['size']];
}
function process_request(): array {
    if(clean_input('website',500)!=='')throw new InvalidArgumentException('Не удалось отправить форму. Свяжитесь с компанией напрямую.');
    $nonce=clean_input('idempotency',60,true);if(!preg_match('/^[a-f0-9]{40}$/',$nonce))throw new InvalidArgumentException('Обновите страницу заявки.');
    $key=hash('sha256',session_id().'|'.$nonce);
    if($existing=row('SELECT public_id FROM requests WHERE idempotency_key=?',[$key])){$_SESSION['last_request']=$existing['public_id'];return ['ok'=>true,'number'=>$existing['public_id'],'count'=>cart_count()];}
    if(!hash_equals($_SESSION['request_nonce']??'',$nonce))throw new InvalidArgumentException('Обновите страницу и повторите отправку.');
    $input=['name'=>clean_input('name',120,true),'phone'=>clean_input('phone',40,true),'email'=>clean_input('email',190,true),'company'=>clean_input('company',190),'city'=>clean_input('city',255),'message'=>clean_input('message',8000)];
    if(!filter_var($input['email'],FILTER_VALIDATE_EMAIL)||str_contains($input['email'],"\r")||str_contains($input['email'],"\n"))throw new InvalidArgumentException('Укажите корректный email.');
    $digits=preg_replace('/\D/','',$input['phone']);if(strlen($digits)<7||strlen($digits)>16)throw new InvalidArgumentException('Укажите корректный номер телефона.');
    if(($_POST['consent']??'')!=='1')throw new InvalidArgumentException('Подтвердите согласие на обработку данных.');
    $type=clean_input('type',30);if(!in_array($type,['cart','contact'],true))$type='cart';
    $ip=ip_hash();$count=(int)query("SELECT COUNT(*) FROM requests WHERE ip_hash=? AND created_at>DATE_SUB(NOW(),INTERVAL 1 HOUR)",[$ip])->fetchColumn();
    if($count>=10)throw new InvalidArgumentException('За короткое время отправлено много заявок. Повторите позже или напишите на monopoly@list.ru.');
    $items=$type==='cart'?cart():[];$updates=json_value((string)($_POST['cart_updates']??''),[]);
    foreach($items as $k=>$item){
        if(isset($updates[$k]))$items[$k]=cart_update_fields($item,(array)$updates[$k]);
        if($item['product_id']){
            $p=row('SELECT p.* FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=? AND p.active=1 AND c.active=1',[$item['product_id']]);
            if(!$p)throw new InvalidArgumentException('Одна из позиций больше недоступна. Удалите ее из корзины или внесите наименование вручную.');
            $items[$k]['name']=$p['name'];$items[$k]['sku']=$p['sku'];
        }
    }
    if(!$items&&$input['message']===''&&empty($_FILES['specification']['name']))throw new InvalidArgumentException('Добавьте товар, напишите сообщение или приложите спецификацию.');
    $file=receive_upload('specification');$pdo=db();
    try{
        $pdo->beginTransaction();
        query('INSERT INTO requests (idempotency_key,type,name,email,phone,company,city,message,manager_note,attachment_path,attachment_name,attachment_mime,attachment_bytes,ip_hash,source,utm_source,consent_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())',[$key,$type,$input['name'],$input['email'],$input['phone'],$input['company'],$input['city'],$input['message'],'',$file['path']??null,$file['name']??null,$file['mime']??null,$file['bytes']??null,$ip,clean_input('source',255),substr((string)($_SESSION['utm_source']??''),0,100)]);
        $id=(int)$pdo->lastInsertId();$number='M-'.date('Ymd').'-'.str_pad((string)$id,5,'0',STR_PAD_LEFT);
        query('UPDATE requests SET public_id=? WHERE id=?',[$number,$id]);
        foreach($items as $item)query('INSERT INTO request_items (request_id,product_id,name,sku,quantity,unit,parameters) VALUES (?,?,?,?,?,?,?)',[$id,$item['product_id'],$item['name'],$item['sku'],$item['quantity'],$item['unit'],$item['parameters']??'']);
        query("INSERT INTO email_outbox (request_id,last_error) VALUES (?,'')",[$id]);$pdo->commit();
    }catch(Throwable $ex){if($pdo->inTransaction())$pdo->rollBack();if($file)@unlink(ROOT.'/'.$file['path']);throw $ex;}
    $_SESSION['last_request']=$number;$_SESSION['request_nonce']=bin2hex(random_bytes(20));if($type==='cart')$_SESSION['cart']=[];unset($_SESSION['form_input']);
    // A failed notification never discards a saved request. Cron retries the durable outbox.
    try{send_outbox($id);}catch(Throwable $ex){error_log('Mail delivery: '.$ex->getMessage());}
    return ['ok'=>true,'number'=>$number,'count'=>cart_count()];
}
function handle_api(string $path): never {
    header('Cache-Control: no-store');
    if($path==='/api/cart'&&($_SERVER['REQUEST_METHOD']??'GET')==='GET')json_response(['ok'=>true,'count'=>cart_count(),'items'=>array_values(cart())]);
    if(!in_array($path,['/api/cart','/api/request'],true))json_response(['ok'=>false,'error'=>'Запрос не найден.'],404);
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){header('Allow: POST');json_response(['ok'=>false,'error'=>'Используйте отправку формы.'],405);}
    $ajax=str_contains($_SERVER['HTTP_ACCEPT']??'','application/json');
    try{validate_csrf();$data=$path==='/api/cart'?process_cart():process_request();if($ajax)json_response($data);if($path==='/api/request')redirect('/success');flash('success','Заявка обновлена.');redirect('/request');}
    catch(InvalidArgumentException $ex){if($ajax)json_response(['ok'=>false,'error'=>$ex->getMessage()],422);if($path==='/api/request')$_SESSION['form_input']=array_intersect_key($_POST,array_flip(['name','phone','email','company','city','message']));flash('error',$ex->getMessage());redirect($path==='/api/request'&&($_POST['type']??'')==='contact'?'/contacts#contact-form':'/request');}
}
