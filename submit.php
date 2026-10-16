<?php
// ===== PENGATURAN =====
$FOLDER_SIMPAN = __DIR__ . '/data-vendor';   // folder penyimpanan (dibuat otomatis)
$EMAIL_NOTIF   = '';                          // angga.bagus@ptnpg.com
$MAX_BYTES     = 3 * 1024 * 1024;             // maksimal 3 MB per file
// ======================

header('Content-Type: application/json; charset=utf-8');
function gagal($m){ echo json_encode(['ok'=>false,'error'=>$m]); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') gagal('Metode tidak valid.');
if (!empty($_POST['hp'])) { echo json_encode(['ok'=>true,'id'=>'OK']); exit; } // jebakan bot

$f = function($k){ return trim((string)($_POST[$k] ?? '')); };
foreach (['bentuk','nama','bidang','alamat','kota','prov','bank','norek','anrek'] as $k)
  if ($f($k) === '') gagal('Data wajib belum lengkap.');
if (!in_array($f('bentuk'), ['PT','CV'], true)) gagal('Bentuk badan usaha tidak valid.');
if (!preg_match('/^\d+$/', $f('norek'))) gagal('Nomor rekening harus angka.');
if ($f('pos') !== '' && !preg_match('/^\d{5}$/', $f('pos'))) gagal('Kode pos harus 5 angka.');
if ($f('pkp') !== '' && !in_array($f('pkp'), ['PKP','Non PKP'], true)) gagal('Status PKP tidak valid.');

$dok = ['npwp'=>1,'nib'=>1,'skpkp'=>($f('pkp')==='PKP'?1:0),'nonpkp'=>0,'profil'=>0];
$izin = ['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'];
$fi = new finfo(FILEINFO_MIME_TYPE);
$terima = [];
foreach ($dok as $k=>$wajib) {
  $berkas = $_FILES['file_'.$k] ?? null;
  if (!$berkas || $berkas['error'] === UPLOAD_ERR_NO_FILE) { if ($wajib) gagal("Dokumen wajib belum dilampirkan: $k."); continue; }
  if ($berkas['error'] !== UPLOAD_ERR_OK || $berkas['size'] > $MAX_BYTES) gagal("File $k gagal diunggah atau lebih dari 3 MB.");
  $ext = strtolower(pathinfo($berkas['name'], PATHINFO_EXTENSION));
  if (!isset($izin[$ext]) || $fi->file($berkas['tmp_name']) !== $izin[$ext]) gagal("Format file $k harus PDF, JPG, atau PNG.");
  $terima[$k] = [$berkas['tmp_name'], $k.'.'.$ext];
}

if (!is_dir($FOLDER_SIMPAN)) { mkdir($FOLDER_SIMPAN, 0750, true); file_put_contents($FOLDER_SIMPAN.'/.htaccess', "Require all denied\nDeny from all\n"); }
$slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($f('nama'))), '-');
$id = date('Ymd-His') . '-' . substr($slug ?: 'vendor', 0, 30) . '-' . bin2hex(random_bytes(3));
$dir = "$FOLDER_SIMPAN/$id";
if (!mkdir($dir, 0750, true)) gagal('Server gagal menyimpan data.');
foreach ($terima as $k=>$v) move_uploaded_file($v[0], "$dir/{$v[1]}");
file_put_contents("$dir/ringkasan.txt", substr((string)($_POST['ringkasan'] ?? ''), 0, 20000));
$data = $_POST; unset($data['hp'], $data['ringkasan']);
file_put_contents("$dir/data.json", json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

if ($EMAIL_NOTIF !== '') @mail($EMAIL_NOTIF, "Vendor baru: ".$f('nama'), "Pendaftaran vendor baru masuk.\nID: $id\nNama: ".$f('nama')."\nBentuk: ".$f('bentuk')."\n", "Content-Type: text/plain; charset=utf-8");
echo json_encode(['ok'=>true,'id'=>$id]);
