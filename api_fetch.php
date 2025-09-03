<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/lib.php';
if (!is_admin()) { header('Location: admin.php'); exit; }

try {
  $season = (int)($_POST['season'] ?? date('Y'));
  $status = trim($_POST['status'] ?? '');
  $dateFrom = trim($_POST['dateFrom'] ?? '');
  $dateTo = trim($_POST['dateTo'] ?? '');

  $q = ['season'=>$season];
  if ($status !== '') $q['status']=$status;
  if ($dateFrom !== '') $q['dateFrom']=$dateFrom;
  if ($dateTo !== '') $q['dateTo']=$dateTo;

  $data = fd_api_get('/competitions/'.FD_COMP_CODE.'/matches', $q);
  $pdo = pdo();
  $ins = $pdo->prepare("INSERT INTO matches(stage,md,kickoff,home_team,away_team,home_score,away_score,is_finished,api_source,api_match_id,api_season,api_status,last_sync)
    VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE stage=VALUES(stage), md=VALUES(md), kickoff=VALUES(kickoff), home_team=VALUES(home_team),
      away_team=VALUES(away_team), home_score=VALUES(home_score), away_score=VALUES(away_score), is_finished=VALUES(is_finished),
      api_status=VALUES(api_status), last_sync=VALUES(last_sync)");
  $n=0;
  foreach (($data['matches'] ?? []) as $m) {
    $id = (string)($m['id'] ?? '');
    if ($id === '') continue;
    $utc = $m['utcDate'] ?? null;
    $kick = $utc ? date('Y-m-d H:i:s', strtotime($utc)) : null;
    $home = $m['homeTeam']['name'] ?? '';
    $away = $m['awayTeam']['name'] ?? '';
    $hs = $m['score']['fullTime']['home'] ?? null;
    $as = $m['score']['fullTime']['away'] ?? null;
    $fin = ($m['status'] ?? '') === 'FINISHED' ? 1 : 0;
    $md  = $m['matchday'] ?? null;
    $stage = $m['stage'] ?? 'Phase de ligue';
    $ins->execute([$stage,$md,$kick,$home,$away,$hs,$as,$fin,'football-data',$id,$season,($m['status'] ?? null),date('Y-m-d H:i:s')]);
    $n++;
  }
  flash("Import terminé: $n matchs traités");
} catch(Throwable $e) {
  flash("Erreur API: ".$e->getMessage());
}
header('Location: admin.php');
