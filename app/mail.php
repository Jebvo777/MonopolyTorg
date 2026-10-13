<?php
declare(strict_types=1);
function mail_option(string $key): string { $value=setting($key);return $value!==''?$value:(string)config($key,''); }
function smtp_read($stream): string {
    $reply='';$lines=0;
    do {
        $line=fgets($stream,8192);if($line===false)throw new RuntimeException('SMTP connection closed or timed out');
        $reply.=$line;if(++$lines>100)throw new RuntimeException('SMTP response too long');
    }while(strlen($line)>3&&$line[3]==='-');
    return $reply;
}
function smtp_command($stream,string $command,array $codes): string {
    if(fwrite($stream,$command."\r\n")===false)throw new RuntimeException('SMTP write failed');
    $reply=smtp_read($stream);if(!in_array((int)substr($reply,0,3),$codes,true))throw new RuntimeException('SMTP rejected command: '.trim(substr($reply,0,400)));
    return $reply;
}
function safe_email(string $email): string { if(!filter_var($email,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$email))throw new RuntimeException('Invalid email configuration');return $email; }
function smtp_send(string $to,string $from,string $message): void {
    $host=mail_option('smtp_host');$port=(int)mail_option('smtp_port');$mode=mail_option('smtp_encryption');
    if(!preg_match('/^[a-zA-Z0-9.-]+$/',$host)||$port<1||$port>65535)throw new RuntimeException('SMTP server is not configured');
    $context=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'peer_name'=>$host,'allow_self_signed'=>false]]);
    $target=($mode==='ssl'?'ssl://':'tcp://').$host.':'.$port;
    $stream=stream_socket_client($target,$errno,$error,8,STREAM_CLIENT_CONNECT,$context);
    if(!$stream)throw new RuntimeException('SMTP connection failed: '.$error);
    stream_set_timeout($stream,8);
    try{
        $greeting=smtp_read($stream);if(substr($greeting,0,3)!=='220')throw new RuntimeException('Unexpected SMTP greeting');
        smtp_command($stream,'EHLO monopoly-torg.ru',[250]);
        if($mode==='tls'){
            smtp_command($stream,'STARTTLS',[220]);
            if(!stream_socket_enable_crypto($stream,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('SMTP TLS negotiation failed');
            smtp_command($stream,'EHLO monopoly-torg.ru',[250]);
        }
        $user=mail_option('smtp_user');if($user!==''){
            smtp_command($stream,'AUTH LOGIN',[334]);smtp_command($stream,base64_encode($user),[334]);smtp_command($stream,base64_encode(mail_option('smtp_password')),[235]);
        }
        smtp_command($stream,'MAIL FROM:<'.$from.'>',[250]);smtp_command($stream,'RCPT TO:<'.$to.'>',[250,251]);smtp_command($stream,'DATA',[354]);
        $message=preg_replace('/\r?\n/',"\r\n",$message);$message=preg_replace('/(?m)^\./','..',$message);
        fwrite($stream,rtrim($message,"\r\n")."\r\n.\r\n");$reply=smtp_read($stream);
        if(substr($reply,0,3)!=='250')throw new RuntimeException('SMTP rejected message: '.trim(substr($reply,0,400)));
        smtp_command($stream,'QUIT',[221]);
    }finally{fclose($stream);}
}
function request_message(array $r,array $items): array {
    $subject='Новая заявка '.$r['public_id'].' — торговая компания «Монополия»';
    $lines=['Заявка '.$r['public_id'],'Дата: '.$r['created_at'],'Имя: '.$r['name'],'Компания: '.$r['company'],'Телефон: '.$r['phone'],'Email: '.$r['email'],'Город / доставка: '.$r['city'],'','Комментарий:',$r['message'],'','Позиции:'];
    foreach($items as $i=>$item)$lines[]=($i+1).'. '.$item['name'].' | '.$item['sku'].' | '.$item['quantity'].' '.$item['unit'].' | '.$item['parameters'];
    if(!$items)$lines[]='Заявка без товарных строк — см. сообщение и приложенный файл.';
    $lines[]='';$lines[]='Администрирование: '.absolute_url('/admin').'?section=requests&id='.$r['id'];
    $text=implode("\n",$lines);$to=safe_email(mail_option('mail_to'));$from=safe_email(mail_option('mail_from'));$reply=safe_email($r['email']);
    $headers=['Date: '.date(DATE_RFC2822),'From: =?UTF-8?B?'.base64_encode('Торговая компания «Монополия»').'?= <'.$from.'>','To: '.$to,'Reply-To: '.$reply,'Subject: =?UTF-8?B?'.base64_encode($subject).'?=','Message-ID: <'.bin2hex(random_bytes(16)).'@monopoly-torg.ru>','MIME-Version: 1.0'];
    if($r['attachment_path']){
        $path=ROOT.'/'.$r['attachment_path'];if(!is_file($path))throw new RuntimeException('Request attachment missing');
        $boundary='monopoly_'.bin2hex(random_bytes(16));$headers[]='Content-Type: multipart/mixed; boundary="'.$boundary.'"';
        $body='--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($text));
        $name='specification.'.pathinfo($r['attachment_name'],PATHINFO_EXTENSION);$utf8=rawurlencode($r['attachment_name']);
        $body.='--'.$boundary."\r\nContent-Type: ".$r['attachment_mime'].'; name="'.$name.'"'."\r\nContent-Disposition: attachment; filename=\"".$name.'"; filename*=UTF-8\'\''.$utf8."\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode(file_get_contents($path)));
        $body.='--'.$boundary."--\r\n";
    }else{$headers[]='Content-Type: text/plain; charset=UTF-8';$headers[]='Content-Transfer-Encoding: base64';$body=chunk_split(base64_encode($text));}
    return ['to'=>$to,'from'=>$from,'subject'=>$subject,'headers'=>$headers,'body'=>$body,'message'=>implode("\r\n",$headers)."\r\n\r\n".$body];
}
function send_outbox(int $requestId): bool {
    $claimed=query("UPDATE email_outbox SET status='sending',locked_at=NOW(),attempts=attempts+1 WHERE request_id=? AND attempts<5 AND (status IN ('pending','failed') OR (status='sending' AND locked_at<DATE_SUB(NOW(),INTERVAL 10 MINUTE)))",[$requestId])->rowCount();
    if(!$claimed)return false;
    try{
        $r=row('SELECT * FROM requests WHERE id=?',[$requestId]);if(!$r)throw new RuntimeException('Request missing');
        $items=rows('SELECT * FROM request_items WHERE request_id=? ORDER BY id',[$requestId]);$m=request_message($r,$items);
        if(mail_option('mail_transport')==='smtp')smtp_send($m['to'],$m['from'],$m['message']);
        elseif(mail_option('mail_transport')==='mail'){
            $headers=array_values(array_filter($m['headers'],fn($h)=>!str_starts_with($h,'To: ')&&!str_starts_with($h,'Subject: ')));
            if(!mail($m['to'],'=?UTF-8?B?'.base64_encode($m['subject']).'?=',$m['body'],implode("\r\n",$headers)))throw new RuntimeException('Hosting mail() did not accept the notification');
        }else throw new RuntimeException('Unknown mail transport');
        query("UPDATE email_outbox SET status='sent',sent_at=NOW(),last_error='',locked_at=NULL WHERE request_id=?",[$requestId]);query("UPDATE requests SET email_status='sent' WHERE id=?",[$requestId]);return true;
    }catch(Throwable $ex){
        $attempts=(int)query('SELECT attempts FROM email_outbox WHERE request_id=?',[$requestId])->fetchColumn();$delay=min(3600,60*(2**max(0,$attempts-1)));
        $error=substr($ex->getMessage(),0,2000);query("UPDATE email_outbox SET status='failed',last_error=?,available_at=DATE_ADD(NOW(),INTERVAL ".$delay." SECOND),locked_at=NULL WHERE request_id=?",[$error,$requestId]);query("UPDATE requests SET email_status='failed' WHERE id=?",[$requestId]);error_log('Request '.$requestId.' notification: '.$error);return false;
    }
}
