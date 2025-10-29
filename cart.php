<?php
// cart.php (perbaikan: update working, profesional back button, live subtotal & total, catatan)
require_once 'config.php';
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(!isset($_POST['csrf']) || !verify_csrf_token($_POST['csrf'])){
        die('CSRF token invalid');
    }
    $action = $_POST['action'] ?? '';
    if($action === 'add'){
        $menu_id = (int)$_POST['menu_id'];
        $qty = max(1, (int)$_POST['qty']);
        $stmt = $pdo->prepare("SELECT id, name, price, image FROM menu_items WHERE id = ?");
        $stmt->execute([$menu_id]);
        $item = $stmt->fetch();
        if($item){
            if(!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
            if(isset($_SESSION['cart'][$menu_id])){
                $_SESSION['cart'][$menu_id]['qty'] += $qty;
            } else {
                $_SESSION['cart'][$menu_id] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'qty' => $qty,
                    'image' => $item['image'] ?? null
                ];
            }
            flash_set('success','Item ditambahkan ke keranjang');
        }
        header('Location: cart.php'); exit;
    } elseif ($action === 'update'){
        // update qty or remove (if qty 0)
        if(!empty($_POST['qty']) && is_array($_POST['qty'])){
            foreach($_POST['qty'] as $menu_id => $q){
                $menu_id = (int)$menu_id;
                $q = (int)$q;
                if($q <= 0){
                    unset($_SESSION['cart'][$menu_id]);
                } else {
                    if(isset($_SESSION['cart'][$menu_id])) $_SESSION['cart'][$menu_id]['qty'] = $q;
                }
            }
            flash_set('success','Keranjang diperbarui');
        }
        header('Location: cart.php'); exit;
    } elseif ($action === 'remove'){
        // remove single item
        $menu_id = (int)($_POST['menu_id'] ?? 0);
        if($menu_id && isset($_SESSION['cart'][$menu_id])){
            unset($_SESSION['cart'][$menu_id]);
            flash_set('success','Item dihapus dari keranjang');
        }
        header('Location: cart.php'); exit;
    } elseif ($action === 'clear'){
        unset($_SESSION['cart']);
        flash_set('success','Keranjang dikosongkan');
        header('Location: cart.php'); exit;
    }
}

