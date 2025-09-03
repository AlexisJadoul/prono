<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/lib.php';

if (!is_admin()) { header('Location: admin.php'); exit; }

try {
  $pdo = pdo();

  // uniquement les matches provenant de football-data
  $ids = $pdo->query("
    SELECT api_match_id
      FROM matches
     WHERE api_source='football-data'
       AND api_match_id IS NOT NULL
  ")->fetchAll(PDO::FETCH_COLUMN);

  if (!$ids) {
    flash('Aucun match importé via API', 'err');
    header('Location: admin.php'); exit;
  }

  $updated = 0;
  $chunks  = array_chunk($ids, 10); // paquets d'IDs gérés par l'API

  $upd = $pdo->prepare("
    UPDATE matches
       SET home_score=?,
           away_score=?,
           is_finished=?,
           api_status=?,
           last_sync=?
     WHERE api_source='football-data'
       AND api_match_id=?
  ");

  foreach ($chunks as $ch) {
    // ex: /matches?ids=123,456,789
    $data = fd_api_get('/matches', ['ids'=>implode(',', $ch)]);

    foreach (($data['matches'] ?? []) as $m) {
      $id  = (string)($m['id'] ?? '');
      if ($id === '') continue;

      $hs  = $m['score']['fullTime']['home'] ?? null;
      $as  = $m['score']['fullTime']['away'] ?? null;
      $fin = ($m['status'] ?? '') === 'FINISHED' ? 1 : 0;

      $upd->execute([
        $hs,
        $as,
        $fin,
        ($m['status'] ?? null),
        date('Y-m-d H:i:s'),
        $id
      ]);
      $updated++;
    }
  }

  flash("Sync ok: $updated match(s) mis à jour");
} catch (Throwable $e) {
  flash('Erreur sync: '.$e->getMessage(), 'err');
}

header('Location: admin.php');
