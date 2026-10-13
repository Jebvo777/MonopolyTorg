<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
$pdo=new PDO('mysql:host='.config('db_host').';port='.config('db_port').';charset=utf8mb4',config('db_user'),config('db_password'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$installed=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='system404_monop' AND table_name='products'")->fetchColumn();
if($installed && !in_array('--force',$_SERVER['argv'],true)){fwrite(STDERR,"Database already contains the site. Import is intended for an empty database; use --force only after a backup.\n");exit(1);}
$sql=file_get_contents(ROOT.'/database/monopoly_install.sql');
if($sql===false){fwrite(STDERR,"database/monopoly_install.sql is missing.\n");exit(1);}
// The generated script puts each complete statement on its own terminating line.
$statement='';foreach(explode("\n",$sql) as $line){if(str_starts_with(trim($line),'--')||trim($line)==='')continue;$statement.=$line."\n";if(str_ends_with(rtrim($line),';')){$pdo->exec($statement);$statement='';}}
echo "Database initialized.\n";
