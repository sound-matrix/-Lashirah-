<?php
declare(strict_types=1);
require __DIR__.'/../cms/library.php';
session_name('lashirah_admin');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
session_start();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
$configPath=__DIR__.'/config.local.php';
$config=is_file($configPath)?require $configPath:null;
$ready=is_array($config)&&!empty($config['username'])&&!empty($config['password_hash'])&&$config['password_hash']!=='PASTE_PASSWORD_HASH_HERE';
if (!isset($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24));
$error='';$success='';
function allowed(): bool {return !empty($_SESSION['lash_admin']);}
function csrf_check(): void {if (!hash_equals((string)($_SESSION['csrf']??''),(string)($_POST['csrf']??''))) {http_response_code(403);exit('Invalid session. Refresh and retry.');}}
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    csrf_check();$action=(string)($_POST['action']??'');
    if ($action==='login' && $ready) {
        if (hash_equals((string)$config['username'],(string)($_POST['username']??'')) && password_verify((string)($_POST['password']??''),(string)$config['password_hash'])) {
            session_regenerate_id(true);$_SESSION['lash_admin']=true;header('Location: ./');exit;
        }
        usleep(300000);$error='Invalid username or password.';
    } elseif ($action==='logout') {
        $_SESSION=[];session_destroy();header('Location: ./');exit;
    } elseif (allowed()) {
        try {
            $data=lash_data();
            if ($action==='add_video') {
                $id=lash_youtube_id((string)($_POST['url']??''));
                if (!$id) throw new RuntimeException('Enter a valid YouTube video, Shorts or live URL.');
                $title=trim((string)($_POST['title']??''));
                if ($title===''||mb_strlen($title)>150) throw new RuntimeException('A title up to 150 characters is required.');
                array_unshift($data['videos'],['id'=>bin2hex(random_bytes(8)),'youtube_id'=>$id,'title'=>$title]);
                lash_save($data);$success='YouTube video published.';
            } elseif ($action==='add_photo') {
                if (!isset($_FILES['photo'])||!is_array($_FILES['photo'])||$_FILES['photo']['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Select an image to upload (max 8 MB).');
                $tmp=(string)$_FILES['photo']['tmp_name'];$size=(int)$_FILES['photo']['size'];
                if ($size<1||$size>8*1024*1024) throw new RuntimeException('Image must be under 8 MB.');
                $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
                $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$mime]??null;
                if (!$ext||getimagesize($tmp)===false) throw new RuntimeException('Only valid JPG, PNG, WebP or GIF images are allowed.');
                $dir=__DIR__.'/../uploads/gallery';if (!is_dir($dir)&&!mkdir($dir,0755,true)) throw new RuntimeException('Upload folder unavailable.');
                $name=bin2hex(random_bytes(16)).'.'.$ext;
                if (!move_uploaded_file($tmp,$dir.'/'.$name)) throw new RuntimeException('Image upload failed. Check folder permissions.');
                $caption=mb_substr(trim((string)($_POST['caption']??'')),0,160);
                array_unshift($data['photos'],['id'=>bin2hex(random_bytes(8)),'url'=>'/uploads/gallery/'.$name,'caption'=>$caption]);
                lash_save($data);$success='Photo published.';
            } elseif ($action==='delete') {
                $type=(string)($_POST['type']??'');$id=(string)($_POST['id']??'');
                if (!in_array($type,['photos','videos'],true)) throw new RuntimeException('Invalid gallery type.');
                $removed=null;
                $data[$type]=array_values(array_filter($data[$type],static function($item)use($id,&$removed){if (($item['id']??'')===$id){$removed=$item;return false;}return true;}));
                lash_save($data);
                if ($type==='photos' && $removed && preg_match('~^/uploads/gallery/[a-f0-9]{32}\.(?:jpg|png|webp|gif)$~',$removed['url']??'')) @unlink(__DIR__.'/..'.$removed['url']);
                $success='Gallery item removed.';
            } else throw new RuntimeException('Unknown action.');
        } catch (Throwable $e) {$error=$e->getMessage();}
    }
}
$data=allowed()?lash_data():['photos'=>[],'videos'=>[]];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Lashirah · Client CMS</title>
<style>
:root{color-scheme:dark;font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif;background:#081226;color:#eaf1ff}*{box-sizing:border-box}body{margin:0;min-height:100vh}header{padding:24px max(20px,calc((100vw - 1100px)/2));border-bottom:1px solid #263650;background:#101c32;display:flex;align-items:center;justify-content:space-between;gap:16px}h1{font-size:22px;margin:0}h2{font-size:18px}p,small{color:#adbed7}main{max-width:1100px;margin:42px auto;padding:0 20px}.panel{background:#111e35;border:1px solid #2a3c5b;border-radius:18px;padding:24px;margin-bottom:24px}.login{max-width:450px;margin:80px auto}label{display:block;margin:14px 0 6px;font-size:13px;color:#cbd9ee}input{width:100%;background:#09152a;border:1px solid #3a506f;border-radius:10px;padding:13px;color:#fff;font:inherit}button{font:inherit;border:0;border-radius:10px;padding:12px 17px;background:#d3ac64;color:#101728;cursor:pointer;font-weight:700}button.danger{background:#422437;color:#ffccd5}button.ghost{background:#263750;color:#eaf1ff}.grid{display:grid;grid-template-columns:1fr 1fr;gap:24px}.items{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px}.item{background:#0b1527;border:1px solid #283c56;border-radius:12px;overflow:hidden}.item img{width:100%;aspect-ratio:16/10;object-fit:cover}.item .info{padding:12px}.info p{overflow-wrap:anywhere;min-height:36px;margin:5px 0 12px}form.inline{display:inline}form.inline button{padding:8px 12px}.notice{padding:12px 16px;border-radius:10px;background:#173a35;color:#b8f5dd;margin-bottom:20px}.error{background:#4c2431;color:#ffd7dd}.tabs{display:flex;gap:8px;margin:20px 0}.tabs a{color:#fff;text-decoration:none;background:#263750;padding:10px 16px;border-radius:9px}@media(max-width:750px){.grid{grid-template-columns:1fr}header{padding:18px 20px}}
</style></head><body><header><div><h1>LASHIRAH <span style="color:#d3ac64">/ CONTENT MANAGER</span></h1><small>Private client administration</small></div><?php if(allowed()): ?><form method="post"><input type="hidden" name="csrf" value="<?=lash_escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="logout"><button class="ghost">Sign out</button></form><?php endif ?></header><main>
<?php if(!$ready): ?><div class="panel login"><h2>Complete secure setup</h2><p>On Hostinger, create <code>admin/config.local.php</code> from <code>admin/config.example.php</code>, generate a password hash and set your admin username. No default login is provided.</p></div>
<?php elseif(!allowed()): ?><div class="panel login"><h2>Client sign in</h2><?php if($error): ?><div class="notice error"><?=lash_escape($error)?></div><?php endif ?><form method="post"><input type="hidden" name="csrf" value="<?=lash_escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="login"><label>Username</label><input name="username" autocomplete="username" required><label>Password</label><input type="password" name="password" autocomplete="current-password" required><p><button type="submit">Secure sign in</button></p></form></div>
<?php else: ?>
<p>Changes publish immediately to the website’s separate galleries.</p>
<?php if($error): ?><div class="notice error"><?=lash_escape($error)?></div><?php endif ?><?php if($success): ?><div class="notice"><?=lash_escape($success)?></div><?php endif ?>
<div class="grid"><section class="panel"><h2>Image Gallery</h2><p>Upload JPG, PNG, WebP or GIF images (8 MB maximum).</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=lash_escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="add_photo"><label>Choose image</label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif" required><label>Caption</label><input name="caption" maxlength="160"><p><button>Publish image</button></p></form></section>
<section class="panel"><h2>Video Gallery · YouTube</h2><p>Paste a YouTube watch, Shorts, share or live URL. No video file upload is needed.</p><form method="post"><input type="hidden" name="csrf" value="<?=lash_escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="add_video"><label>Video title</label><input name="title" maxlength="150" required><label>YouTube URL</label><input type="url" name="url" placeholder="https://www.youtube.com/watch?v=..." required><p><button>Publish video</button></p></form></section></div>
<section class="panel"><h2>Published images (<?=count($data['photos'])?>)</h2><div class="items"><?php foreach($data['photos'] as $item): ?><div class="item"><img loading="lazy" src="<?=lash_escape($item['url'])?>" alt="<?=lash_escape($item['caption'])?>"><div class="info"><p><?=lash_escape($item['caption'])?></p><form method="post" class="inline" onsubmit="return confirm('Delete this image?')"><input type="hidden" name="csrf" value="<?=lash_escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="type" value="photos"><input type="hidden" name="id" value="<?=lash_escape($item['id'])?>"><button class="danger">Delete</button></form></div></div><?php endforeach ?></div></section>
<section class="panel"><h2>Published YouTube videos (<?=count($data['videos'])?>)</h2><div class="items"><?php foreach($data['videos'] as $item): ?><div class="item"><img loading="lazy" src="https://i.ytimg.com/vi/<?=lash_escape($item['youtube_id'])?>/hqdefault.jpg" alt=""><div class="info"><p><?=lash_escape($item['title'])?></p><form method="post" class="inline" onsubmit="return confirm('Delete this video?')"><input type="hidden" name="csrf" value="<?=lash_escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="type" value="videos"><input type="hidden" name="id" value="<?=lash_escape($item['id'])?>"><button class="danger">Delete</button></form></div></div><?php endforeach ?></div></section>
<?php endif ?></main></body></html>
