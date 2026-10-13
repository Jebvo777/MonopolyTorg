<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';require ROOT.'/app/mail.php';
$lock=fopen(ROOT.'/storage/cache/mail-worker.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))exit;
$jobs=rows("SELECT request_id FROM email_outbox WHERE attempts<5 AND ((status IN ('pending','failed') AND available_at<=NOW()) OR (status='sending' AND locked_at<DATE_SUB(NOW(),INTERVAL 10 MINUTE))) ORDER BY id LIMIT 20");
foreach($jobs as $job){$ok=send_outbox((int)$job['request_id']);echo 'Request '.$job['request_id'].': '.($ok?'sent':'deferred').PHP_EOL;}
flock($lock,LOCK_UN);fclose($lock);
