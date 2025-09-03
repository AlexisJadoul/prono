<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/lib.php';

$pdo = pdo();

/* helpers locaux - au cas où */
if (!function_exists('is_admin')) {
  function is_admin(): bool {
    // admin si flag BDD, ou si session admin activée par mot de passe ADMIN_PASS
    $me = current_user();
    if ($me && !empty($me['is_admin'])) return true;
    if (!empty($_SESSION['is_admin'])) return true;
    return false;
  }
}
if (!function_exists('flash')) {
  function flash(string $msg, string $type='ok'): void {
    $_SESSION['flash'][] = ['t'=>$type,'m'=>$msg];
  }
}

/* actions - faites AVANT l'affichage */
if (!is_admin() && $_SERVER['REQUEST_METHOD']==='POST' && !isset($_GET['action'])) {
  // demande d'accès admin par mot de passe simple
  $pass = $_POST['pass'] ?? '';
  if ($pass === ADMIN_PASS) { $_SESSION['is_admin'] = true; flash('Mode admin activé'); }
  else { flash('Mot de passe admin invalide','err'); }
  header('Location: admin.php'); exit;
}

if (is_admin() && ($_GET['action'] ?? '') === 'add' && $_SERVER['REQUEST_METHOD']==='POST') {
  $stage = trim($_POST['stage'] ?? '');
  $md    = ($_POST['md'] === '' ? null : (int)$_POST['md']);
  $kick  = trim($_POST['kickoff'] ?? '');
  $home  = trim($_POST['home'] ?? '');
  $away  = trim($_POST['away'] ?? '');

  // normalise le datetime-local
  if ($kick && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $kick)) {
    $kick = str_replace('T',' ',$kick).':00';
  }

  try {
    if ($stage && $kick && $home && $away) {
      $pdo->prepare("INSERT INTO matches(stage,md,kickoff,home_team,away_team) VALUES(?,?,?,?,?)")
          ->execute([$stage,$md,$kick,$home,$away]);
      flash('Match ajouté');
    } else {
      flash('Champs manquants','err');
    }
  } catch(Throwable $e){
    flash('Erreur SQL: '.$e->getMessage(),'err');
  }
  header('Location: admin.php'); exit;
}

if (is_admin() && ($_GET['action'] ?? '') === 'set_score' && $_SERVER['REQUEST_METHOD']==='POST') {
  $id  = (int)($_POST['match_id'] ?? 0);
  $hs  = ($_POST['home_score']===''?null:(int)$_POST['home_score']);
  $as  = ($_POST['away_score']===''?null:(int)$_POST['away_score']);
  $fin = isset($_POST['is_finished']) ? 1 : 0;

  try {
    $pdo->prepare("UPDATE matches SET home_score=?, away_score=?, is_finished=? WHERE id=?")
        ->execute([$hs,$as,$fin,$id]);
    flash('Score mis à jour');
  } catch(Throwable $e){
    flash('Erreur SQL: '.$e->getMessage(),'err');
  }
  header('Location: admin.php'); exit;
}

/* affichage */
require_once __DIR__.'/header.php';
?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="card" style="margin-top:10px">
    <?php foreach ($_SESSION['flash'] as $f): ?>
      <p class="<?= $f['t']==='err'?'err':'ok' ?>"><?= h($f['m']) ?></p>
    <?php endforeach; unset($_SESSION['flash']); ?>
  </div>
<?php endif; ?>

<?php if (!is_admin()): ?>
  <div class="card">
    <h2>Admin</h2>
    <form method="post" class="row">
      <div class="col"><input type="password" name="pass" placeholder="Mot de passe admin" required></div>
      <div class="col" style="max-width:160px"><button type="submit">Entrer</button></div>
    </form>
    <p class="muted">Change ADMIN_PASS dans <code>config.php</code>.</p>
  </div>
  <?php require_once __DIR__.'/footer.php'; exit; ?>
<?php endif; ?>

<div class="card">
  <h2>Ajouter un match manuellement</h2>
  <form method="post" action="admin.php?action=add" class="row" style="gap:8px;align-items:end">
    <div class="col">
      <label>Phase</label>
      <input name="stage" value="Phase de ligue" required>
    </div>
    <div class="col" style="max-width:120px">
      <label>Journée</label>
      <input name="md" type="number" min="1" max="8" placeholder="1-8">
    </div>
    <div class="col" style="max-width:220px">
      <label>Date - heure</label>
      <input type="datetime-local" name="kickoff" required>
    </div>
    <div class="col">
      <label>Domicile</label>
      <input name="home" placeholder="Domicile" required>
    </div>
    <div class="col">
      <label>Extérieur</label>
      <input name="away" placeholder="Extérieur" required>
    </div>
    <div class="col" style="max-width:160px">
      <button type="submit">Ajouter</button>
    </div>
  </form>
</div>

<div class="card">
  <h2>Intégration API football-data.org</h2>
  <form method="post" action="api_fetch.php" class="row" style="gap:8px;align-items:end">
    <div class="col" style="max-width:130px">
      <label>Saison</label>
      <input name="season" type="number" value="<?=date('Y')?>" required>
    </div>
    <div class="col" style="max-width:190px">
      <label>Status</label>
      <select name="status">
        <option value="">Tous</option>
        <option>SCHEDULED</option>
        <option>LIVE</option>
        <option>FINISHED</option>
        <option>POSTPONED</option>
      </select>
    </div>
    <div class="col" style="max-width:170px">
      <label>dateFrom</label>
      <input name="dateFrom" type="date">
    </div>
    <div class="col" style="max-width:170px">
      <label>dateTo</label>
      <input name="dateTo" type="date">
    </div>
    <div class="col" style="max-width:240px">
      <button type="submit">Importer - mettre à jour</button>
    </div>
  </form>

  <form method="post" action="api_sync.php" style="margin-top:10px">
    <button type="submit">Synchroniser les résultats des matchs importés</button>
  </form>
</div>

<?php $st = $pdo->query("SELECT * FROM matches ORDER BY kickoff ASC"); ?>
<div class="card">
  <h2>Matchs - scores</h2>
  <table>
    <tr>
      <th>#</th><th>Phase</th><th>J</th><th>Date</th><th>Match</th>
      <th>Score</th><th>Fini</th><th>Action</th>
    </tr>
    <?php while ($m = $st->fetch(PDO::FETCH_ASSOC)): ?>
      <tr>
        <td><?= (int)$m['id'] ?></td>
        <td><?= h($m['stage']) ?></td>
        <td><?= $m['md'] ? 'J'.(int)$m['md'] : '-' ?></td>
        <td><?= h(date('d/m/Y H:i', strtotime($m['kickoff']))) ?></td>
        <td><?= h($m['home_team']) ?> vs <?= h($m['away_team']) ?></td>
        <td>
          <form method="post" action="admin.php?action=set_score" class="row" style="gap:6px">
            <input type="hidden" name="match_id" value="<?= (int)$m['id'] ?>">
            <input style="width:60px" name="home_score" type="number" min="0" value="<?= h((string)$m['home_score']) ?>"> -
            <input style="width:60px" name="away_score" type="number" min="0" value="<?= h((string)$m['away_score']) ?>">
        </td>
        <td>
          <label>
            <input type="checkbox" name="is_finished" <?= $m['is_finished'] ? 'checked' : '' ?>> fini
          </label>
        </td>
        <td><button type="submit">Enregistrer</button></form></td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>

<?php require_once __DIR__.'/footer.php'; ?>
