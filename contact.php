<?php
declare(strict_types=1);
// Private recipient: never sent to the visitor's browser.
const CONTACT_RECIPIENT = 'nandavaramhassan@gmail.com';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
function respond(int $status,string $message): never {
    http_response_code($status);
    echo json_encode(['ok'=>$status===200,'message'=>$message], JSON_UNESCAPED_SLASHES);
    exit;
}
if (($_SERVER['REQUEST_METHOD']??'') !== 'POST') respond(405,'Method not allowed');
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if ($origin!==''&&!in_array($origin,['https://lashirah.com','https://www.lashirah.com'],true)) respond(403,'Invalid request origin');
if ((int)($_SERVER['CONTENT_LENGTH']??0)>12000)respond(413,'Message is too large');
$input=json_decode((string)file_get_contents('php://input'),true);
if (!is_array($input))respond(400,'Invalid request');
if (trim((string)($input['website']??''))!=='')respond(200,'Thank you for your message.');
$name=trim((string)($input['name']??''));
$email=trim((string)($input['email']??''));
$phone=trim((string)($input['phone']??''));
$about=trim((string)($input['about']??''));
$tier=trim((string)($input['tier']??''));
$message=trim((string)($input['message']??''));
if ($name===''||mb_strlen($name)>120||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254||mb_strlen($phone)>45||mb_strlen($about)>80||mb_strlen($tier)>150||$message===''||mb_strlen($message)>4000)respond(422,'Please check your details and message.');
foreach([$name,$email,$phone,$about,$tier] as $value){if(preg_match('/[\r\n\x00-\x1F\x7F]/',$value))respond(422,'Invalid form field.');}
$allowed=['Sponsorship','General enquiry','Media','Press','Training','Other','Collaboration','Support','Partnership'];
if($about!=='' && !in_array($about,$allowed,true)){
    // A custom option can be used in the prototype; it is displayed as plain text only.
    if(preg_match('/[<>]/',$about))respond(422,'Invalid enquiry category.');
}
$base=__DIR__.'/storage';
if(!is_dir($base)&&!mkdir($base,0750,true))respond(503,'The form is temporarily unavailable.');
$ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');
$key=hash('sha256',$ip);
$statePath=$base.'/contact-limit-'.$key.'.json';
$now=time();
$fp=fopen($statePath,'c+');
if(!$fp)respond(503,'The form is temporarily unavailable.');
if(!flock($fp,LOCK_EX)){fclose($fp);respond(503,'The form is temporarily unavailable.');}
$old=json_decode((string)stream_get_contents($fp),true);
$events=array_values(array_filter(is_array($old)?$old:[],static fn($t):bool=>is_numeric($t)&&(int)$t>$now-3600));
if(count($events)>=5){flock($fp,LOCK_UN);fclose($fp);respond(429,'Too many messages. Please try again later.');}
$events[]=$now;
rewind($fp);ftruncate($fp,0);fwrite($fp,json_encode($events));fflush($fp);flock($fp,LOCK_UN);fclose($fp);
@chmod($statePath,0600);
$subject='Lashirah website enquiry: '.($about?:'General').' from '.$name;
$body="New enquiry from lashirah.com\n\n".
      "Name: $name\nEmail: $email\nPhone: ".($phone?:'-')."\n".
      "Enquiry: ".($about?:'General')."\nPackage: ".($tier?:'-')."\n\n".
      "Message:\n$message\n";
$headers=['From'=>'Lashirah Website <no-reply@lashirah.com>','Reply-To'=>$email,'Content-Type'=>'text/plain; charset=UTF-8','X-Mailer'=>'Lashirah Contact Form'];
if(!function_exists('mail')||!@mail(CONTACT_RECIPIENT,$subject,$body,$headers)){
    error_log('Lashirah contact: mail submission failed');
    respond(503,'Your message could not be submitted. Please try again later.');
}
respond(200,'Your message has been submitted. Thank you!');
