<?php
require_once 'config.php';
require_once 'functions.php';

$stmt = $pdo->query("SELECT * FROM menu_items ORDER BY category, name");
$items = $stmt->fetchAll();

$csrf = generate_csrf_token();
$logo_local = file_exists(__DIR__ . '/assets/logo.png') ? 'assets/logo.png' : null;

// ambil slider dari database jika ada
$slides = [];
try {
    if($pdo->query("SHOW TABLES LIKE 'slider_images'")->fetch()){
        $rs = $pdo->query("SELECT filename, caption FROM slider_images ORDER BY sort_order ASC, id ASC")->fetchAll();
        foreach($rs as $r){
            $fn = $r['filename'] ?? '';
            if($fn && file_exists(__DIR__ . '/assets/slider/' . $fn)){
                $slides[] = ['url' => 'assets/slider/' . rawurlencode($fn), 'caption' => $r['caption'] ?? ''];
            }
        }
    }
} catch(Throwable $e){}
if(count($slides) === 0){
    $fallback = ['assets/images/slider1.jpg','assets/images/slider2.jpg','assets/images/slider3.jpg'];
    foreach($fallback as $f){
        if(file_exists(__DIR__ . '/' . $f)){
            $slides[] = ['url' => $f, 'caption' => ''];
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Resto - Menu</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
  :root{
    --bg-light: #f5f6fa;
    --bg-dark: #0b1220;
    --card-light: #ffffff;
    --card-dark: #111827;
    --text-light: #111827;
    --text-dark: #e5e7eb;
    --nav-dark: #1e40af;
  }

  body {
    background: var(--bg-light);
    color: var(--text-light);
    transition: background-color 400ms ease, color 400ms ease;
  }
  .navbar { transition: background-color 400ms ease, color 400ms ease; }
  .card {
    background: var(--card-light);
    transition: background-color 400ms ease, color 400ms ease, box-shadow 300ms ease;
  }
  .hero-overlay { transition: background 400ms ease; }

  .hero-section{position:relative;overflow:hidden;height:450px;color:#fff;}
  .hero-carousel img{object-fit:cover;width:100%;height:450px;filter:brightness(70%);transition:filter 400ms ease;}
  .hero-overlay{position:absolute;inset:0;background:rgba(0,0,0,0.45);z-index:2;}
  .hero-content{position:absolute;z-index:3;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;color:#fff;width:90%;max-width:800px;}
  .hero-content h1{font-weight:700;margin-bottom:15px;}
  .hero-content p{font-size:1.1rem;margin-bottom:25px;}
  .search-area input,.search-area select{border-radius:10px;font-size:1rem;}
  .card img{height:180px;object-fit:cover;border-top-left-radius:.5rem;border-top-right-radius:.5rem;}

  .theme-transition * { transition: background-color 400ms ease, color 400ms ease, border-color 400ms ease !important; }

  .dark-mode {
    --bg-light: var(--bg-dark);
    --card-light: var(--card-dark);
    --text-light: var(--text-dark);
  }
  .dark-mode .navbar { background: var(--nav-dark) !important; }
  .dark-mode .hero-overlay { background: rgba(0,0,0,0.6); }
  @media (prefers-reduced-motion: reduce) {
    body, .card, .navbar, .hero-overlay, .theme-transition * {
      transition: none !important;
    }
  }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="#">
      <?php if($logo_local): ?>
        <img src="<?php echo e($logo_local); ?>" alt="logo" class="me-2" style="width:40px;height:40px;object-fit:cover;border-radius:8px;">
      <?php else: ?><i class="bi bi-shop-window me-2 fs-3"></i><?php endif; ?>
      <span class="fw-bold">Resto</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item me-2">
          <a href="cart.php" class="btn btn-outline-light btn-sm">
            <i class="bi bi-cart"></i> Keranjang
            <span id="cart-count" class="badge bg-white text-primary ms-1"></span>
          </a>
        </li>
        <li class="nav-item me-2">
          <button id="darkToggle" class="btn btn-outline-light btn-sm" title="Ganti Tema"><i id="darkIcon" class="bi"></i></button>
        </li>
        <li class="nav-item"><a href="admin_login.php" class="btn btn-light btn-sm"><i class="bi bi-person-fill"></i> Admin</a></li>
      </ul>
    </div>
  </div>
</nav>

<header class="hero-section">
  <div id="heroCarousel" class="carousel slide hero-carousel" data-bs-ride="carousel" data-bs-interval="4000">
    <div class="carousel-inner">
      <?php foreach($slides as $i=>$s): ?>
      <div class="carousel-item <?php echo $i==0?'active':''; ?>">
        <img src="<?php echo e($s['url']); ?>" class="d-block w-100" alt="">
        <?php if(!empty($s['caption'])): ?>
          <div class="carousel-caption"><p><?php echo e($s['caption']); ?></p></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if(count($slides)>1): ?>
    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
      <span class="carousel-control-prev-icon"></span><span class="visually-hidden">Prev</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
      <span class="carousel-control-next-icon"></span><span class="visually-hidden">Next</span>
    </button>
    <?php endif; ?>
  </div>

  <div class="hero-overlay"></div>
  <div class="hero-content">
    <h1>Selamat Datang di Resto</h1>
    <p>Pilih menu favoritmu — Makanan Berat, Ringan, atau Minuman. Pesan & bayar dengan cepat lewat QR.</p>

    <div class="search-area">
      <form class="row g-2 justify-content-center" onsubmit="return false;">
        <div class="col-md-6 col-lg-4">
          <input id="searchInput" type="search" class="form-control form-control-lg" placeholder="Cari menu..." oninput="filterCards()">
        </div>
        <div class="col-md-3 col-lg-3">
          <select id="categoryFilter" class="form-select form-select-lg" onchange="filterCards()">
            <option value="">Semua Kategori</option>
            <option value="Makanan Berat">Makanan Berat</option>
            <option value="Makanan Ringan">Makanan Ringan</option>
            <option value="Minuman">Minuman</option>
          </select>
        </div>
      </form>
    </div>
  </div>
</header>

<main class="container my-5">
  <?php foreach(['Makanan Berat','Makanan Ringan','Minuman'] as $cat): ?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0 fw-semibold text-dark"><?php echo e($cat); ?></h4>
    <span class="badge bg-primary-subtle text-primary">
      <?php $count=0; foreach($items as $it) if($it['category']==$cat) $count++; echo $count.' item'; ?>
    </span>
  </div>
  <div class="row g-4 mb-5">
    <?php foreach($items as $it) if($it['category']==$cat): ?>
    <div class="col-sm-6 col-md-4 col-lg-3">
      <div class="card menu-card h-100 shadow-sm" data-name="<?php echo strtolower(e($it['name'])); ?>" data-category="<?php echo strtolower(e($it['category'])); ?>">
        <img src="<?php echo file_exists("assets/images/{$it['image']}")?'assets/images/'.e($it['image']):'https://via.placeholder.com/600x400/eeeeee/888888?text=No+Image'; ?>" class="img-fluid" alt="">
        <div class="card-body d-flex flex-column">
          <h5 class="card-title mb-1 text-dark"><?php echo e($it['name']); ?></h5>
          <p class="card-text text-muted small mb-2"><?php echo e($it['description']); ?></p>
          <div class="mt-auto d-flex justify-content-between align-items-center">
            <span class="fw-bold text-primary">Rp <?php echo number_format($it['price'],0,',','.'); ?></span>
            <form method="post" action="cart.php">
              <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="menu_id" value="<?php echo (int)$it['id']; ?>">
              <div class="input-group input-group-sm" style="width:100px;">
                <input type="number" name="qty" value="1" min="1" class="form-control">
                <button class="btn btn-primary"><i class="bi bi-plus-circle"></i></button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</main>

<footer class="py-4 bg-white border-top text-center text-muted small">
  &copy; <?php echo date('Y'); ?> Resto • Profesional Menu System
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('cart-count').textContent = localStorage.getItem('cartCount') || '';

function filterCards(){
  const q = document.getElementById('searchInput').value.trim().toLowerCase();
  const cat = document.getElementById('categoryFilter').value.trim().toLowerCase();
  const cards = document.querySelectorAll('.menu-card');
  cards.forEach(c=>{
    const name = c.getAttribute('data-name');
    const category = c.getAttribute('data-category');
    let visible = true;
    if(q && !name.includes(q)) visible = false;
    if(cat && category !== cat) visible = false;
    c.parentElement.style.display = visible ? '' : 'none';
  });
}

// ==== DARK/LIGHT MODE DENGAN TRANSISI ====
const body = document.body;
const darkToggle = document.getElementById('darkToggle');
const darkIcon = document.getElementById('darkIcon');
const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function enableTransitionTemporarily(){
  if(prefersReduced) return;
  body.classList.add('theme-transition');
  setTimeout(()=>body.classList.remove('theme-transition'), 550);
}

function applyTheme(theme){
  theme = (theme==='dark')?'dark':'light';
  enableTransitionTemporarily();
  if(theme==='dark'){
    body.classList.add('dark-mode');
    darkIcon.className='bi bi-moon-stars-fill';
  }else{
    body.classList.remove('dark-mode');
    darkIcon.className='bi bi-sun-fill';
  }
  localStorage.setItem('theme',theme);
}

const saved = localStorage.getItem('theme') || 'light';
if(saved==='dark'){body.classList.add('dark-mode');darkIcon.className='bi bi-moon-stars-fill';}
else{body.classList.remove('dark-mode');darkIcon.className='bi bi-sun-fill';}

darkToggle.addEventListener('click',()=>{
  const isDark = body.classList.contains('dark-mode');
  applyTheme(isDark?'light':'dark');
});
</script>
</body>
</html>
