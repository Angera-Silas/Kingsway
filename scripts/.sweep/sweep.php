<?php
$lines = file(__DIR__.'/endpoints.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$ctx = stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\n",'content'=>json_encode(['username'=>'test_headteacher','password'=>'Pass123!@']),'ignore_errors'=>true]]);
$login = json_decode(file_get_contents('https://localhost/Kingsway/api/auth/login', false, $ctx), true);
$token = $login['data']['token'] ?? '';
$csrf = $login['data']['csrf_token'] ?? '';
if (!$token) { fwrite(STDERR, "LOGIN FAILED\n"); exit(1); }
$BASE='https://127.0.0.1/Kingsway/api';
$CONC=25;
$tests=[];
foreach ($lines as $line) {
    if (!trim($line)) continue;
    $parts=explode(' ', $line, 2);
    $m=$parts[0]; $ep=$parts[1];
    $ep=preg_replace('#/X$#','',preg_replace('#X#','1',$ep));
    $url=$BASE.$ep;
    $ch=curl_init($url);
    $headers=["Authorization: Bearer $token","Accept: application/json","Host: localhost"];
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>$headers]);
    if($m==='POST'){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,'{}');curl_setopt($ch,CURLOPT_HTTPHEADER,array_merge($headers,['Content-Type: application/json','X-CSRF-Token: '.$csrf]));}
    elseif($m==='PUT'){curl_setopt($ch,CURLOPT_CUSTOMREQUEST,'PUT');curl_setopt($ch,CURLOPT_POSTFIELDS,'{}');curl_setopt($ch,CURLOPT_HTTPHEADER,array_merge($headers,['Content-Type: application/json','X-CSRF-Token: '.$csrf]));}
    elseif($m==='DELETE'){curl_setopt($ch,CURLOPT_CUSTOMREQUEST,'DELETE');curl_setopt($ch,CURLOPT_HTTPHEADER,array_merge($headers,['X-CSRF-Token: '.$csrf]));}
    else { curl_setopt($ch,CURLOPT_NOBODY,true); }
    $tests[]=[$ch,$m,$ep];
}
$mh=curl_multi_init();
$pending=$tests;
$inFlight=[];
$out=[];
$next=function() use (&$pending,&$inFlight,&$mh){ if(count($pending)){ $item=array_shift($pending); $inFlight[]=$item; curl_multi_add_handle($mh,$item[0]); } };
for($i=0;$i<$CONC;$i++) $next();
do {
    do { $status=curl_multi_exec($mh,$active); } while ($status===CURLM_CALL_MULTI_PERFORM);
    while($info=curl_multi_info_read($mh)){
        $ch=$info['handle'];
        $code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        foreach($inFlight as $k=>$item){ if($item[0]===$ch){ $out[]=$code.' '.$item[1].' '.$item[2]; unset($inFlight[$k]); curl_multi_remove_handle($mh,$ch); curl_close($ch); $next(); break; } }
    }
    if($active>0) curl_multi_select($mh,1.0);
} while($active>0 || count($pending) || count($inFlight));
curl_multi_close($mh);
sort($out);
file_put_contents(__DIR__.'/final_results.txt', implode("\n",$out)."\n");
echo "DONE ".count($out)."\n";
