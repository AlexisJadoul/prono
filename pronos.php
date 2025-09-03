<?php
require_once __DIR__.'/header.php';
require_once __DIR__.'/lib.php';

$pdo = pdo();
$me  = current_user();
if (!$me) { header('Location: login.php?next=pronos.php'); exit; }
$uid = (int)$me['id'];

/* --------- sauvegarde des pronos --------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // anti CSRF basique: tu peux ajouter csrf_token() si tu veux
  $ids = $_POST['match_id'] ?? [];
  if (!is_array($ids)) $ids = [];

  $qMatch = $pdo->prepare("SELECT kickoff, is_finished FROM matches WHERE id=?");
  $qSel   = $pdo->prepare("SELECT id FROM predictions WHERE user_id=? AND match_id=?");
  $qIns   = $pdo->prepare("INSERT INTO predictions(user_id, match_id, pred_home, pred_away, pick) VALUES(?,?,?,?,?)");
  $qUpd   = $pdo->prepare("UPDATE predictions SET pred_home=?, pred_away=?, pick=? WHERE user_id=? AND match_id=?");
  $qDel   = $pdo->prepare("DELETE FROM predictions WHERE user_id=? AND match_id=?");

  foreach ($ids as $mid) {
    $mid = (int)$mid;

    // verrou: pas de save si match fini ou verrouillé
    $qMatch->execute([$mid]);
    $m = $qMatch->fetch(PDO::FETCH_ASSOC);
    if (!$m) continue;

    $locked = false;
    if ((int)$m['is_finished'] === 1) {
      $locked = true;
    } else {
      $ko = strtotime($m['kickoff']);
      if ($ko !== false && (time() >= $ko - (int)LOCK_MINUTES_BEFORE*60)) {
        $locked = true;
      }
    }
    if ($locked) continue;

    // récup du POST pour ce match
    $ph = $_POST['ph'][$mid] ?? '';
    $pa = $_POST['pa'][$mid] ?? '';
    $pk = $_POST['pick'][$mid] ?? '';

    // normalisation
    $ph = ($ph === '' ? null : (int)$ph);
    $pa = ($pa === '' ? null : (int)$pa);
    $pk = in_array($pk, ['H','D','A'], true) ? $pk : null;

    // si scores saisis, on ignore pick
    if ($ph !== null && $pa !== null) $pk = null;

    // si rien du tout -> delete éventuel
    if ($ph === null && $pa === null && $pk === null) {
      $qDel->execute([$uid, $mid]);
      continue;
    }

    // upsert
    $qSel->execute([$uid, $mid]);
    if ($qSel->fetchColumn()) {
      $qUpd->execute([$ph, $pa, $pk, $uid, $mid]);
    } else {
      $qIns->execute([$uid, $mid, $ph, $pa, $pk]);
    }
  }
  header('Location: pronos.php?saved=1&md='.(int)($_GET['md'] ?? 0));
  exit;
}

/* --------- filtre: journée md --------- */
$mds = $pdo->query("SELECT DISTINCT md FROM matches WHERE md IS NOT NULL ORDER BY md")->fetchAll(PDO::FETCH_COLUMN);
$defaultMd = $mds ? (int)min($mds) : 1;

// on essaye de prendre la prochaine journée non terminée
$nextMd = $pdo->query("SELECT md FROM matches WHERE is_finished=0 AND md IS NOT NULL ORDER BY md LIMIT 1")->fetchColumn();
if ($nextMd !== false) $defaultMd = (int)$nextMd;

$md = isset($_GET['md']) ? (int)$_GET['md'] : $defaultMd;
if ($mds && !in_array($md, array_map('intval',$mds), true)) $md = $defaultMd;

