<?php
declare(strict_types=1);
// Isolated CI credentials are never production credentials.
$config = ['db_host'=>'127.0.0.1','db_port'=>3306,'db_name'=>'system404_monop','db_user'=>'root','db_password'=>'qa-only-root','base_url'=>'http://127.0.0.1:8085','base_path'=>''];
file_put_contents(__DIR__.'/../app/config.local.php',"<?php\nreturn ".var_export($config,true).";\n");
require __DIR__.'/../app/bootstrap.php';
$password = bin2hex(random_bytes(24));
file_put_contents(__DIR__.'/.qa-password',$password);
query('UPDATE admin_users SET password_hash=? WHERE username=?',[password_hash($password,PASSWORD_DEFAULT),'admin']);
query("UPDATE settings SET value='0' WHERE name='analytics_enabled'");
