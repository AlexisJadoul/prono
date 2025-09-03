<?php
require_once __DIR__.'/header.php';
$pdo = pdo();

$md = isset($_GET['md']) && $_GET['md'] !== '' ? max(1, min(8, (int)$_GET['md'])) : null;
$team = isset($_GET['team']) ? trim($_GET['team']) : '';

$sql = "SELECT * FROM matches WHERE md IS NOT NULL";
$params = [];
if ($md)   { $sql .= " AND md = ?";   $params[] = $md; }
if ($team) { $sql .= " AND (home_team LIKE ? OR away_team LIKE ?)"; $params[] = "%$team%"; $params[] = "%$team%"; }
$sql .= " ORDER BY md ASC, kickoff ASC";

$st = $pdo->prepare($sql);
$st->execute($params);

$byMd = [];
while ($m = $st->fetch(PDO::FETCH_ASSOC)) {
  $k = (int)$m['md'];
  if (!isset($byMd[$k])) $byMd[$k] = [];
  $byMd[$k][] = $m;
}
?>
<div class="card">
  <h2>Phase de ligue - toutes les journées</h2>
  <form method="get" class="row" style="align-items:flex-end">
    <div class="col">
      <label>Journée</label><br>
      <select name="md">
        <option value="">Toutes</option>
        <?php for ($i=1;$i<=8;$i++): ?>
          <option value="<?=$i?>" <?=($md===$i)?'selected':''?>>J<?=$i?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="col">
      <label>Équipe</label><br>
      <input name="team" value="<?=h($team)?>" placeholder="ex: Paris SG">
    </div>
    <div class="col" style="align-self:end">
      <button type="submit">Filtrer</button>
      <a href="league.php" class="muted" style="margin-left:8px">Réinitialiser</a>
    </div>
  </form>
  <p class="muted" style="margin-top:6px">Format ligue 36 clubs - 8 matchs par équipe. Utilise MD = 1 à 8.</p>
</div>

<?php if (!$byMd): ?>
  <div class="card"><p class="muted">Aucun match trouvé pour ce filtre.</p></div>
<?php else: ?>
  <?php ksort($byMd); foreach ($byMd as $jj => $list): ?>
    <div class="card">
      <h3>J<?=$jj?></h3>
      <table>
        <tr><th>Date</th><th>Match</th><th>Score</th><th>Statut</th></tr>
        <?php foreach ($list as $m): ?>
          <?php
            $date  = date('d/m/Y H:i', strtotime($m['kickoff']));
            $score = ($m['home_score']===null || $m['away_score']===null) ? '-' : ((int)$m['home_score'].' - '.(int)$m['away_score']);
            $stat  = $m['is_finished'] ? "<span class='ok'>Terminé</span>" : "<span class='warn'>À jouer</span>";
          ?>
          <tr>
            <td><?=$date?></td>
            <td><?=h($m['home_team'])?> - <?=h($m['away_team'])?></td>
            <td><?=$score?></td>
            <td><?=$stat?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__.'/footer.php'; ?>
