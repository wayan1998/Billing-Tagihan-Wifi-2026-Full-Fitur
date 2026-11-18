<?php
require __DIR__.'/config.php';
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']);
session_start();
header('Content-Type: application/json; charset=utf-8');
function out($d,$c=200){http_response_code($c);echo json_encode($d,JSON_UNESCAPED_UNICODE);exit;}
try{$db=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}
catch(Exception $e){out(['err'=>'Database tidak terhubung. Periksa config.php'],500);}
const INT=['price','due_day','amount','discount','penalty','cash_received'];
const NUL=['package_id','collector_id','registered_at','paid_at','pay_method','note','collector_name'];
$M=[
 'pkg'=>['packages',['id'=>'id','n'=>'name','h'=>'price']],
 'cus'=>['customers',['id'=>'id','kode'=>'code','n'=>'name','hp'=>'phone','al'=>'address','pk'=>'package_id','st'=>'status','tg'=>'due_day','user'=>'pppoe_user','reg'=>'registered_at','pg'=>'collector_id']],
 'inv'=>['invoices',['id'=>'id','no'=>'invoice_no','cid'=>'customer_id','per'=>'period','jt'=>'due_date','b'=>'amount','ds'=>'discount','st'=>'status','dn'=>'penalty','pm'=>'pay_method','pd'=>'paid_at','nt'=>'note','by'=>'collector_name','tn'=>'cash_received']],
 'exp'=>['expenses',['id'=>'id','t'=>'spent_at','k'=>'description','j'=>'amount']]];
$in=json_decode(file_get_contents('php://input'),true)?:[];
$a=$_GET['a']??'';
function pub($u){return['id'=>$u['id'],'n'=>$u['name'],'u'=>$u['username'],'r'=>$u['role'],'p'=>''];}
function rows($db,$m,$w='',$pr=[]){$c=$m[1];$sel=[];foreach($c as $k=>$v)$sel[]="`$v` AS `$k`";
 $s=$db->prepare('SELECT '.implode(',',$sel)." FROM `{$m[0]}` $w");$s->execute($pr);$o=[];
 foreach($s as $r){foreach($r as $k=>$v)$r[$k]=$v===null?'':(in_array($c[$k],INT)?(int)$v:$v);$o[]=$r;}return $o;}
function ups($db,$m,$r){$cols=[];$vals=[];$upd=[];
 foreach($m[1] as $k=>$v){$x=$r[$k]??'';if(in_array($v,INT))$x=(int)$x;elseif($x===''&&in_array($v,NUL))$x=null;
  $cols[]="`$v`";$vals[]=$x;if($v!=='id')$upd[]="`$v`=VALUES(`$v`)";}
 $db->prepare("INSERT INTO `{$m[0]}`(".implode(',',$cols).') VALUES('.implode(',',array_fill(0,count($cols),'?')).') ON DUPLICATE KEY UPDATE '.implode(',',$upd))->execute($vals);}

function ins($db,$id,$no,$ac,$j,$u,$nt){$db->prepare('INSERT INTO payment_log(invoice_id,invoice_no,action,amount,user_name,note) VALUES(?,?,?,?,?,?)')->execute([$id,$no,$ac,$j,$u,$nt?:null]);}
function lg($db,$me,$r){$s=$db->prepare('SELECT invoice_no,status,amount,discount,penalty FROM invoices WHERE id=?');$s->execute([$r['id']]);$o=$s->fetch();$n=($r['st']??'')==='lunas';
 if($o&&$o['status']==='lunas'&&!$n)ins($db,$r['id'],$o['invoice_no'],'batal',$o['amount']-$o['discount']+$o['penalty'],$me['name'],$r['nt']??'');
 elseif((!$o||$o['status']!=='lunas')&&$n)ins($db,$r['id'],$r['no']??'','bayar',(int)($r['b']??0)-(int)($r['ds']??0)+(int)($r['dn']??0),$me['name'],$r['nt']??'');}
if($a==='login'){
 $u=trim(strtolower($in['u']??''));$p=$in['p']??'';
 if(!$db->query('SELECT 1 FROM users LIMIT 1')->fetch()){
  if(strlen($u)<3||strlen($p)<6)out(['err'=>'Akun admin pertama: username min 3 dan password min 6 karakter'],400);
  $id=bin2hex(random_bytes(5));
  $db->prepare("INSERT INTO users(id,name,username,password_hash,role) VALUES(?,?,?,?,'admin')")->execute([$id,'Administrator',$u,password_hash($p,PASSWORD_DEFAULT)]);
  $_SESSION['uid']=$id;out(['ok'=>1]);}
 $s=$db->prepare('SELECT * FROM users WHERE username=?');$s->execute([$u]);$r=$s->fetch();
 if(!$r||!password_verify($p,$r['password_hash'])){sleep(1);out(['err'=>'Username atau password salah'],401);}
 session_regenerate_id(true);$_SESSION['uid']=$r['id'];out(['ok'=>1]);}
if($a==='logout'){session_destroy();out(['ok'=>1]);}

$me=null;
if(!empty($_SESSION['uid'])){$s=$db->prepare('SELECT * FROM users WHERE id=?');$s->execute([$_SESSION['uid']]);$me=$s->fetch();}
if(!$me)out(['err'=>'Silakan login'],401);
$adm=$me['role']==='admin';
$f='(collector_id=? OR collector_id IS NULL)';

