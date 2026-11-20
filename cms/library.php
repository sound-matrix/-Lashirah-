<?php
declare(strict_types=1);
const LASHIRAH_STORAGE = __DIR__ . '/../storage/gallery.json';
function lash_data(): array {
    if (!is_file(LASHIRAH_STORAGE)) return ['photos'=>[], 'videos'=>[]];
    $data = json_decode((string) file_get_contents(LASHIRAH_STORAGE), true);
    return is_array($data) ? ['photos'=>is_array($data['photos']??null)?$data['photos']:[], 'videos'=>is_array($data['videos']??null)?$data['videos']:[]] : ['photos'=>[], 'videos'=>[]];
}
function lash_save(array $data): void {
    $dir=dirname(LASHIRAH_STORAGE);
    if (!is_dir($dir) && !mkdir($dir,0750,true) && !is_dir($dir)) throw new RuntimeException('Cannot create storage folder');
    $json=json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $fp=fopen(LASHIRAH_STORAGE,'c+');
    if (!$fp) throw new RuntimeException('Cannot save gallery');
    try { if (!flock($fp,LOCK_EX)) throw new RuntimeException('Gallery locked'); ftruncate($fp,0);rewind($fp);fwrite($fp,$json);fflush($fp);flock($fp,LOCK_UN); } finally { fclose($fp); }
}
function lash_youtube_id(string $url): ?string {
    $parts=parse_url(trim($url));
    if (!$parts || !in_array(strtolower($parts['scheme']??''),['https','http'],true)) return null;
    $host=strtolower($parts['host']??''); $host=preg_replace('/^www\./','',$host);
    $path=trim($parts['path']??'','/'); $id=null;
    if ($host==='youtu.be') $id=explode('/',$path)[0];
    elseif (in_array($host,['youtube.com','m.youtube.com','music.youtube.com','youtube-nocookie.com'],true)) {
        if ($path==='watch') {parse_str($parts['query']??'',$q);$id=$q['v']??null;}
        elseif (preg_match('~^(?:shorts|embed|live)/([^/]+)~',$path,$m)) $id=$m[1];
    }
    return is_string($id)&&preg_match('/^[A-Za-z0-9_-]{11}$/D',$id)?$id:null;
}
function lash_escape(string $s): string {return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
