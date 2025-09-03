<?php
require_once __DIR__.'/header.php';
$pdo = pdo();

// Récup liste des groupes présents en base
$groups = $pdo->query("SELECT DISTINCT grp FROM matches WHERE grp IS NOT NULL AND grp <> '' ORDER BY grp ASC")->fetchAll(PDO::FETCH_COLUMN);
$grpFilter = isset($_GET['grp']) ? trim($_GET['grp']) : '';

$params = [];
$sql = "SELECT m.*
        FROM matches m
        WHERE m.grp IS NOT NULL AND m.grp <> ''";
if ($grpFilter !== '') {
  $sql .= " AND m.grp = ?";
  $params[] = $grpFilter;
}
$sql .= " ORDER BY m.grp ASC, m.kickoff ASC";

$st = $pdo->prepare($sql);
$st->execute($params);

// Bucket par groupe
$byGroup = [];
while ($m = $st->fetch(PDO::FETCH_ASSOC)) {
  $g = $m['grp'] ?: 'Groupe ?';
  if (!isset($byGroup[$g])) $byGroup[$g] = [];
  $byGroup[$g][] = $m;
}
?>

<div class="card">
  <h2>Matchs de poules</h2>
  <form method="get" class="row" style="align-items:flex-end">
    <div class="col">
      <label>Filtrer par groupe</label><br>
      <select name="grp">
        <option value="">Tous les groupes</option>
        <?php foreach ($groups as $g): ?>
          <option value="<?=h($g)?>" <?= $grpFilter===$g ? 'selected' : '' ?>><?=h($g)?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col" style="align-self:end">
      <button type="submit">Appliquer</button>
      <a href="groups.php" class="muted" style="margin-left:8px">Réinitialiser</a>
    </div>
  </form>
  <p class="muted" style="margin-top:6px">Les matchs affichés proviennent de la base. Importe ou mets à jour via Admin → Intégration API.</p>
</div>

<?php if (!$byGroup): ?>
  <div class="card"><p class="muted">Aucun match de poules en base pour l’instant.</p></div>
<?php else: ?>
  <?php ksort($byGroup); foreach ($byGroup as $g => $matches): ?>
    <div class="card">
      <h3><?=h($g)?></h3>
      <table>
        <tr>
          <th>Date</th>
          <th>Match</th>
          <th>Score</th>
          <th>Statut</th>
        </tr>
        <?php foreach ($matches as $m): ?>
          <?php
            $date  = date('d/m/Y H:i', strtotime($m['kickoff']));
            $score = ($m['home_score']===null || $m['away_score']===null) ? '-' : ((int)$m['home_score'].' - '.(int)$m['away_score']);
            $stat  = $m['is_finished'] ? "<span class='ok'>Terminé</span>" : "<span class='warn'>À jouer</span>";
          ?>
          <tr>
            <td><?=$date?></td>
            <td><?=h($m['home_team'])?> vs <?=h($m['away_team'])?></td>
            <td><?=$score?></td>
            <td><?=$stat?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__.'/footer.php'; ?>
