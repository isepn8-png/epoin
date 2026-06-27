<?php
// admin/pengaturan_petugas_absensi.php — v3.8 embed-aware

$EMBED = defined('EMBED_TABS'); // true jika dipanggil dari manajemen_pengguna.php

$DEBUG = isset($_GET['debug']) && $_GET['debug']=='1';
if ($DEBUG) { ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL); }

if (!$EMBED) include 'header.php';
include '../koneksi.php';
require_once __DIR__ . '/../includes/auth.php';

if (function_exists('ensure_logged_in')) ensure_logged_in();
if (function_exists('user_has_any_role') && !user_has_any_role(['administrator','superadmin','tas'])) {
  http_response_code(403);
  echo "<section class='content'><div class='callout callout-danger'>403: Anda tidak berhak membuka halaman ini.</div></section>";
  if (!$EMBED) include 'footer.php'; exit;
}

/* CSRF fallback */
if (!function_exists('csrf_token')) {
  function csrf_token(){ if (session_status()===PHP_SESSION_NONE) session_start(); if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
}
if (!function_exists('csrf_check')) {
  function csrf_check($t){ if (session_status()===PHP_SESSION_NONE) session_start(); return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$t); }
}

mysqli_set_charset($koneksi,'utf8mb4');

/* Helpers */
$DBG=[];
function esc($s){ global $koneksi; return mysqli_real_escape_string($koneksi,(string)$s); }
function i($v){ return (int)$v; }
function one($sql){ global $koneksi,$DBG; $DBG[]=['sql'=>$sql]; $r=mysqli_query($koneksi,$sql); if($r===false){ $DBG[count($DBG)-1]['err']=mysqli_error($koneksi);} return $r?mysqli_fetch_assoc($r):null; }
function qr($sql){ global $koneksi,$DBG; $DBG[]=['sql'=>$sql]; $r=mysqli_query($koneksi,$sql); if($r===false){ $DBG[count($DBG)-1]['err']=mysqli_error($koneksi);} return $r; }

/* Akun staf helper */
function find_or_create_user_for_siswa($siswa_id){
  $s=one("SELECT siswa_id,siswa_nis,siswa_nama,siswa_password FROM siswa WHERE siswa_id=".i($siswa_id)." LIMIT 1");
  if(!$s) return [false,"Data siswa #$siswa_id tidak ditemukan"];
  $u=one("SELECT user_id,user_username FROM `user` WHERE linked_siswa_id=".i($s['siswa_id'])." LIMIT 1");
  if($u) return [(int)$u['user_id'],"OK (akun staf sudah ada: ".$u['user_username'].")"];
  $uname=$s['siswa_nis'];
  $cek=one("SELECT user_id FROM `user` WHERE user_username='".esc($uname)."' LIMIT 1");
  if($cek) $uname.='-sek';
  $ok=qr(sprintf(
    "INSERT INTO `user`(user_nama,user_username,user_password,user_foto,user_level,linked_siswa_id,status_login)
     VALUES('%s','%s','%s','', 'sekretaris', %d, 'offline')",
     esc($s['siswa_nama']), esc($uname), esc($s['siswa_password']), (int)$s['siswa_id']
  ));
  if(!$ok) return [false,"Gagal membuat akun staf."];
  global $koneksi; return [(int)mysqli_insert_id($koneksi),"OK (akun staf baru: {$uname})"];
}
function role_id($key){ $r=one("SELECT role_id FROM roles WHERE role_key='".esc(strtolower($key))."' LIMIT 1"); return $r?(int)$r['role_id']:0; }
function assign_role($uid,$key){ $rid=role_id($key); if(!$rid) return false; $r=qr("INSERT IGNORE INTO user_roles(user_id,role_id) VALUES(".i($uid).",$rid)"); if($r && function_exists('epoin_rbac_bump_version')) epoin_rbac_bump_version(); return $r; }
function revoke_role($uid,$key){ $rid=role_id($key); if(!$rid) return false; $r=qr("DELETE FROM user_roles WHERE user_id=".i($uid)." AND role_id=$rid"); if($r && function_exists('epoin_rbac_bump_version')) epoin_rbac_bump_version(); return $r; }
function sync_password_from_siswa($uid){
  $u=one("SELECT linked_siswa_id FROM `user` WHERE user_id=".i($uid)." LIMIT 1");
  if(!$u||!$u['linked_siswa_id']) return [false,"Akun staf tidak tertaut ke siswa"];
  $s=one("SELECT siswa_password FROM siswa WHERE siswa_id=".i($u['linked_siswa_id'])." LIMIT 1");
  if(!$s) return [false,"Data siswa tidak ditemukan"];
  $ok=qr("UPDATE `user` SET user_password='".esc($s['siswa_password'])."' WHERE user_id=".i($uid));
  return $ok?[true,"Password staf disamakan dengan password siswa."]:[false,"Gagal sinkron password."];
}

