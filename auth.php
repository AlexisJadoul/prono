<?php
// auth.php - sessions, helpers d'auth, Twitch, et is_admin()

require_once __DIR__.'/db.php'; // doit définir pdo() et charger config.php si besoin

// - session sécurisée
if (session_status() === PHP_SESSION_NONE) {
  $sess = __DIR__.'/var/sessions';
  if (!is_dir($sess)) @mkdir($sess, 0775, true);
  @session_save_path($sess);
  ini_set('session.cookie_httponly', 1);
  ini_set('session.use_only_cookies', 1);
  // en prod https, tu peux activer:
  // ini_set('session.cookie_secure', 1);
  session_start();
}

/* ------------------ CSRF ------------------ */
function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}
function csrf_check($t): bool {
  return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$t);
}

/* ------------------ Utilisateur courant ------------------ */
function current_user() {
  return $_SESSION['user'] ?? null; // array [id,username,is_admin,twitch_login,twitch_avatar]
}
function require_login() {
  if (!current_user()) {
    $next = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
    header('Location: login.php?next='.$next);
    exit;
  }
}

/* ------------------ Admin global ------------------ */
if (!function_exists('is_admin')) {
  function is_admin(): bool {
    $me = current_user();
    if ($me && !empty($me['is_admin'])) return true;  // admin BDD
    if (!empty($_SESSION['is_admin'])) return true;    // admin via ADMIN_PASS
    return false;
  }
}

/* ------------------ Login par mot de passe ------------------ */
function login_with_password(string $username, string $password): bool {
  $pdo = pdo();
  $st = $pdo->prepare("SELECT * FROM users WHERE username=? LIMIT 1");
  $st->execute([$username]);
  $u = $st->fetch(PDO::FETCH_ASSOC);
  if (!$u || empty($u['pass_hash']) || !password_verify($password, $u['pass_hash'])) return false;

  $_SESSION['user'] = [
    'id'            => (int)$u['id'],
    'username'      => $u['username'],
    'is_admin'      => (int)$u['is_admin'],
    'twitch_login'  => $u['twitch_login'] ?? null,
    'twitch_avatar' => $u['twitch_avatar'] ?? null,
  ];
  return true;
}

/* ------------------ Inscription locale ------------------ */
function register_local(string $username, string $password, ?string $email=null): array {
  $pdo = pdo();
  if (!preg_match('~^[a-z0-9._-]{3,32}$~i', $username)) return [false,'Nom utilisateur invalide'];
  if (strlen($password) < 6) return [false,'Mot de passe trop court'];

  $st = $pdo->prepare("SELECT 1 FROM users WHERE username=?");
  $st->execute([$username]);
  if ($st->fetchColumn()) return [false,'Nom déjà pris'];

  $hash = password_hash($password, PASSWORD_DEFAULT);
  $ins  = $pdo->prepare("INSERT INTO users(username, pass_hash, email, is_admin) VALUES(?,?,?,0)");
  $ins->execute([$username,$hash,$email]);
  return [true,'ok'];
}

/* ------------------ Login via Twitch ------------------ */
function login_with_twitch(array $tw): void {
  // $tw = ['id','login','email','avatar']
  $pdo = pdo();

  $st = $pdo->prepare("SELECT * FROM users WHERE twitch_id=? LIMIT 1");
  $st->execute([$tw['id']]);
  $u = $st->fetch(PDO::FETCH_ASSOC);

  if (!$u) {
    // génère un username unique basé sur login Twitch
    $base = preg_replace('~[^a-z0-9._-]~i','',$tw['login']);
    $name = $base ?: ('tw_'.$tw['id']);
    $try  = $name; $i=1;
    while (true) {
      $chk = $pdo->prepare("SELECT 1 FROM users WHERE username=?");
      $chk->execute([$try]);
      if (!$chk->fetchColumn()) break;
      $try = $name.$i++;
    }
    $name = $try;

    $ins = $pdo->prepare("
      INSERT INTO users(username,email,is_admin,twitch_id,twitch_login,twitch_avatar)
      VALUES(?,?,?,?,?,?)
    ");
    $ins->execute([$name, $tw['email'] ?? null, 0, $tw['id'], $tw['login'], $tw['avatar'] ?? null]);
    $u = $pdo->query("SELECT * FROM users WHERE id=".$pdo->lastInsertId())->fetch(PDO::FETCH_ASSOC);
  } else {
    $upd = $pdo->prepare("UPDATE users SET twitch_login=?, twitch_avatar=?, email=COALESCE(?,email) WHERE id=?");
    $upd->execute([$tw['login'], $tw['avatar'] ?? null, $tw['email'] ?? null, $u['id']]);
  }

  $_SESSION['user'] = [
    'id'            => (int)$u['id'],
    'username'      => $u['username'],
    'is_admin'      => (int)$u['is_admin'],
    'twitch_login'  => $tw['login'] ?? null,
    'twitch_avatar' => $tw['avatar'] ?? null,
  ];
}

/* ------------------ Logout ------------------ */
function logout() {
  $_SESSION['user'] = null;
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'] ?? '', !empty($p['secure']), !empty($p['httponly']));
  }
  session_destroy();
}
