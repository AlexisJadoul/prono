<?php
require_once __DIR__.'/auth.php';
$err = null; $ok = null;

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) { $err='Jeton invalide'; }
  else {
    $u = trim($_POST['username'] ?? '');
    $p = (string)($_POST['password'] ?? '');
    $e = trim($_POST['email'] ?? '') ?: null;
    [$succ,$msg] = register_local($u,$p,$e);
    if ($succ) { $ok='Compte créé - tu peux te connecter.'; }
    else $err=$msg;
  }
}
?>
<!doctype html><meta charset="utf-8">
<title>Créer un compte</title>
<link rel="stylesheet" href="style.css">
<div class="card" style="max-width:460px;margin:40px auto">
  <h2>Créer un compte local</h2>
  <?php if($err): ?><p class="err"><?=$err?></p><?php endif; ?>
  <?php if($ok): ?><p class="ok"><?=$ok?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token(),ENT_QUOTES)?>">
    <label>Nom d’utilisateur</label>
    <input name="username" required placeholder="3 à 32 caractères">
    <label>Email (facultatif)</label>
    <input type="email" name="email" placeholder="ex: toi@mail.com">
    <label>Mot de passe</label>
    <input type="password" name="password" required placeholder="min 6 caractères">
    <button type="submit">Créer le compte</button>
  </form>
  <hr>
  <a class="btn-twitch" href="twitch_login.php">S’inscrire avec Twitch</a>
</div>
