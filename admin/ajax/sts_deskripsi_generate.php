<?php
// admin/ajax/sts_deskripsi_generate.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../helpers/deskripsi_sts_helper.php';

$pts_id = isset($_POST['pts_id']) ? (int)$_POST['pts_id'] : 0;
$pts_ids = isset($_POST['pts_ids']) ? json_decode($_POST['pts_ids'], true) : null;

if ($pts_ids && is_array($pts_ids)) {
  $items = [];
  foreach($pts_ids as $pid){
    [$opt,$per] = load_tp_lists_by_pts((int)$pid);
    $items[] = [ 'pts_id' => (int)$pid, 'deskripsi' => build_deskripsi_final($opt,$per) ];
  }
  echo json_encode(['ok'=>true,'items'=>$items]); exit;
}

if(!$pts_id){ echo json_encode(['ok'=>false,'msg'=>'pts_id kosong']); exit; }
[$opt,$per] = load_tp_lists_by_pts($pts_id);
$desc = build_deskripsi_final($opt,$per);
echo json_encode(['ok'=>true,'deskripsi'=>$desc]);