// Show cart
$cart = $_SESSION['cart'] ?? [];
$total = 0;
foreach($cart as $c) $total += $c['price'] * $c['qty'];
$csrf = generate_csrf_token();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Keranjang - Resto</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:#f4f6fa;color:#111827}
    .card-soft{background:#fff;border-radius:12px;box-shadow:0 6px 20px rgba(16,24,40,0.06)}
    .muted{color:#6b7280}
    .thumb{width:96px;height:72px;object-fit:cover;border-radius:8px;border:1px solid #eef2f6}
    .btn-remove{border:1px solid #e11d48;color:#e11d48;background:transparent}
    .btn-back { background:#0d6efd;color:#fff;border-radius:8px;padding:6px 12px;border:0 }
    @media (max-width:575px){ .thumb{width:72px;height:54px} }
    .small-muted { font-size:0.9rem;color:#6b7280; }
  </style>
</head>
<body>
<div class="container my-5">
  <!-- professional Back button -->
  <div class="mb-3">
    <a href="index.php" class="btn btn-back"><i class="bi bi-arrow-left-circle me-1"></i> Kembali ke Menu</a>
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card card-soft p-4 mb-4">
        <h4 class="mb-3">Keranjang Anda</h4>

        <?php if($msg = flash_get('success')): ?>
          <div class="alert alert-success"><?php echo e($msg); ?></div>
        <?php endif; ?>

        <?php if(!$cart): ?>
          <div class="py-4 text-center muted">Keranjang kosong — tambahkan menu favoritmu.</div>
        <?php else: ?>
          <!-- Update form (single form; no nested forms) -->
          <form id="updateForm" method="post" class="mb-3">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="update">
            <div class="list-group">
              <?php foreach($cart as $k => $it): 
                $subtotal = $it['price'] * $it['qty'];
                $img = (!empty($it['image']) && file_exists(__DIR__.'/assets/images/'.$it['image'])) ? 'assets/images/'.rawurlencode($it['image']) : 'https://via.placeholder.com/160x120?text=No+Image';
              ?>
                <div class="list-group-item d-flex gap-3 align-items-center" data-menu-id="<?php echo (int)$k; ?>">
                  <img src="<?php echo $img; ?>" alt="" class="thumb">
                  <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                      <div>
                        <div class="fw-semibold"><?php echo e($it['name']); ?></div>
                        <div class="muted small">Rp <?php echo number_format($it['price'],0,',','.'); ?> / pcs</div>
                      </div>
                      <div class="text-end">
                        <div class="muted small">Subtotal</div>
                        <div class="fw-bold item-subtotal" data-menu-id="<?php echo (int)$k; ?>">Rp <?php echo number_format($subtotal,0,',','.'); ?></div>
                      </div>
                    </div>

                    <div class="mt-2 d-flex align-items-center justify-content-between">
                      <div style="max-width:160px">
                        <input data-menu-id="<?php echo (int)$k; ?>" type="number" name="qty[<?php echo (int)$k; ?>]" value="<?php echo (int)$it['qty']; ?>" min="0" class="form-control form-control-sm qty-input">
                      </div>

                      <div class="d-flex gap-2 align-items-center">
                        <!-- Remove single item (JS form submission) -->
                        <button type="button" class="btn btn-remove btn-sm" onclick="removeItem(<?php echo (int)$k; ?>)">Hapus</button>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="mt-3 d-flex justify-content-between align-items-center">
              <div>
                <!-- Perbarui button -->
                <button id="updateBtn" type="submit" class="btn btn-primary">Perbarui Keranjang</button>
                <a href="#" class="btn btn-outline-danger" onclick="event.preventDefault(); if(confirm('Kosongkan keranjang?')) document.getElementById('clearForm').submit();">Kosongkan</a>
              </div>
              <div class="text-end">
                <div class="muted small">Total:</div>
                <div class="h4" id="totalAmount">Rp <?php echo number_format($total,0,',','.'); ?></div>
              </div>
            </div>
          </form>
        <?php endif; ?>
      </div>

      <!-- optional: tips / promo -->
      <div class="card card-soft p-3 muted small">
        <div class="fw-semibold mb-1">Tips</div>
        <div>Masukkan email saat checkout untuk menerima struk pembelian melalui email.</div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card card-soft p-4">
        <h5 class="mb-3">Checkout</h5>

        <?php if(!$cart): ?>
          <div class="muted small">Tambahkan item ke keranjang terlebih dahulu.</div>
        <?php else: ?>
          <form method="post" action="checkout.php" id="checkoutForm">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="checkout">

            <div class="mb-3">
              <label for="table_no" class="form-label">Nomor Meja</label>
              <input type="text" id="table_no" name="table_no" class="form-control" required placeholder="Contoh: 01 / A5">
            </div>

            <div class="mb-3">
              <label for="email" class="form-label">Email (untuk kirim struk)</label>
              <input type="email" id="email" name="email" class="form-control" placeholder="email@pelanggan.com" required>
              <div class="form-text">Kami akan mengirimkan struk pembelian ke alamat email ini.</div>
            </div>

            <div class="mb-3">
              <label for="customer_note" class="form-label">Catatan (opsional)</label>
              <textarea id="customer_note" name="customer_note" class="form-control" rows="3" placeholder="Contoh: Kurang pedas, tanpa bawang..."></textarea>
              <div class="form-text small-muted">Catatan ini akan dikirim bersama struk.</div>
            </div>

            <div class="mb-3">
              <div class="muted small mb-1">Ringkasan</div>
              <div class="d-flex justify-content-between">
                <div>Subtotal</div>
                <div id="summarySubtotal">Rp <?php echo number_format($total,0,',','.'); ?></div>
              </div>
            </div>

            <button type="submit" class="btn btn-success w-100">Checkout & Kirim Struk via Email</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <form method="post" id="clearForm" style="display:none">
    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
    <input type="hidden" name="action" value="clear">
  </form>
</div>

<script>
  // Remove item by constructing a minimal form and submitting it (avoids nested forms)
  function removeItem(menuId){
    if(!confirm('Hapus item ini dari keranjang?')) return;
    const f = document.createElement('form');
    f.method = 'post';
    f.style.display = 'none';
    const cs = document.createElement('input');
    cs.name = 'csrf'; cs.value = '<?php echo $csrf; ?>'; f.appendChild(cs);
    const a = document.createElement('input');
    a.name = 'action'; a.value = 'remove'; f.appendChild(a);
    const m = document.createElement('input');
    m.name = 'menu_id'; m.value = menuId; f.appendChild(m);
    document.body.appendChild(f);
    f.submit();
  }

  // Live update subtotal & total when qty changes
  function formatRupiah(num){
    return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  }

  function recalcTotals(){
    let total = 0;
    document.querySelectorAll('.qty-input').forEach(inp=>{
      const menuId = inp.getAttribute('data-menu-id');
      const qty = parseInt(inp.value) || 0;
      // price: derive from DOM (the original price not printed as data attribute), so we'll retrieve price from server-rendered text
      // To avoid parsing text, embed price in a data attribute: but we didn't. We'll compute subtotal by asking server on update.
      // Simpler approach: store price in an attribute on the parent element on render.
    });
  }

  // To compute live subtotals we need prices in DOM; add data-price in each qty input.
  // Attach event handlers and compute directly
  document.querySelectorAll('.qty-input').forEach(inp=>{
    // ensure data-price present: when rendering we can embed data-price attribute in the input; if not present, derive from nearby text
    // Try to get price from sibling text (format Rp 30.000)
    let parent = inp.closest('[data-menu-id]');
    let price = 0;
    // attempt to read price from the "muted small" text (Rp x / pcs)
    const priceTextEl = parent.querySelector('.muted.small');
    if(priceTextEl){
      const m = priceTextEl.textContent.match(/Rp\s*([\d\.\,]+)/);
      if(m){
        price = parseInt(m[1].replace(/\./g,'')) || 0;
      }
    }
    // store price on element for fast calc
    inp.dataset.price = price;

    inp.addEventListener('input', function(){
      const q = parseInt(this.value) || 0;
      const pr = parseInt(this.dataset.price) || 0;
      const sub = q * pr;
      // update subtotal display
      const subEl = document.querySelector('.item-subtotal[data-menu-id="'+this.dataset.menuId+'"]');
      if(subEl) subEl.textContent = formatRupiah(sub);
      // recompute total
      let total = 0;
      document.querySelectorAll('.qty-input').forEach(i=>{
        total += (parseInt(i.value) || 0) * (parseInt(i.dataset.price) || 0);
      });
      document.getElementById('totalAmount').textContent = formatRupiah(total);
      document.getElementById('summarySubtotal').textContent = formatRupiah(total);
      // enable update button
      document.getElementById('updateBtn').disabled = false;
    });
  });

  // initially ensure update button enabled
  document.getElementById('updateBtn').disabled = false;

  // Checkout validation
  document.getElementById('checkoutForm')?.addEventListener('submit', function(e){
    const email = document.getElementById('email').value.trim();
    const table = document.getElementById('table_no').value.trim();
    if(!table){ alert('Isi nomor meja'); e.preventDefault(); return; }
    if(!email || !/^\S+@\S+\.\S+$/.test(email)){ alert('Masukkan email yang valid'); e.preventDefault(); return; }
    // Before submit, optionally sync qty changes by submitting update first (but we'll rely on checkout.php reading cart server-side)
  });
</script>
</body>
</html>
