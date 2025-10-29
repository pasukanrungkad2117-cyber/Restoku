<?php
// admin_menu.php (diperbarui: preview gambar & client-side validation)
require_once 'config.php';
require_once 'functions.php';

if(empty($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php'); exit;
}

$errors = [];
$success = null;

// Handle create/update/delete (server-side already implemented earlier)
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && verify_csrf_token($_POST['csrf'] ?? '')){
    $action = $_POST['action'];
    if($action === 'create'){
        $name = trim($_POST['name'] ?? '');
        $category = $_POST['category'] ?? 'Makanan Berat';
        $price = (int)($_POST['price'] ?? 0);
        $desc = trim($_POST['description'] ?? '');

        if($name === '') $errors[] = 'Nama wajib diisi';
        if($price <= 0) $errors[] = 'Harga harus lebih dari 0';

        $image_filename = null;
        if(isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE){
            $img_err = null;
            $res = handle_image_upload($_FILES['image'], $img_err);
            if($res === false) $errors[] = 'Gambar: ' . $img_err;
            else $image_filename = $res;
        }

        if(empty($errors)){
            $stmt = $pdo->prepare("INSERT INTO menu_items (name, category, price, description, image) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $category, $price, $desc, $image_filename]);
            $success = 'Menu berhasil ditambahkan';
        }
    } elseif ($action === 'update' && isset($_POST['id'])){
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $category = $_POST['category'] ?? 'Makanan Berat';
        $price = (int)($_POST['price'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        if($name === '') $errors[] = 'Nama wajib diisi';
        if($price <= 0) $errors[] = 'Harga harus lebih dari 0';

        if(empty($errors)){
            $stmt = $pdo->prepare("SELECT image FROM menu_items WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();

            $image_filename = $row['image'] ?? null;
            if(isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE){
                $img_err = null;
                $res = handle_image_upload($_FILES['image'], $img_err);
                if($res === false) $errors[] = 'Gambar: ' . $img_err;
                else {
                    if($image_filename){
                        @unlink(__DIR__ . '/assets/images/' . $image_filename);
                        @unlink(__DIR__ . '/assets/images/thumbs/' . $image_filename);
                    }
                    $image_filename = $res;
                }
            }

            if(empty($errors)){
                $stmt = $pdo->prepare("UPDATE menu_items SET name=?, category=?, price=?, description=?, image=? WHERE id = ?");
                $stmt->execute([$name, $category, $price, $desc, $image_filename, $id]);
                $success = 'Menu berhasil diperbarui';
            }
        }
    }
}

// Handle delete via GET action=delete&id=...
if(isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])){
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT image FROM menu_items WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if($row && $row['image']){
        @unlink(__DIR__ . '/assets/images/' . $row['image']);
        @unlink(__DIR__ . '/assets/images/thumbs/' . $row['image']);
    }
    $pdo->prepare("DELETE FROM menu_items WHERE id = ?")->execute([$id]);
    header('Location: admin_menu.php'); exit;
}

$menus = $pdo->query("SELECT * FROM menu_items ORDER BY category, name")->fetchAll();
$csrf = generate_csrf_token();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Kelola Menu</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .thumb-preview{ width:110px; height:72px; object-fit:cover; border-radius:6px; border:1px solid #e6e6e6; }
    .no-image{ width:110px; height:72px; display:inline-flex; align-items:center; justify-content:center; background:#f1f3f5; color:#6b7280; border-radius:6px; }
    .small-muted{ font-size:0.85rem; color:#6b7280; }
  </style>
</head>
<body class="bg-light">
<div class="container my-4">
  <a href="admin_dashboard.php" class="btn btn-link">&larr; Dashboard</a>
  <h3>Kelola Menu</h3>

  <?php if($errors): ?>
    <div class="alert alert-danger">
      <ul><?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
    </div>
  <?php endif; ?>
  <?php if($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <!-- Add new -->
  <div class="card mb-4">
    <div class="card-body">
      <h5>Tambah Menu Baru</h5>
      <form id="createForm" method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="action" value="create">
        <div class="row g-2">
          <div class="col-md-4"><input name="name" class="form-control" placeholder="Nama menu" required></div>
          <div class="col-md-3">
            <select name="category" class="form-select">
              <option>Makanan Berat</option>
              <option>Makanan Ringan</option>
              <option>Minuman</option>
            </select>
          </div>
          <div class="col-md-2"><input name="price" type="number" class="form-control" placeholder="Harga" required></div>
          <div class="col-md-3">
            <input id="createImage" name="image" type="file" accept="image/*" class="form-control">
            <div id="createPreview" class="mt-2 no-image">No Image</div>
            <div class="small-muted mt-1">Maks 3MB. Tipe: JPG / PNG / WEBP.</div>
          </div>
          <div class="col-12 mt-2"><textarea name="description" class="form-control" placeholder="Deskripsi (opsional)"></textarea></div>
          <div class="col-12 mt-2"><button class="btn btn-primary">Tambah Menu</button></div>
        </div>
      </form>
    </div>
  </div>

  <!-- List existing -->
  <div class="card">
    <div class="card-body">
      <h5>Daftar Menu</h5>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead><tr><th>ID</th><th>Gambar</th><th>Nama</th><th>Kategori</th><th>Harga</th><th>Aksi</th></tr></thead>
          <tbody>
            <?php foreach($menus as $m): ?>
              <tr>
                <td><?php echo (int)$m['id']; ?></td>
                <td style="width:140px">
                  <?php if($m['image'] && file_exists(__DIR__ . '/assets/images/thumbs/' . $m['image'])): ?>
                    <img src="assets/images/thumbs/<?php echo e($m['image']); ?>" class="thumb-preview" alt="">
                  <?php else: ?>
                    <div class="no-image">No Image</div>
                  <?php endif; ?>
                </td>
                <td><?php echo e($m['name']); ?></td>
                <td><?php echo e($m['category']); ?></td>
                <td>Rp <?php echo number_format($m['price'],0,',','.'); ?></td>
                <td>
                  <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo (int)$m['id']; ?>">Edit</button>
                  <a onclick="return confirm('Hapus menu ini?')" href="admin_menu.php?action=delete&id=<?php echo (int)$m['id']; ?>" class="btn btn-sm btn-danger">Hapus</a>
                </td>
              </tr>

              <!-- Edit Modal -->
              <div class="modal fade" id="editModal<?php echo (int)$m['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                    <form class="editForm" method="post" enctype="multipart/form-data" novalidate>
                      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                      <div class="modal-header"><h5 class="modal-title">Edit Menu #<?php echo (int)$m['id']; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <div class="row g-2">
                          <div class="col-md-6"><input name="name" value="<?php echo e($m['name']); ?>" class="form-control" required></div>
                          <div class="col-md-3">
                            <select name="category" class="form-select">
                              <option <?php if($m['category']==='Makanan Berat') echo 'selected'; ?>>Makanan Berat</option>
                              <option <?php if($m['category']==='Makanan Ringan') echo 'selected'; ?>>Makanan Ringan</option>
                              <option <?php if($m['category']==='Minuman') echo 'selected'; ?>>Minuman</option>
                            </select>
                          </div>
                          <div class="col-md-3"><input name="price" type="number" value="<?php echo (int)$m['price']; ?>" class="form-control" required></div>
                          <div class="col-12 mt-2"><textarea name="description" class="form-control"><?php echo e($m['description']); ?></textarea></div>
                          <div class="col-12 mt-2">
                            <label class="form-label">Ganti gambar (opsional)</label>
                            <input class="editImage" name="image" type="file" accept="image/*" class="form-control">
                            <?php if($m['image'] && file_exists(__DIR__ . '/assets/images/thumbs/' . $m['image'])): ?>
                              <div class="mt-2">
                                <span class="small-muted">Preview:</span>
                                <img src="assets/images/thumbs/<?php echo e($m['image']); ?>" class="thumb-preview preview-existing-<?php echo (int)$m['id']; ?>" alt="">
                              </div>
                            <?php else: ?>
                              <div class="mt-2">
                                <span class="small-muted">Preview:</span>
                                <div class="no-image preview-existing-<?php echo (int)$m['id']; ?>">No Image</div>
                              </div>
                            <?php endif; ?>
                            <div class="small-muted mt-1">Maks 3MB. JPG / PNG / WEBP. Preview akan muncul sebelum upload.</div>
                          </div>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                        <button class="btn btn-primary">Simpan Perubahan</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>

            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
/*
 Client-side preview & validation
 - Max size 3MB
 - Allowed types: image/*
 - For create & each edit modal
*/

const MAX_BYTES = 3 * 1024 * 1024;
const allowed = ['image/jpeg','image/png','image/webp'];

function readAndPreview(file, targetEl){
  const reader = new FileReader();
  reader.onload = e => {
    targetEl.innerHTML = '';
    const img = document.createElement('img');
    img.src = e.target.result;
    img.className = 'thumb-preview';
    targetEl.appendChild(img);
  };
  reader.readAsDataURL(file);
}

function showFileError(targetEl, msg){
  targetEl.innerHTML = '<div style="color:#b91c1c;font-size:.9rem">'+msg+'</div>';
}

// create form handlers
const createInput = document.getElementById('createImage');
const createPreview = document.getElementById('createPreview');
if(createInput){
  createInput.addEventListener('change', function(){
    const f = this.files[0];
    if(!f){ createPreview.innerHTML = 'No Image'; return; }
    if(f.size > MAX_BYTES){ showFileError(createPreview, 'File terlalu besar (max 3MB)'); this.value = ''; return; }
    if(!allowed.includes(f.type)){ showFileError(createPreview, 'Tipe file tidak diizinkan'); this.value = ''; return; }
    // preview
    createPreview.innerHTML = '';
    readAndPreview(f, createPreview);
  });
}

// edit modals (multiple)
document.querySelectorAll('.editForm').forEach(form=>{
  const fileInput = form.querySelector('input[type=file]');
  if(!fileInput) return;
  // find preview existing element inside same modal
  const modal = form.closest('.modal');
  const previewExisting = modal.querySelector('[class^="preview-existing-"]') || null;
  // create a dynamic preview container
  let dynPreview = modal.querySelector('.dyn-preview');
  if(!dynPreview){
    dynPreview = document.createElement('div');
    dynPreview.className = 'mt-2 dyn-preview';
    fileInput.parentNode.appendChild(dynPreview);
  }
  fileInput.addEventListener('change', function(){
    dynPreview.innerHTML = '';
    const f = this.files[0];
    if(!f){
      // restore existing preview (if any)
      return;
    }
    if(f.size > MAX_BYTES){ showFileError(dynPreview, 'File terlalu besar (max 3MB)'); this.value = ''; return; }
    if(!allowed.includes(f.type)){ showFileError(dynPreview, 'Tipe file tidak diizinkan'); this.value = ''; return; }
    readAndPreview(f, dynPreview);
  });
});
</script>
</body>
</html>