if($a==='all'){
 $S=['u'=>pub($me),'cfg'=>[],'seq'=>0,'pkg'=>rows($db,$M['pkg']),'cus'=>[],'inv'=>[],'exp'=>[],'users'=>[],'log'=>[]];
 foreach($db->query('SELECT k,v FROM settings') as $r){
  if($r['k']==='seq')$S['seq']=(int)$r['v'];
  else $S['cfg'][$r['k']]=in_array($r['k'],['denda','maxd','pw'])?(int)$r['v']:$r['v'];}
 if($adm){$S['cus']=rows($db,$M['cus']);$S['inv']=rows($db,$M['inv']);$S['exp']=rows($db,$M['exp']);
  foreach($db->query('SELECT created_at t,action a,invoice_no `no`,amount j,user_name u,note nt FROM payment_log ORDER BY id DESC LIMIT 60') as $r)$S['log'][]=$r;
  foreach($db->query('SELECT * FROM users ORDER BY name') as $r)$S['users'][]=pub($r);}
 else{$S['cus']=rows($db,$M['cus'],"WHERE $f",[$me['id']]);
  $S['inv']=rows($db,$M['inv'],"WHERE customer_id IN (SELECT id FROM customers WHERE $f)",[$me['id']]);}
 out($S);}

if($a==='sync'){
 $up=$in['up']??[];$del=$in['del']??[];
 $db->beginTransaction();
 try{
  if($adm){
   foreach(($in['cfg']??[]) as $k=>$v)if(in_array($k,['nama','alamat','wa','rek','denda','maxd','pw','tpl']))
    $db->prepare('INSERT INTO settings(k,v) VALUES(?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)')->execute([$k,(string)$v]);
   if(isset($in['seq']))$db->prepare("INSERT INTO settings(k,v) VALUES('seq',?) ON DUPLICATE KEY UPDATE v=GREATEST(CAST(v AS UNSIGNED),CAST(VALUES(v) AS UNSIGNED))")->execute([(int)$in['seq']]);
   foreach(['inv','cus','pkg','exp'] as $k)foreach(($del[$k]??[]) as $id)$db->prepare("DELETE FROM `{$M[$k][0]}` WHERE id=?")->execute([$id]);
   foreach(['pkg','cus','inv','exp'] as $k)foreach(($up[$k]??[]) as $r){if($k==='inv')lg($db,$me,$r);ups($db,$M[$k],$r);}
   foreach(($up['users']??[]) as $r){
    $un=strtolower(trim($r['u']??''));$role=($r['r']??'')==='admin'?'admin':'penagih';$pw=$r['p']??'';
    $e=$db->prepare('SELECT id FROM users WHERE id=?');$e->execute([$r['id']]);
    if($e->fetch()){$pa=[$r['n'],$un,$role];if($pw!=='')$pa[]=password_hash($pw,PASSWORD_DEFAULT);$pa[]=$r['id'];
     $db->prepare('UPDATE users SET name=?,username=?,role=?'.($pw!==''?',password_hash=?':'').' WHERE id=?')->execute($pa);}
    elseif($pw!=='')$db->prepare('INSERT INTO users(id,name,username,password_hash,role) VALUES(?,?,?,?,?)')->execute([$r['id'],$r['n'],$un,password_hash($pw,PASSWORD_DEFAULT),$role]);}
   foreach(($del['users']??[]) as $id)if($id!==$me['id'])$db->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
  }else{
   // penagih: mencatat pembayaran dan membatalkan pembayaran miliknya sendiri; semua tercatat di payment_log
   foreach(($up['inv']??[]) as $r){
    $s=$db->prepare("SELECT i.invoice_no,i.status,i.amount,i.discount,i.penalty,i.collector_name FROM invoices i JOIN customers c ON c.id=i.customer_id WHERE i.id=? AND (c.collector_id=? OR c.collector_id IS NULL)");
    $s->execute([$r['id'],$me['id']]);$o=$s->fetch();if(!$o)continue;$st=$r['st']??'';
    if($st==='lunas'&&$o['status']!=='lunas'){
     $ds=(int)($r['ds']??0);$dn=(int)($r['dn']??0);
     $db->prepare("UPDATE invoices SET discount=?,status='lunas',penalty=?,pay_method=?,paid_at=?,note=?,collector_name=?,cash_received=? WHERE id=?")
      ->execute([$ds,$dn,($r['pm']??'')?:null,($r['pd']??'')?:null,($r['nt']??'')?:null,$me['name'],(int)($r['tn']??0),$r['id']]);
     ins($db,$r['id'],$o['invoice_no'],'bayar',$o['amount']-$ds+$dn,$me['name'],$r['nt']??'');
    }elseif($st==='belum'&&$o['status']==='lunas'&&$o['collector_name']===$me['name']){
     $db->prepare("UPDATE invoices SET status='belum',discount=0,penalty=0,pay_method=NULL,paid_at=NULL,cash_received=0,collector_name=NULL,note=? WHERE id=?")->execute([($r['nt']??'')?:null,$r['id']]);
     ins($db,$r['id'],$o['invoice_no'],'batal',$o['amount']-$o['discount']+$o['penalty'],$me['name'],$r['nt']??'');
    }
   }
  }
  $db->commit();out(['ok'=>1]);
 }catch(Exception $e){$db->rollBack();out(['err'=>'Gagal menyimpan: '.$e->getMessage()],409);}}

if($a==='dump'&&$adm){
 header('Content-Type: application/sql; charset=utf-8');header('Content-Disposition: attachment; filename=dwinet-'.date('Ymd').'.sql');
 foreach(['settings','packages','users','customers','invoices','expenses'] as $t)foreach($db->query("SELECT * FROM `$t`") as $r)
  echo "INSERT INTO `$t` (`".implode('`,`',array_keys($r))."`) VALUES (".implode(',',array_map(function($v)use($db){return $v===null?'NULL':$db->quote($v);},$r)).");\n";
 exit;}
out(['err'=>'Tidak dikenal'],404);