/* Actions */
$flash=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!csrf_check($_POST['csrf']??'')){ $flash=['type'=>'danger','msg'=>'CSRF token tidak valid.']; }
  else{
    $act=$_POST['act']??'';
    if($act==='promote_sekretaris'||$act==='promote_piket'){
      $sid=i($_POST['siswa_id']??0); [$uid,$m]=find_or_create_user_for_siswa($sid);
      $flash=!$uid?['type'=>'danger','msg'=>$m]:(assign_role($uid,$act==='promote_sekretaris'?'sekretaris':'piket')
        ?['type'=>'success','msg'=>"Berhasil set <b>".($act==='promote_sekretaris'?'Sekretaris':'Piket')."</b>. $m"]
        :['type'=>'danger','msg'=>'Gagal assign role.']);
    }elseif($act==='revoke_sekretaris'||$act==='revoke_piket'){
      $uid=i($_POST['user_id']??0);
      $flash=($uid&&revoke_role($uid,$act==='revoke_sekretaris'?'sekretaris':'piket'))
        ?['type'=>'success','msg'=>'Role dicabut.']:['type'=>'danger','msg'=>'Gagal mencabut role.'];
    }elseif($act==='sync_pass'){
      $uid=i($_POST['user_id']??0); [$ok,$m]=sync_password_from_siswa($uid); $flash=['type'=>$ok?'success':'danger','msg'=>$m];
    }elseif(in_array($act,['bulk_promote_sekretaris','bulk_promote_piket','bulk_revoke_sekretaris','bulk_revoke_piket','bulk_sync_pass'],true)){
      $ids=array_values(array_unique(array_map('i',(array)($_POST['selected']??[])))); $ok=0;$er=0;
      if(!$ids){ $flash=['type'=>'warning','msg'=>'Tidak ada siswa yang dipilih.']; }
      else foreach($ids as $sid){
        if($act==='bulk_promote_sekretaris'||$act==='bulk_promote_piket'){
          [$uid,$m]=find_or_create_user_for_siswa($sid);
          if($uid){ if(assign_role($uid,$act==='bulk_promote_sekretaris'?'sekretaris':'piket')) $ok++; else $er++; } else $er++;
        }elseif($act==='bulk_sync_pass'){
          $u=one("SELECT user_id FROM `user` WHERE linked_siswa_id={$sid} LIMIT 1");
          if($u){ [$okx,$mx]=sync_password_from_siswa((int)$u['user_id']); $ok+=$okx?1:0; $er+=(!$okx?1:0);} else $er++;
        }else{
          $u=one("SELECT user_id FROM `user` WHERE linked_siswa_id={$sid} LIMIT 1");
          if($u){ if(revoke_role((int)$u['user_id'],$act==='bulk_revoke_sekretaris'?'sekretaris':'piket')) $ok++; else $er++; } else $er++;
        }
      }
      $flash=['type'=>$er?'warning':'success','msg'=>"Proses massal: <b>{$ok} OK</b>, <b>{$er} gagal</b>."];
    }
  }
}

/* Filters */
$kelas_id=i($_GET['kelas']??0);
$status=$_GET['status']??'all';
$q=trim((string)($_GET['q']??''));
$page=max(1,i($_GET['page']??1));
$pp=min(200,max(10,i($_GET['pp']??25)));

