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
// The client approved a single four-digit PIN instead of a second username/password.
 // Keep verification server-side. Hostinger may override this hash in config.local.php.
$pinHash=(is_array($config)&&!empty($config['pin_hash']))?(string)$config['pin_hash']:'$2y$12$1G0//ovyeDdSqFgtkT8G/ebNspp3T7tsJXMIHKmNl7jB1bV/APFDi';
$ready=true;
if (!isset($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24));
$error='';$success='';
function allowed(): bool {return !empty($_SESSION['lash_admin']);}
function csrf_check(): void {if (!hash_equals((string)($_SESSION['csrf']??''),(string)($_POST['csrf']??''))) {http_response_code(403);exit('Invalid session. Refresh and retry.');}}
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    csrf_check();$action=(string)($_POST['action']??'');
    if ($action==='login' && $ready) {
        $now=time();
        $attempts=$_SESSION['pin_attempts']??[];
        $attempts=array_values(array_filter(is_array($attempts)?$attempts:[],static fn($t)=>is_int($t)&&$t>$now-900));
        if (count($attempts)>=5) {
            http_response_code(429);$error='Too many attempts. Try again in 15 minutes.';
        } elseif (preg_match('/^[0-9]{4}$/D',(string)($_POST['pin']??'')) && password_verify((string)$_POST['pin'],$pinHash)) {
            session_regenerate_id(true);$_SESSION['pin_attempts']=[];$_SESSION['lash_admin']=true;header('Location: ./');exit;
        } else {
            $attempts[]=$now;$_SESSION['pin_attempts']=$attempts;
            usleep(300000);$error='Incorrect PIN. Please try again.';
        }
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

// Authenticated client dashboard: preserve the approved original HTML admin design.
if (allowed() && ($_SERVER['REQUEST_METHOD']??'GET')==='GET') {
    $html=file_get_contents(__DIR__.'/../index.html');
    if ($html===false) {http_response_code(500);exit('Portfolio template unavailable');}
    $source=__DIR__.'/../storage/site.json';
    $saved=is_file($source)?json_decode((string)file_get_contents($source),true):null;
    if (is_array($saved)&&!empty($saved['published'])&&is_array($saved['data']??null)) {
        // The public publishing endpoint deliberately strips PIN metadata.
        // Restore the original dashboard PIN hash for the authenticated editor.
        $saved['data']['pinHash']='p7c794d2c';
        $dataJson=json_encode($saved['data'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE);
        $html=preg_replace('~(<script type="application/json" id="site-data">).*?(</script>)~s','$1'.$dataJson.'$2',$html,1);
    }
    $html=str_replace('<head>','<head><script>window.__IS_ADMIN_SITE=true;</script><base href="https://lashirah.com/"><meta name="robots" content="noindex,nofollow"><style>.ad-card:has(#ad-newpin){display:none!important}#admin{z-index:999999!important}</style><link rel="stylesheet" href="https://lashirah.com/cms/media.php">',$html);
    // Keep the original PIN lock rather than forcing the editor open.
    // Avoid previous browser-only edits overwriting the server's published state.
    $html=str_replace("var sv=JSON.parse(localStorage.getItem(LS_KEY)||'null');","var sv=null;",$html);
    // Server session already verified the PIN: do not ask the client a second time.
    $html=str_replace("var A=null, tab='medals', editAch=-1, unlocked=false;",
      "var A=null, tab='medals', editAch=-1, unlocked=!!window.__IS_ADMIN_SITE;",$html);
    $html=str_replace("try{ unlocked=sessionStorage.getItem(SS_KEY)===D.pinHash; }catch(e){}",
      "try{ if(!window.__IS_ADMIN_SITE) unlocked=sessionStorage.getItem(SS_KEY)===D.pinHash; }catch(e){}",$html);
    // Lock means sign out of the server session, not another client-side PIN prompt.
    $html=str_replace("case 'lock': unlocked=false; try{ sessionStorage.removeItem(SS_KEY); }catch(e){} render(); return;",
      "case 'lock': { var f=document.createElement('form'); f.method='POST'; f.action='./'; var a=document.createElement('input');a.type='hidden';a.name='action';a.value='logout';f.appendChild(a);var c=document.createElement('input');c.type='hidden';c.name='csrf';c.value=window.__CMS_TOKEN||'';f.appendChild(c);document.body.appendChild(f);f.submit();return; }",$html);
    $html=str_replace('⬇ Download updated website','✓ Publish to website',$html);
    // Use a real link for View site so browser navigation works without JS/popup permissions.
    $html=str_replace('<button type="button" class="ad-btn sm" data-act="close">View site</button>',
      '<a class="ad-btn sm" href="https://lashirah.com/" target="_blank" rel="noopener noreferrer">View site</a>', $html);
    // The editor is hosted on admin.lashirah.com. Close must open the public website,
    // not merely remove the overlay while leaving the admin-only page visible.
    $html=str_replace("case 'close': close(); return;",
      "case 'close': window.open('https://lashirah.com/','_blank','noopener'); return;", $html);
    $html=str_replace('Edits are saved on this device only until you download the updated website.','Review changes, then click Publish to update the live website.',$html);
    $html=str_replace('Your edits are only on this device. Download the updated website to share it. It\\\'s one HTML file with everything inside.','Click Publish to update lashirah.com immediately.',$html);
    $html=str_replace('case \'dl\': busy(true); downloadSite().then(function(){busy(false);},function(){busy(false);}); return;',
      'case \'dl\': busy(true); fetch(window.location.origin+"/publish.php",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-Token":window.__CMS_TOKEN},credentials:"same-origin",body:JSON.stringify({data:D,images:CMS.getPendingImages()})}).then(function(r){return r.text().then(function(raw){var j;try{j=JSON.parse(raw);}catch(e){throw Error("Server returned HTTP "+r.status+". Please refresh and try again.");}if(!r.ok||!j.ok)throw Error(j.error||"Publish failed");return j;});}).then(function(){busy(false);toastMsg("✓ Published to lashirah.com successfully");},function(e){busy(false);toastMsg("Publish failed: "+e.message);}); return;',
      $html);
    // Let the client paste a YouTube URL into the existing Videos tab.
    $needle="var h='<div class=\"ad-card\"><h3>Video gallery</h3>";
    $html=str_replace($needle,"var h='<div class=\"ad-card\"><h3>Video gallery</h3>",$html);
    $html=str_replace("h+='</div></div><div class=\"ad-card\"><h3>“Coming soon” tiles", 
      "h+='</div><div class=\"ad-card\" style=\"margin-top:16px\"><h3>Add a YouTube video</h3><p class=\"ad-help\">Paste a YouTube watch, short, live, or share URL.</p><div class=\"row\"><div class=\"fld\"><label>Video title</label><input id=\"youtube-title\" placeholder=\"Video title\"></div><div class=\"fld\"><label>YouTube link</label><input id=\"youtube-url\" type=\"url\" placeholder=\"https://youtu.be/...\"></div></div><button type=\"button\" class=\"ad-btn pri\" data-act=\"youtube-add\">＋ Add YouTube video</button></div></div><div class=\"ad-card\"><h3>“Coming soon” tiles",
      $html);
    $html=str_replace("case 'vid-del':", <<<'JS'
case 'youtube-add':
        var title=(document.getElementById('youtube-title')||{}).value||'',raw=(document.getElementById('youtube-url')||{}).value||'',id='';
        try {var url=new URL(raw),host=url.hostname.toLowerCase().replace(/^www\./,'');
          if(url.protocol!=='https:')throw Error('Invalid protocol');
          if(host==='youtu.be')id=url.pathname.split('/')[1]||'';
          else if(['youtube.com','m.youtube.com','music.youtube.com'].indexOf(host)>=0){var seg=url.pathname.split('/');id=seg[1]==='watch'?url.searchParams.get('v'):(['shorts','embed','live'].indexOf(seg[1])>=0?seg[2]:'');}
        } catch(e){}
        if(!/^[A-Za-z0-9_-]{11}$/.test(id)){toastMsg('Enter a valid YouTube URL');return;}
        title=title.trim(); if(!title){toastMsg('Please enter a video title');return;}
        D.videos.push({title:title.slice(0,150),caption:'',src:'youtube:'+id,poster:''});changed(true);toastMsg('YouTube video added. Click Publish to make it live.');return;
      case 'vid-del':
JS, $html);
    // Prevent video file uploads which cannot be published by the lightweight YouTube CMS.
    $html=str_replace("upBtn('＋ Upload video','video','video/mp4,video/quicktime,video/*')","'<span class=\"ad-help\">Use YouTube links for video publishing.</span>'",$html);
    $html=str_replace("/* Legacy browser-only editor disabled: the secure Hostinger admin is at /admin/. */",
       "try { if(window.CMS){CMS.boot();CMS.open();} } catch(err) {console.warn('Admin editor unavailable',err);}",$html);
    $html=str_replace('<script type="application/json" id="site-data">','<script type="application/json" id="site-data">',$html);
    $html=str_replace('</head>','<script>window.__CMS_TOKEN='.json_encode($_SESSION['csrf']).';</script></head>',$html);
    // This is a stand-alone admin application. Hide the public portfolio completely.
    // The admin editor is the original prototype's #admin overlay, mounted by its CMS module.
    $html=str_replace('</head>',
      '<style id="admin-standalone-style">body>*:not(#admin):not(#toast):not(#admin-live-publish-status):not(script):not(style){display:none!important}body{overflow:hidden!important;background:#06102a!important}html.intro-pending #intro{display:none!important}#admin{position:fixed!important;inset:0!important;display:block!important;z-index:2147483647!important;overflow:auto!important}</style></head>', $html);
    $html=str_replace('</body>',
      '<script id="admin-standalone-boot">(function(){function start(){document.body.classList.add("admin-on");document.body.classList.add("admin-standalone");try{if(!window.CMS||typeof CMS.open!=="function")throw new Error("CMS unavailable");CMS.boot();CMS.open();}catch(e){console.error("Admin startup error",e);var panel=document.getElementById("admin");if(panel){panel.textContent="Unable to load the admin editor. Please refresh the page.";}}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",start);else start();})();</script></body>', $html);

    // Publish is an admin-only action. Bind it directly to document capture so that
    // the original prototype's legacy download handler cannot swallow the click.
    $livePublishScript = <<<'HTML'
<style id="admin-publish-controls-style">
#admin [data-act="dl"]{cursor:pointer!important;pointer-events:auto!important;touch-action:manipulation}
#admin [data-act="dl"]:focus-visible{outline:3px solid #ffcc59;outline-offset:3px}
#admin [data-act="dl"][disabled]{cursor:wait!important;opacity:.75}
#admin-live-publish-status{display:none;position:fixed;right:18px;bottom:18px;z-index:2147483647;max-width:min(460px,calc(100vw - 36px));padding:16px 20px;background:#12243e;color:#fff;border:1px solid #eabf62;border-radius:14px;box-shadow:0 14px 35px rgba(0,0,0,.35);font:600 14px/1.5 system-ui,sans-serif}
#admin-live-publish-status[data-state="error"]{border-color:#e06b75}
#admin-live-publish-status[data-state="success"]{border-color:#5acb9c}
</style>
<div id="admin-live-publish-status" role="status" aria-live="polite"></div>
<script id="admin-publish-click-controller">
(function(){
  'use strict';
  var publishing=false;
  function status(message,state){
    var el=document.getElementById('admin-live-publish-status');
    if(!el)return;
    el.textContent=message;
    el.dataset.state=state||'pending';
    el.style.display='block';
  }
  function publish(){
    if(publishing)return;
    if(!window.CMS||typeof CMS.data!=='function'||typeof CMS.getPendingImages!=='function'){
      status('The editor has not loaded. Refresh this page and try again.','error');
      return;
    }
    var data=CMS.data(),images=CMS.getPendingImages();
    if(!data||!Array.isArray(data.gallery)){
      status('Content is not ready to publish. Refresh this page.','error');return;
    }
    var buttons=Array.prototype.slice.call(document.querySelectorAll('#admin [data-act="dl"]'));
    publishing=true;
    buttons.forEach(function(button){button.disabled=true;button.dataset.previousLabel=button.textContent;button.textContent='Publishing…';});
    status('Publishing your changes…','pending');
    fetch(window.location.origin+'/publish.php',{
      method:'POST',credentials:'same-origin',
      headers:{'Content-Type':'application/json','X-CSRF-Token':String(window.__CMS_TOKEN||'')},
      body:JSON.stringify({data:data,images:images})
    }).then(function(response){
      return response.text().then(function(body){
        var result;
        try{result=JSON.parse(body);}catch(e){throw Error('Unexpected server response (HTTP '+response.status+').');}
        if(!response.ok||!result.ok)throw Error(result.error||'Publish failed (HTTP '+response.status+').');
        return result;
      });
    }).then(function(){
      status('Published successfully. Open View site to see your changes.','success');
    }).catch(function(error){
      status('Could not publish: '+error.message,'error');
    }).finally(function(){
      publishing=false;
      buttons.forEach(function(button){button.disabled=false;button.textContent='✓ Publish to website';delete button.dataset.previousLabel;});
    });
  }
  // Capture before the prototype's delegated click handler; no silent download fallback.
  document.addEventListener('click',function(event){
    var button=event.target&&event.target.closest?event.target.closest('#admin [data-act="dl"]'):null;
    if(!button)return;
    event.preventDefault();
    event.stopImmediatePropagation();
    publish();
  },true);
})();
</script>
HTML;
    $html=str_replace('</body>',$livePublishScript.'</body>',$html);

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo $html;
    exit;
}

$data=allowed()?lash_data():['photos'=>[],'videos'=>[]];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Lashirah · Client CMS</title>
<style>
:root{color-scheme:dark;font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif;background:#081226;color:#eaf1ff}*{box-sizing:border-box}body{margin:0;min-height:100vh}header{padding:24px max(20px,calc((100vw - 1100px)/2));border-bottom:1px solid #263650;background:#101c32;display:flex;align-items:center;justify-content:space-between;gap:16px}h1{font-size:22px;margin:0}h2{font-size:18px}p,small{color:#adbed7}main{max-width:1100px;margin:42px auto;padding:0 20px}.panel{background:#111e35;border:1px solid #2a3c5b;border-radius:18px;padding:24px;margin-bottom:24px}.login{max-width:450px;margin:80px auto}label{display:block;margin:14px 0 6px;font-size:13px;color:#cbd9ee}input{width:100%;background:#09152a;border:1px solid #3a506f;border-radius:10px;padding:13px;color:#fff;font:inherit}button{font:inherit;border:0;border-radius:10px;padding:12px 17px;background:#d3ac64;color:#101728;cursor:pointer;font-weight:700}button.danger{background:#422437;color:#ffccd5}button.ghost{background:#263750;color:#eaf1ff}.grid{display:grid;grid-template-columns:1fr 1fr;gap:24px}.items{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px}.item{background:#0b1527;border:1px solid #283c56;border-radius:12px;overflow:hidden}.item img{width:100%;aspect-ratio:16/10;object-fit:cover}.item .info{padding:12px}.info p{overflow-wrap:anywhere;min-height:36px;margin:5px 0 12px}form.inline{display:inline}form.inline button{padding:8px 12px}.notice{padding:12px 16px;border-radius:10px;background:#173a35;color:#b8f5dd;margin-bottom:20px}.error{background:#4c2431;color:#ffd7dd}.tabs{display:flex;gap:8px;margin:20px 0}.tabs a{color:#fff;text-decoration:none;background:#263750;padding:10px 16px;border-radius:9px}@media(max-width:750px){.grid{grid-template-columns:1fr}header{padding:18px 20px}}
body:has(.login)>header{display:none}
body:has(.login)>main{max-width:none;margin:0;padding:20px;min-height:100vh;display:grid;place-items:center}
.login{text-align:center;width:min(456px,100%);max-width:456px;margin:0;border-radius:28px;padding:42px;background:#121c36}
.login-brand{font-size:24px;letter-spacing:.05em;font-weight:900;display:flex;justify-content:center;align-items:center;gap:12px}
.login-mark{width:34px;height:34px;border:4px solid #16a9e1;background:#ffce2f;box-shadow:inset 0 0 0 5px #f2445b,0 0 0 2px #364664;border-radius:50%;display:inline-block}
.login h2{font-size:42px;font-weight:900;margin:14px 0 10px}
.login>p{font-size:16px}
.login .pin-input{height:84px;border:2px solid #ffa532;border-radius:16px;letter-spacing:14px;text-align:center;font-size:27px;box-shadow:0 0 0 3px #293048}
.login button[type=submit]{width:100%;background:linear-gradient(90deg,#ffa136,#ffcb45);font-size:18px;padding:17px;margin-top:1px;border-radius:15px}
.login .back-link{display:inline-block;text-decoration:none;background:#263049;border:1px solid #3a455e;border-radius:12px;color:white;padding:12px 16px;margin-top:38px;font-weight:700}
</style></head><body><header><div><h1>LASHIRAH <span style="color:#d3ac64">/ CONTENT MANAGER</span></h1><small>Private client administration</small></div><?php if(allowed()): ?><form method="post"><input type="hidden" name="csrf" value="<?=lash_escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="logout"><button class="ghost">Sign out</button></form><?php endif ?></header><main>
<?php if(!$ready): ?><div class="panel login"><h2>Complete secure setup</h2><p>On Hostinger, create <code>admin/config.local.php</code> from <code>admin/config.example.php</code>, generate a password hash and set your admin username. No default login is provided.</p></div>
<?php elseif(!allowed()): ?><div class="panel login"><div class="login-brand"><span class="login-mark" aria-hidden="true"></span>LASHIRAH</div><h2>ADMIN</h2><p>Enter the PIN to edit the website.</p><?php if($error): ?><div class="notice error"><?=lash_escape($error)?></div><?php endif ?><form method="post"><input type="hidden" name="csrf" value="<?=lash_escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="login"><input id="client-pin" class="pin-input" name="pin" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="off" aria-label="4-digit PIN" placeholder="••••" required autofocus><p><button type="submit">Unlock</button></p></form><p><a class="back-link" href="https://lashirah.com/">← Back to the website</a></p></div>
<?php else: ?>
<p>Changes publish immediately to the website’s separate galleries.</p>
<?php if($error): ?><div class="notice error"><?=lash_escape($error)?></div><?php endif ?><?php if($success): ?><div class="notice"><?=lash_escape($success)?></div><?php endif ?>
<div class="grid"><section class="panel"><h2>Image Gallery</h2><p>Upload JPG, PNG, WebP or GIF images (8 MB maximum).</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=lash_escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="add_photo"><label>Choose image</label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif" required><label>Caption</label><input name="caption" maxlength="160"><p><button>Publish image</button></p></form></section>
<section class="panel"><h2>Video Gallery · YouTube</h2><p>Paste a YouTube watch, Shorts, share or live URL. No video file upload is needed.</p><form method="post"><input type="hidden" name="csrf" value="<?=lash_escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="add_video"><label>Video title</label><input name="title" maxlength="150" required><label>YouTube URL</label><input type="url" name="url" placeholder="https://www.youtube.com/watch?v=..." required><p><button>Publish video</button></p></form></section></div>
<section class="panel"><h2>Published images (<?=count($data['photos'])?>)</h2><div class="items"><?php foreach($data['photos'] as $item): ?><div class="item"><img loading="lazy" src="<?=lash_escape($item['url'])?>" alt="<?=lash_escape($item['caption'])?>"><div class="info"><p><?=lash_escape($item['caption'])?></p><form method="post" class="inline" onsubmit="return confirm('Delete this image?')"><input type="hidden" name="csrf" value="<?=lash_escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="type" value="photos"><input type="hidden" name="id" value="<?=lash_escape($item['id'])?>"><button class="danger">Delete</button></form></div></div><?php endforeach ?></div></section>
<section class="panel"><h2>Published YouTube videos (<?=count($data['videos'])?>)</h2><div class="items"><?php foreach($data['videos'] as $item): ?><div class="item"><img loading="lazy" src="https://i.ytimg.com/vi/<?=lash_escape($item['youtube_id'])?>/hqdefault.jpg" alt=""><div class="info"><p><?=lash_escape($item['title'])?></p><form method="post" class="inline" onsubmit="return confirm('Delete this video?')"><input type="hidden" name="csrf" value="<?=lash_escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="type" value="videos"><input type="hidden" name="id" value="<?=lash_escape($item['id'])?>"><button class="danger">Delete</button></form></div></div><?php endforeach ?></div></section>
<?php endif ?></main></body></html>
