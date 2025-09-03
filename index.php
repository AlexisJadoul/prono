<?php
require_once __DIR__.'/header.php';
$pdo = pdo();

$sql = "
SELECT u.username,
       COALESCE(SUM(
         CASE
           WHEN m.home_score IS NULL OR m.away_score IS NULL THEN NULL
           WHEN p.pred_home IS NOT NULL AND p.pred_away IS NOT NULL THEN
             CASE
               WHEN m.home_score = p.pred_home AND m.away_score = p.pred_away THEN ".PTS_EXACT."
               WHEN SIGN(m.home_score - m.away_score) = SIGN(p.pred_home - p.pred_away) THEN ".PTS_TENDANCE."
               ELSE 0
             END
           WHEN p.pick IS NOT NULL THEN
             CASE
               WHEN (m.home_score > m.away_score AND p.pick='H')
                 OR (m.home_score = m.away_score AND p.pick='D')
                 OR (m.home_score < m.away_score AND p.pick='A') THEN ".PTS_TENDANCE."
               ELSE 0
             END
           ELSE 0
         END
       ),0) AS pts,
       COUNT(CASE WHEN m.is_finished=1 THEN 1 END) AS matchs_joues,
       COUNT(p.id) AS pronos_places
FROM users u
LEFT JOIN predictions p ON p.user_id = u.id
LEFT JOIN matches m ON m.id = p.match_id
GROUP BY u.id
ORDER BY pts DESC, u.username ASC";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="card">
  <h2>Classement général</h2>
  <?php if (!$rows): ?>
    <p class="muted">Aucun joueur pour le moment.</p>
  <?php else: ?>
    <table>
      <tr><th>#</th><th>Pseudo</th><th>Points</th><th>Pronos</th><th>Matchs joués</th></tr>
      <?php $rank=1; foreach ($rows as $r): ?>
        <tr>
          <td><?=$rank++?></td>
          <td><a href="player.php?u=<?=urlencode($r['username'])?>"><?=h($r['username'])?></a></td>
          <td><strong><?= (int)$r['pts'] ?></strong></td>
          <td><?= (int)$r['pronos_places'] ?></td>
          <td><?= (int)$r['matchs_joues'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>
<?php require_once __DIR__.'/footer.php'; ?>
