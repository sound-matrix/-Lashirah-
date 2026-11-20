<?php
declare(strict_types=1);
session_name('lashirah_admin');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function fail(string $message,int $status=400): never {http_response_code($status);echo json_encode(['ok'=>false,'error'=>$message]);exit;}
if (($_SERVER['REQUEST_METHOD']??'')!=='POST')fail('Method not allowed',405);
if (empty($_SESSION['lash_admin']))fail('Please sign in again',401);
if (!hash_equals((string)($_SESSION['csrf']??''),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))fail('Invalid session',403);
if ((int)($_SERVER['CONTENT_LENGTH']??0)>24*1024*1024)fail('Publish payload too large',413);
$input=json_decode((string)file_get_contents('php://input'),true);
if (!is_array($input)||!is_array($input['data']??null))fail('Invalid content');
$d=$input['data'];
foreach(['medals','stats','achievements','gallery','videos','tiers','social','videoSlots'] as $field){
    if (!isset($d[$field])||!is_array($d[$field]))fail('Missing or invalid '.$field);
}
if (count($d['gallery'])>200||count($d['videos'])>100||count($d['achievements'])>100||count($d['tiers'])>30)fail('Too many items');
if (!is_string($d['contactEmail']??null)||strlen($d['contactEmail'])>250)fail('Invalid contact email');
foreach($d['videos'] as &$video){
    if (!is_array($video)||strlen((string)($video['title']??''))>160)fail('Invalid video');
    $src=(string)($video['src']??'');
    if (str_starts_with($src,'youtube:')&&!preg_match('/^youtube:[A-Za-z0-9_-]{11}$/D',$src)) fail('Invalid YouTube video ID');
}
unset($video);
$dir=__DIR__.'/../storage';
$mediaDir=__DIR__.'/../uploads/gallery';
if ((!is_dir($dir)&&!mkdir($dir,0750,true))||(!is_dir($mediaDir)&&!mkdir($mediaDir,0755,true))) fail('Storage unavailable',500);
$cssFile=$dir.'/media.css';
$existing=is_file($cssFile)?(string)file_get_contents($cssFile):'';
$css=$existing;
$images=$input['images']??[];
if (!is_array($images)||count($images)>80)fail('Too many images');
foreach($images as $id=>$dataUrl){
    if (!is_string($id)||!preg_match('/^[A-Za-z0-9_-]{1,72}$/D',$id)||!is_string($dataUrl))fail('Invalid image');
    if (!preg_match('~^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$~D',$dataUrl,$match))fail('Only compressed JPEG uploads supported');
    $bytes=base64_decode($match[1],true);
    if ($bytes===false||strlen($bytes)>8*1024*1024||strlen($bytes)<20||@getimagesizefromstring($bytes)===false)fail('Invalid image upload');
    $name=hash('sha256',$bytes).'.jpg';
    if (!is_file($mediaDir.'/'.$name)&&file_put_contents($mediaDir.'/'.$name,$bytes,LOCK_EX)===false)fail('Image storage failed',500);
    $rule='.ph-'.$id.'{background-image:url("https://lashirah.com/uploads/gallery/'.$name.'")}'."\n";
    $css=preg_replace('/\\.ph-'.preg_quote($id,'/').'\\{[^}]*\\}\\s*/','',$css);
    $css.=$rule;
}
if (strlen($css)>500000)fail('Gallery stylesheet too large');
$d['rev']='published-'.gmdate('YmdHis');
$d['pinHash']=''; // PIN is not a security mechanism. Authentication is server-side.
$json=json_encode(['published'=>true,'data'=>$d],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
if (strlen($json)>2*1024*1024)fail('Content too large');
$tmp=$dir.'/site.'.bin2hex(random_bytes(8)).'.tmp';
if (file_put_contents($tmp,$json,LOCK_EX)===false||!rename($tmp,$dir.'/site.json'))fail('Unable to save published content',500);
if (file_put_contents($cssFile,$css,LOCK_EX)===false)fail('Unable to save image styles',500);
echo json_encode(['ok'=>true,'publishedAt'=>gmdate('c')]);
