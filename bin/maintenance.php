<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
query('DELETE FROM login_attempts WHERE created_at<DATE_SUB(NOW(),INTERVAL 7 DAY)');
query('DELETE FROM visits WHERE created_at<DATE_SUB(NOW(),INTERVAL 12 MONTH)');
echo "Old anonymous statistics and login attempts removed.\n";
