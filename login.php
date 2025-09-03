<?php
require_once __DIR__.'/auth.php';
$err = null;
$next = $_GET['next'] ?? 'index.php';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) { $err='Jeton invalide'; }
  else {
    $u = trim($_POST['username'] ?? '');
    $p = (string)($_POST['password'] ?? '');
    if ($u==='' || $p==='') $err='Champs requis';
    elseif (login_with_password($u,$p)) { header('Location: '.$next); exit; }
    else $err='Identifiants invalides';
  }
}
?>
<!doctype html><meta charset="utf-8">
<title>Connexion</title>
<link rel="stylesheet" href="style.css">
<div class="card" style="max-width:460px;margin:40px auto">
  <h2>Connexion</h2>
  <?php if($err): ?><p class="err"><?=$err?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token(),ENT_QUOTES)?>">
    <input type="hidden" name="next" value="<?=htmlspecialchars($next,ENT_QUOTES)?>">
    <label>Nom d’utilisateur</label>
    <input name="username" autocomplete="username" required>
    <label>Mot de passe</label>
    <input type="password" name="password" autocomplete="current-password" required>
    <button type="submit">Connexion</button>
  </form>
  <hr>
  <a class="btn-twitch" href="twitch_login.php?next=<?=urlencode($next)?>">Se connecter / S’inscrire avec Twitch</a>
  <p class="muted" style="margin-top:8px">
    Pas de compte ? <a href="register.php">Créer un compte local</a>
  </p>
</div>
