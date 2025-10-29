<?php
// admin_slider.php - simple slider manager (upload/delete)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: admin_login.php'); exit; }
$csrf = function_exists('generate_csrf_token') ? generate_csrf_token() : '';

$dir = __DIR__ . '/assets/slider';
$webdir = 'assets/slider';
if(!is_dir($dir)) mkdir($dir,0755,true);

// handle upload
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && (!function_exists('verify_csrf_token') || verify_csrf_token($_POST['csrf'] ?? ''))){
    if($_POST['action']==='upload' && !empty($_FILES['slider_image']) ){
        $f = $_FILES['slider_image'];
        if($f['error'] === UPLOAD_ERR_OK){
            $allowed = ['image/jpeg','image/png','image/webp'];
            if(!in_array($f['type'], $allowed)){ $err = "Tipe file tidak diperbolehkan."; }
            else {
                $ext = $f['type']==='image/png' ? 'png' : ($f['type']==='image/webp' ? 'webp' : 'jpg');
                $name = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $dir . '/' . $name;
                if(move_uploaded_file($f['tmp_name'],$dest)){
                    // Optional: simple resize to width 1600 (gd)
                    if(function_exists('getimagesize')){
                        list($w,$h) = getimagesize($dest);
                        $maxw = 1600;
                        if($w > $maxw){
                            $ratio = $h/$w; $neww = $maxw; $newh = round($neww*$ratio);
                            $src = null;
                            if($ext==='png') $src = imagecreatefrompng($dest);
                            elseif($ext==='webp') $src = imagecreatefromwebp($dest);
                            else $src = imagecreatefromjpeg($dest);
                            $dst = imagecreatetruecolor($neww,$newh);
                            imagecopyresampled($dst,$src,0,0,0,0,$neww,$newh,$w,$h);
                            if($ext==='png') imagepng($dst,$dest);
                            elseif($ext==='webp') imagewebp($dst,$dest,80);
                            else imagejpeg($dst,$dest,85);
                            imagedestroy($src); imagedestroy($dst);
                        }
                    }
                    $msg = "Upload sukses.";
                } else $err = "Gagal menyimpan file.";
            }
        } else $err = "Upload error code: " . $f['error'];
    } elseif($_POST['action']==='delete' && !empty($_POST['file'])){
        $file = basename($_POST['file']);
        $path = $dir . '/' . $file;
        if(file_exists($path)) { unlink($path); $msg = "File dihapus."; } else $err = "File tidak ditemukan.";
    }
    // redirect to avoid repost
    $_SESSION['__flash__']['success'] = $msg ?? null;
    $_SESSION['__flash__']['error'] = $err ?? null;
    header('Location: admin_slider.php'); exit;
}

$flash_success = $_SESSION['__flash__']['success'] ?? null; if(isset($_SESSION['__flash__']['success'])) unset($_SESSION['__flash__']['success']);
$flash_error = $_SESSION['__flash__']['error'] ?? null; if(isset($_SESSION['__flash__']['error'])) unset($_SESSION['__flash__']['error']);

$files = array_values(array_filter(scandir($dir), function($f){ return !in_array($f,['.','..']); }));
?>
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kelola Slider</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>.thumb{width:100%;height:180px;object-fit:cover;border-radius:8px}</style>
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark"><div class="container"><a class="navbar-brand" href="admin_dashboard.php">Admin - Resto</a></div></nav>
<div class="container my-4">
  <h4>Kelola Slider</h4>
  <?php if($flash_success): ?><div class="alert alert-success"><?php echo htmlspecialchars($flash_success); ?></div><?php endif;?>
  <?php if($flash_error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($flash_error); ?></div><?php endif;?>
  <div class="card mb-3"><div class="card-body">
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <input type="hidden" name="action" value="upload">
      <div class="mb-2">
        <label class="form-label">Upload gambar slider (jpg/png/webp) — direkomendasikan 1600×450</label>
        <input type="file" name="slider_image" class="form-control" accept="image/*" required>
      </div>
      <button class="btn btn-primary">Upload</button>
    </form>
  </div></div>

  <div class="row g-3">
    <?php foreach($files as $f): ?>
      <div class="col-md-4">
        <div class="card">
          <img src="<?php echo $webdir . '/' . rawurlencode($f); ?>" class="thumb" alt="">
          <div class="card-body">
            <form method="post" onsubmit="return confirm('Hapus gambar ini?')">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="file" value="<?php echo htmlspecialchars($f); ?>">
              <div class="d-flex justify-content-between">
                <a href="<?php echo $webdir . '/' . rawurlencode($f); ?>" target="_blank" class="btn btn-outline-primary btn-sm">Lihat</a>
                <button class="btn btn-outline-danger btn-sm">Hapus</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if(empty($files)): ?><div class="col-12"><div class="alert alert-info">Belum ada gambar slider.</div></div><?php endif;?>
  </div>
</div>
</body>
</html>
