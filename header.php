<?php
// charge la session + helpers (current_user, require_login, etc.)
require_once __DIR__.'/auth.php';
require_once __DIR__.'/lib.php';   // <— AJOUT
$me = current_user();

?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>LDC</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="top">
  <a href="index.php">Accueil</a>
  <a href="pronos.php">Mes pronos</a>
  <a href="compare_md.php">Comparer par journée</a>

  <?php if ($me): ?>
    <span class="muted">Connecté: <?=htmlspecialchars($me['username'])?></span>
    <?php if (!empty($me['twitch_avatar'])): ?>
      <img src="<?=htmlspecialchars($me['twitch_avatar'])?>" alt="" style="height:24px;border-radius:50%;vertical-align:middle">
    <?php endif; ?>
    <a class="badge" href="logout.php">Se déconnecter</a>
  <?php else: ?>
    <a class="btn-twitch" href="twitch_login.php?next=<?=urlencode($_SERVER['REQUEST_URI'] ?? 'index.php')?>">Se connecter / S’inscrire avec Twitch</a>
    <a class="badge" href="login.php">Connexion</a>
    <a class="badge" href="register.php">Inscription</a>
  <?php endif; ?>
</nav>

<div class="container">
