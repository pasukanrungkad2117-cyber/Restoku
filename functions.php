<?php
// functions.php
require_once 'config.php';

function generate_csrf_token(){
    if(empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token){
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function flash_set($k, $m){
    $_SESSION['flash'][$k] = $m;
}
function flash_get($k){
    if(isset($_SESSION['flash'][$k])){
        $m = $_SESSION['flash'][$k];
        unset($_SESSION['flash'][$k]);
        return $m;
    }
    return null;
}
// functions.php  (tambahkan dibawah fungsi lain)
function ensure_image_dirs(){
    $base = __DIR__ . '/assets/images';
    $thumb = $base . '/thumbs';
    if(!is_dir($base)) mkdir($base, 0755, true);
    if(!is_dir($thumb)) mkdir($thumb, 0755, true);
}

function generate_random_filename($ext){
    return bin2hex(random_bytes(10)) . '.' . $ext;
}

/**
 * Handle upload, validate, resize main image and make thumbnail.
 * - $file: $_FILES['fieldname']
 * - returns filename (e.g. 3f2a...jpg) or false on error (use errors via global or return array)
*/
function handle_image_upload($file, &$error=null){
    ensure_image_dirs();
    $max_size = 3 * 1024 * 1024; // 3MB
    $allowed_mime = ['image/jpeg','image/png','image/webp'];

    if(!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE){
        $error = 'No file uploaded';
        return false;
    }
    if($file['error'] !== UPLOAD_ERR_OK){
        $error = 'Upload error code: ' . $file['error'];
        return false;
    }
    if($file['size'] > $max_size){
        $error = 'File terlalu besar (max 3MB).';
        return false;
    }

    // verify mime via getimagesize
    $info = @getimagesize($file['tmp_name']);
    if(!$info || !in_array($info['mime'], $allowed_mime)){
        $error = 'Tipe file tidak diperbolehkan. Gunakan JPG/PNG/WebP.';
        return false;
    }

    // determine extension & create image resource
    $mime = $info['mime'];
    switch($mime){
        case 'image/jpeg': $src_img = imagecreatefromjpeg($file['tmp_name']); $ext = 'jpg'; break;
        case 'image/png':  $src_img = imagecreatefrompng($file['tmp_name']); $ext = 'jpg'; break; // convert to jpg
        case 'image/webp': $src_img = function_exists('imagecreatefromwebp') ? imagecreatefromwebp($file['tmp_name']) : null; $ext = 'jpg'; break;
        default: $src_img = null; $ext = 'jpg';
    }
    if(!$src_img){
        $error = 'Gagal memproses gambar (library GD mungkin belum aktif).';
        return false;
    }

    $orig_w = imagesx($src_img);
    $orig_h = imagesy($src_img);

    // TARGET SIZES (ubah kalau perlu)
    $max_main_w = 1200; $max_main_h = 900;   // main image max
    $thumb_w = 600; $thumb_h = 400;          // thumb size (crop center)

    // --- Resize main image, preserving aspect ratio ---
    $ratio = min($max_main_w / $orig_w, $max_main_h / $orig_h, 1); // no upscale
    $main_w = (int)($orig_w * $ratio);
    $main_h = (int)($orig_h * $ratio);

    $main_img = imagecreatetruecolor($main_w, $main_h);
    // white background for PNG transparency
    $white = imagecolorallocate($main_img, 255,255,255);
    imagefill($main_img, 0,0, $white);
    imagecopyresampled($main_img, $src_img, 0,0, 0,0, $main_w, $main_h, $orig_w, $orig_h);

    // --- Create center-cropped thumbnail (fill thumb_w x thumb_h) ---
    // First, compute crop box on original image
    $src_ratio = $orig_w / $orig_h;
    $thumb_ratio = $thumb_w / $thumb_h;
    if($src_ratio > $thumb_ratio){
        // crop sides
        $crop_h = $orig_h;
        $crop_w = (int)($thumb_ratio * $orig_h);
        $crop_x = (int)(($orig_w - $crop_w) / 2);
        $crop_y = 0;
    } else {
        // crop top/bottom
        $crop_w = $orig_w;
        $crop_h = (int)($orig_w / $thumb_ratio);
        $crop_x = 0;
        $crop_y = (int)(($orig_h - $crop_h) / 2);
    }
    $thumb_img = imagecreatetruecolor($thumb_w, $thumb_h);
    $white2 = imagecolorallocate($thumb_img, 255,255,255);
    imagefill($thumb_img, 0,0, $white2);
    imagecopyresampled($thumb_img, $src_img, 0,0, $crop_x, $crop_y, $thumb_w, $thumb_h, $crop_w, $crop_h);

    // generate filename and save both as JPEG
    $filename = generate_random_filename('jpg');
    $main_path = __DIR__ . '/assets/images/' . $filename;
    $thumb_path = __DIR__ . '/assets/images/thumbs/' . $filename;

    $saved_main = imagejpeg($main_img, $main_path, 85);
    $saved_thumb = imagejpeg($thumb_img, $thumb_path, 82);

    imagedestroy($src_img);
    imagedestroy($main_img);
    imagedestroy($thumb_img);

    if($saved_main && $saved_thumb){
        return $filename;
    } else {
        // cleanup partial files
        if(file_exists($main_path)) @unlink($main_path);
        if(file_exists($thumb_path)) @unlink($thumb_path);
        $error = 'Gagal menyimpan gambar.';
        return false;
    }
}