$kelas_rs=qr("SELECT kelas_id,kelas_nama FROM kelas ORDER BY kelas_nama ASC");

/* Base FROM */
$from="
FROM siswa s
LEFT JOIN (SELECT ks_siswa,MAX(ks_id) max_ks FROM kelas_siswa GROUP BY ks_siswa) mk ON mk.ks_siswa=s.siswa_id
LEFT JOIN kelas_siswa ks ON ks.ks_id=mk.max_ks
LEFT JOIN kelas k ON k.kelas_id=ks.ks_kelas
LEFT JOIN `user` u ON u.linked_siswa_id=s.siswa_id
LEFT JOIN (
  SELECT ur.user_id, MAX(r.role_key='sekretaris') is_sek, MAX(r.role_key='piket') is_pik
  FROM user_roles ur JOIN roles r ON r.role_id=ur.role_id GROUP BY ur.user_id
) rr ON rr.user_id=u.user_id
WHERE 1=1";
if($kelas_id>0) $from.=" AND ks.ks_kelas={$kelas_id}";
if($q!==''){ $qesc=esc($q); $from.=" AND (s.siswa_nis LIKE '%{$qesc}%' OR s.siswa_nama LIKE '%{$qesc}%' OR k.kelas_nama LIKE '%{$qesc}%')"; }
if($status==='sek')   $from.=" AND COALESCE(rr.is_sek,0)=1";
elseif($status==='piket') $from.=" AND COALESCE(rr.is_pik,0)=1";
elseif($status==='none')  $from.=" AND COALESCE(rr.is_sek,0)=0 AND COALESCE(rr.is_pik,0)=0";