/* --------- récup des matches + pronos existants --------- */
$stM = $pdo->prepare("
  SELECT id, kickoff, home_team, away_team, is_finished
  FROM matches
  WHERE md = ?
  ORDER BY kickoff, id
");
$stM->execute([$md]);
$matches = $stM->fetchAll(PDO::FETCH_ASSOC);

$stP = $pdo->prepare("SELECT match_id, pred_home, pred_away, pick FROM predictions WHERE user_id=? AND match_id IN (%s)");
$predByMatch = [];
if ($matches) {
  $ids = array_column($matches, 'id');
  $in  = implode(',', array_fill(0, count($ids), '?'));
  $st  = $pdo->prepare("SELECT match_id, pred_home, pred_away, pick FROM predictions WHERE user_id=? AND match_id IN ($in)");
  $st->execute(array_merge([$uid], $ids));
  while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $predByMatch[(int)$r['match_id']] = $r;
  }
}

/* --------- affichage --------- */
?>
<div class="card">
  <h2>Mes pronos - J<?= (int)$md ?></h2>
  <?php if (!empty($_GET['saved'])): ?><p class="ok">Pronos enregistrés.</p><?php endif; ?>

  <?php if ($mds): ?>
    <div class="row" style="gap:6px;flex-wrap:wrap;margin:8px 0">
      <?php foreach ($mds as $x): $act=((int)$x===$md)?"style='font-weight:bold'":""; ?>
        <a class="badge" <?=$act?> href="pronos.php?md=<?=$x?>">J<?=$x?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <?php if (!$matches): ?>
    <p class="muted">Aucun match pour cette journée.</p>
  <?php else: ?>
    <form method="post">
      <table>
        <tr>
          <th>Date - heure</th>
          <th>Match</th>
          <th style="width:110px">Score</th>
          <th style="width:160px">Vainqueur (1N2)</th>
          <th>Etat</th>
        </tr>

        <?php foreach ($matches as $m):
          $mid = (int)$m['id'];
          $ko  = strtotime($m['kickoff']);
          $locked = ($m['is_finished'] == 1) || ($ko !== false && time() >= $ko - (int)LOCK_MINUTES_BEFORE*60);

          $p = $predByMatch[$mid] ?? ['pred_home'=>null,'pred_away'=>null,'pick'=>null];
          $ph = $p['pred_home']; $pa = $p['pred_away']; $pk = $p['pick'];
        ?>
        <tr>
          <td><?= h(date('d/m/Y H:i', strtotime($m['kickoff']))) ?></td>
          <td><?= h($m['home_team']) ?> - <?= h($m['away_team']) ?></td>

          <td>
            <input type="hidden" name="match_id[]" value="<?=$mid?>">
            <input type="number" name="ph[<?=$mid?>]" min="0" style="width:42px" value="<?=($ph!==null?$ph:'')?>" <?= $locked?'disabled':'' ?>>
            -
            <input type="number" name="pa[<?=$mid?>]" min="0" style="width:42px" value="<?=($pa!==null?$pa:'')?>" <?= $locked?'disabled':'' ?>>
          </td>

          <td>
            <?php
              $dH = $locked?'disabled':'';
              $isScore = ($ph !== null && $pa !== null);
            ?>
            <label><input type="radio" name="pick[<?=$mid?>]" value="H" <?= $pk==='H' && !$isScore?'checked':'' ?> <?=$dH?>> 1</label>
            <label><input type="radio" name="pick[<?=$mid?>]" value="D" <?= $pk==='D' && !$isScore?'checked':'' ?> <?=$dH?>> N</label>
            <label><input type="radio" name="pick[<?=$mid?>]" value="A" <?= $pk==='A' && !$isScore?'checked':'' ?> <?=$dH?>> 2</label>
            <label><input type="radio" name="pick[<?=$mid?>]" value=""  <?= ($pk===null || $isScore)?'checked':'' ?> <?=$dH?>> -</label>
          </td>

          <td>
            <?php if ($m['is_finished'] == 1): ?>
              <span class="muted">Terminé</span>
            <?php elseif ($locked): ?>
              <span class="muted">Verrouillé (<?= (int)LOCK_MINUTES_BEFORE ?> min avant)</span>
            <?php else: ?>
              <span class="ok">Ouvert</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>

      <div style="margin-top:10px">
        <button type="submit">Enregistrer mes pronos</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php require_once __DIR__.'/footer.php'; ?>
