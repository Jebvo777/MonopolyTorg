<?php
// Local PHP server. Use Apache/Nginx configuration from the installation guide in production.
$config=require dirname(__DIR__).'/app/config.php';
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/');
$base=$config['base_path'];
if($base&&($path===$base||str_starts_with($path,$base.'/')))$path=substr($path,strlen($base))?:'/';
if(preg_match('~^/(?:app|data|database|docs|tools|bin|storage|asset-packs|releases|ci)(?:/|$)|(?:^|/)\.|(?:^|/)(?:ACCESS|INSTALL)\.txt$|\.(?:sql|zip|log|json|md|py)$~i',$path)){http_response_code(403);exit;}
$root=realpath($config['public_root']);
$file=realpath($root.$path);
if($file && str_starts_with($file,$root.DIRECTORY_SEPARATOR) && is_file($file))return false;
require $root.'/index.php';
