<?php
// admin_login.php
require_once 'config.php';
require_once 'functions.php';

$err = null;
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if(!verify_csrf_token($_POST['csrf'] ?? '')) die('CSRF invalid');
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$user]);
    $u = $stmt->fetch();
    if($u && password_verify($pass, $u['password_hash'])){
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_name'] = $u['fullname'] ?? $u['username'];
        header('Location: admin_dashboard.php'); exit;
    } else {
        $err = 'Username / password salah';
    }
}
$csrf = generate_csrf_token();
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container d-flex justify-content-center align-items-center" style="min-height:80vh">
  <div class="card p-4 shadow-sm" style="width:380px">
    <h4>Admin Login</h4>
    <?php if($err): ?><div class="alert alert-danger"><?php echo e($err); ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <div class="mb-2"><input name="username" class="form-control" placeholder="username"></div>
      <div class="mb-3"><input type="password" name="password" class="form-control" placeholder="password"></div>
      <button class="btn btn-primary w-100">Login</button>
    </form>
  </div>
</div>
</body></html>