/* Ringkasan */
$cnt=one("SELECT COUNT(*) total,
  SUM(COALESCE(rr.is_sek,0)) cntSek,
  SUM(COALESCE(rr.is_pik,0)) cntPik,
  SUM(CASE WHEN COALESCE(rr.is_sek,0)=0 AND COALESCE(rr.is_pik,0)=0 THEN 1 ELSE 0 END) cntNone
  {$from}");
$total=(int)($cnt['total']??0);
$cntSek=(int)($cnt['cntSek']??0);
$cntPik=(int)($cnt['cntPik']??0);
$cntNone=(int)($cnt['cntNone']??0);

/* List */
$offset=($page-1)*$pp;
$sqlList="SELECT s.siswa_id,s.siswa_nis,s.siswa_nama,k.kelas_nama,ks.ks_kelas kelas_id,
                 u.user_id,u.user_username, COALESCE(rr.is_sek,0) is_sekretaris, COALESCE(rr.is_pik,0) is_piket
          {$from} ORDER BY k.kelas_nama, s.siswa_nama LIMIT {$pp} OFFSET {$offset}";
$rs=qr($sqlList); $rows=[]; if($rs) while($r=mysqli_fetch_assoc($rs)) $rows[]=$r;

/* Self URL (reset/pagination) */
$baseSelf = $EMBED ? 'manajemen_pengguna.php?tab=sekretaris' : 'pengaturan_petugas_absensi.php';
$pages=max(1,(int)ceil($total/$pp));
?>
<style>
.table thead th{position:sticky;top:0;background:#12263c;color:#f0f0f0;z-index:2}
.table>tbody>tr>td,.table>thead>tr>th{color:#e6e6e6}
.table-striped>tbody>tr:nth-of-type(odd){background:rgba(255,255,255,.03)}
.small-box .inner h3{font-weight:700}
.badge.bg-green{background:#00a65a!important}.badge.bg-aqua{background:#00c0ef!important}.badge.bg-gray{background:#777!important}
td .btn{margin-bottom:3px}.pagination{margin:8px 0 0}
.box .box-title .label{font-size:12px;margin-left:6px}
</style>

<?php if(!$EMBED): ?>
<section class="content-header">
  <h1><i class="fa fa-id-badge"></i> Pengaturan Petugas Absensi
    <small>Sekretaris & Guru Piket (berbasis Siswa)</small>
  </h1>
</section>
<section class="content">
<?php endif; ?>

  <?php if(!empty($flash)): ?>
    <div class="alert alert-<?=htmlspecialchars($flash['type'])?> alert-dismissible">
      <button type="button" class="close" data-dismiss="alert">&times;</button><?= $flash['msg'] ?>
    </div>
  <?php endif; ?>

  <?php if($DEBUG && !empty($DBG)): ?>
    <div class="callout callout-info">
      <b>DEBUG SQL</b>
      <ul style="margin:6px 0 0 16px">
        <?php foreach($DBG as $d): ?>
          <li><code><?=htmlspecialchars($d['sql'])?></code><?= !empty($d['err'])? " <span class='text-red'>&rarr; ".htmlspecialchars($d['err'])."</span>" : "" ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="row" id="toolbar">
    <div class="col-md-8">
      <div class="box box-default"><div class="box-body">
        <form class="form-inline" method="get" action="<?=$baseSelf?>">
          <label for="kelas" style="margin-right:8px">Kelas:</label>
          <select name="kelas" id="kelas" class="form-control">
            <option value="0">Semua</option>
            <?php mysqli_data_seek($kelas_rs,0); while($k=mysqli_fetch_assoc($kelas_rs)): ?>
              <option value="<?=$k['kelas_id']?>" <?=$kelas_id==$k['kelas_id']?'selected':''?>><?=htmlspecialchars($k['kelas_nama'])?></option>
            <?php endwhile; ?>
          </select>

          <div class="input-group" style="margin-left:8px;max-width:260px;">
            <input type="text" name="q" value="<?=htmlspecialchars($q)?>" class="form-control" placeholder="Cari NIS/Nama/Kelas…">
            <span class="input-group-btn">
              <button class="btn btn-default" type="button" onclick="this.closest('form').q.value='';this.closest('form').submit();"><i class="fa fa-times"></i></button>
            </span>
          </div>

          <div class="btn-group" data-toggle="buttons" style="margin-left:8px">
            <?php $opts=['all'=>'Semua','sek'=>'Sekretaris','piket'=>'Piket','none'=>'Belum Ditugaskan'];
              foreach($opts as $v=>$lbl){ $act=$status===$v?'active':''; $chk=$status===$v?'checked':''; echo "<label class='btn btn-default {$act}'><input type='radio' name='status' value='{$v}' {$chk}> {$lbl}</label>"; } ?>
          </div>

          <button class="btn btn-primary" style="margin-left:8px"><i class="fa fa-search"></i> Terapkan</button>
          <?php if ($kelas_id>0 || $q!=='' || $status!=='all'): ?>
            <a class="btn btn-default" href="<?=$baseSelf?>"><i class="fa fa-refresh"></i> Reset</a>
          <?php endif; ?>
        </form>
        <small class="text-muted">Menampilkan <b><?=count($rows)?></b> dari <b><?=$total?></b> siswa.</small>
      </div></div>
    </div>

    <div class="col-md-4">
      <div class="row">
        <div class="col-xs-3"><div class="small-box bg-aqua"><div class="inner"><h3><?=$total?></h3><p>Siswa</p></div><div class="icon"><i class="fa fa-users"></i></div></div></div>
        <div class="col-xs-3"><div class="small-box bg-green"><div class="inner"><h3><?=$cntSek?></h3><p>Sekretaris</p></div><div class="icon"><i class="fa fa-id-badge"></i></div></div></div>
        <div class="col-xs-3"><div class="small-box bg-aqua"><div class="inner"><h3><?=$cntPik?></h3><p>Piket</p></div><div class="icon"><i class="fa fa-bell"></i></div></div></div>
        <div class="col-xs-3"><div class="small-box bg-gray"><div class="inner"><h3><?=$cntNone?></h3><p>Belum</p></div><div class="icon"><i class="fa fa-minus-circle"></i></div></div></div>
      </div>
    </div>
  </div>

  <!-- Aksi Massal -->
  <div class="box box-success">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-bolt"></i> Aksi Massal <span class="label label-default">centang baris dulu</span></h3>
      <div class="box-tools">
        <form method="get" class="form-inline" action="<?=$baseSelf?>">
          <input type="hidden" name="kelas" value="<?=$kelas_id?>"><input type="hidden" name="status" value="<?=$status?>">
          <input type="hidden" name="q" value="<?=htmlspecialchars($q)?>">
          <label>Baris/hal:</label>
          <select name="pp" class="form-control input-sm" onchange="this.form.submit()">
            <?php foreach([10,25,50,100,200] as $v): ?><option value="<?=$v?>" <?=$pp==$v?'selected':''?>><?=$v?></option><?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>
    <div class="box-body">
      <form id="bulkForm" method="post" onsubmit="return confirmBulk();">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="act" id="bulkAct" value="">
        <div class="btn-group">
          <button type="button" class="btn btn-success" onclick="setBulkAct('bulk_promote_sekretaris')"><i class="fa fa-id-badge"></i> Jadikan Sekretaris</button>
          <button type="button" class="btn btn-info" onclick="setBulkAct('bulk_promote_piket')"><i class="fa fa-bell"></i> Jadikan Piket</button>
          <button type="button" class="btn btn-warning" onclick="setBulkAct('bulk_revoke_sekretaris')"><i class="fa fa-times-circle"></i> Cabut Sekretaris</button>
          <button type="button" class="btn btn-default" onclick="setBulkAct('bulk_revoke_piket')"><i class="fa fa-times"></i> Cabut Piket</button>
          <button type="button" class="btn btn-danger" onclick="setBulkAct('bulk_sync_pass')"><i class="fa fa-refresh"></i> Reset Password = Siswa</button>
        </div>
        <small class="text-muted" style="margin-left:8px">Tips: filter “Belum Ditugaskan” lalu batch assign.</small>
        <div id="bulkSelected"></div>
      </form>
    </div>
  </div>

  <!-- Tabel -->
  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-list"></i> Daftar Siswa</h3>
      <div class="box-tools">
        <button class="btn btn-default btn-sm" onclick="toggleAll(true)"><i class="fa fa-check-square-o"></i> Pilih Semua</button>
        <button class="btn btn-default btn-sm" onclick="toggleAll(false)"><i class="fa fa-square-o"></i> Bersihkan</button>
      </div>
    </div>
    <div class="box-body table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr>
            <th style="width:40px"><input type="checkbox" id="chkAll" onclick="toggleAll(this.checked)"></th>
            <th style="width:120px">Kelas</th>
            <th style="width:120px">NIS</th>
            <th>Nama Siswa</th>
            <th style="width:220px">Akun Staf</th>
            <th>Status</th>
            <th style="width:420px">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows): foreach($rows as $r):
            $hasUsr=!empty($r['user_id']); $isSek=(int)$r['is_sekretaris']; $isPik=(int)$r['is_piket']; ?>
          <tr>
            <td><input type="checkbox" class="chkRow" data-siswa="<?=$r['siswa_id']?>"></td>
            <td><span class="label label-info"><?= $r['kelas_nama'] ?: '-' ?></span></td>
            <td><code><?= htmlspecialchars($r['siswa_nis']) ?></code></td>
            <td><?= htmlspecialchars($r['siswa_nama']) ?></td>
            <td>
              <?php if ($hasUsr): ?>
                <span class="text-success"><i class="fa fa-user"></i> <?= htmlspecialchars($r['user_username']) ?></span>
                <small class="text-muted"> (ID: <?= (int)$r['user_id'] ?>)</small>
              <?php else: ?><span class="text-muted"><i class="fa fa-user-o"></i> belum dibuat</span><?php endif; ?>
            </td>
            <td>
              <?php if ($isSek): ?><span class="badge bg-green">Sekretaris</span> <?php endif; ?>
              <?php if ($isPik): ?><span class="badge bg-aqua">Piket</span> <?php endif; ?>
              <?php if (!$isSek && !$isPik): ?><span class="badge bg-gray">Belum</span><?php endif; ?>
            </td>
            <td>
              <form method="post" class="form-inline" style="display:inline">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="siswa_id" value="<?= (int)$r['siswa_id'] ?>">
                <?php if (!$isSek): ?>
                  <button name="act" value="promote_sekretaris" class="btn btn-success btn-sm"><i class="fa fa-id-badge"></i> Jadikan Sekretaris</button>
                <?php else: ?>
                  <input type="hidden" name="user_id" value="<?= (int)$r['user_id'] ?>">
                  <button name="act" value="revoke_sekretaris" class="btn btn-warning btn-sm" onclick="return confirm('Cabut role Sekretaris?');"><i class="fa fa-times-circle"></i> Cabut Sekretaris</button>
                <?php endif; ?>
              </form>

              <form method="post" class="form-inline" style="display:inline">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="siswa_id" value="<?= (int)$r['siswa_id'] ?>">
                <?php if (!$isPik): ?>
                  <button name="act" value="promote_piket" class="btn btn-info btn-sm"><i class="fa fa-bell"></i> Jadikan Piket</button>
                <?php else: ?>
                  <input type="hidden" name="user_id" value="<?= (int)$r['user_id'] ?>">
                  <button name="act" value="revoke_piket" class="btn btn-default btn-sm" onclick="return confirm('Cabut role Piket?');"><i class="fa fa-times"></i> Cabut Piket</button>
                <?php endif; ?>
              </form>

              <?php if ($hasUsr): ?>
              <form method="post" class="form-inline" style="display:inline" onsubmit="return confirm('Samakan password staf dengan password siswa?');">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="user_id" value="<?= (int)$r['user_id'] ?>">
                <button name="act" value="sync_pass" class="btn btn-danger btn-sm"><i class="fa fa-refresh"></i> Reset = Password Siswa</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="7"><em>Tidak ada data sesuai filter.</em></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="box-footer clearfix">
      <ul class="pagination pagination-sm no-margin pull-right">
        <?php $base=$baseSelf.'&kelas='.$kelas_id.'&status='.$status.'&q='.urlencode($q).'&pp='.$pp;
        for($p=1;$p<=$pages;$p++): ?>
          <li class="<?= $p==$page?'active':'' ?>"><a href="<?= $base.'&page='.$p ?>"><?= $p ?></a></li>
        <?php endfor; ?>
      </ul>
      <div class="pull-left" style="margin-top:8px"><small>Halaman <b><?=$page?></b> / <?=$pages?> — menampilkan <b><?=count($rows)?></b> baris.</small></div>
    </div>
  </div>

<?php if(!$EMBED): ?></section><?php endif; ?>

<script>
function toggleAll(chk){var on=(typeof chk==='boolean')?chk:document.getElementById('chkAll').checked;document.querySelectorAll('.chkRow').forEach(function(c){c.checked=on;});}
function setBulkAct(act){
  var ids=Array.from(document.querySelectorAll('.chkRow:checked')).map(function(el){return el.getAttribute('data-siswa');});
  var box=document.getElementById('bulkSelected'); box.innerHTML='';
  ids.forEach(function(id){var i=document.createElement('input');i.type='hidden';i.name='selected[]';i.value=id;box.appendChild(i);});
  document.getElementById('bulkAct').value=act; document.getElementById('bulkForm').submit();
}
function confirmBulk(){var act=document.getElementById('bulkAct').value||''; if(!act){alert('Pilih aksinya dulu.');return false;}
  var map={'bulk_promote_sekretaris':'Jadikan Sekretaris untuk siswa terpilih?','bulk_promote_piket':'Jadikan Piket untuk siswa terpilih?','bulk_revoke_sekretaris':'Cabut Sekretaris dari siswa terpilih?','bulk_revoke_piket':'Cabut Piket dari siswa terpilih?','bulk_sync_pass':'Reset password staf = password siswa?'}; return confirm(map[act]||'Lanjutkan?');}
</script>

<?php if(!$EMBED) include 'footer.php'; ?>
