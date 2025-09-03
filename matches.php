<?php
require_once __DIR__.'/header.php';
$pdo = pdo();
$st = $pdo->query("SELECT * FROM matches ORDER BY kickoff ASC");
?>
<div class="card">
  <h2>Tous les matchs</h2>
  <table>
    <tr><th>Phase</th><th>J</th><th>Date</th><th>Match</th><th>Score</th><th>Statut</th></tr>
    <?php while ($m = $st->fetch(PDO::FETCH_ASSOC)): ?>
      <?php
        $score = ($m['home_score']===null || $m['away_score']===null) ? '-' : ((int)$m['home_score'].' - '.(int)$m['away_score']);
        $statut = $m['is_finished'] ? "<span class='ok'>Terminé</span>" : "<span class='warn'>À jouer</span>";
      ?>
      <tr>
        <td><?=h($m['stage'])?></td>
        <td><?= $m['md'] ? 'J'.(int)$m['md'] : '-' ?></td>
        <td><?=date('d/m/Y H:i', strtotime($m['kickoff']))?></td>
        <td><?=h($m['home_team'])?> vs <?=h($m['away_team'])?></td>
        <td><?=$score?></td>
        <td><?=$statut?></td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>
<?php require_once __DIR__.'/footer.php'; ?>
