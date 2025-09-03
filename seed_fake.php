<?php
require_once __DIR__.'/header.php';
if (!is_admin()) { http_response_code(403); exit('Forbidden'); }
$pdo = pdo();

if (isset($_GET['clear'])) {
  $pdo->exec("DELETE FROM predictions");
  $pdo->exec("DELETE FROM matches");
  $_SESSION['flash'] = "Base vidée (matches + pronos).";
}

/* 36 équipes mock */
$teams = [
  'Manchester City','Real Madrid','Bayern München','Paris SG','Arsenal','Liverpool',
  'Barcelona','Juventus','Inter','AC Milan','Atlético de Madrid','Borussia Dortmund',
  'RB Leipzig','Napoli','Roma','Benfica','Porto','Sporting CP',
  'Ajax','PSV','Feyenoord','Celtic','Rangers','Galatasaray','Fenerbahçe',
  'Shakhtar Donetsk','Braga','Lille','Monaco','Marseille','Leverkusen','Newcastle',
  'Aston Villa','Sevilla','Lazio','Atalanta'
];
$N = count($teams); // 36

/* Fenêtres de dates par journée (mock) */
$windows = [
  1 => ['2025-09-16','2025-09-18'],
  2 => ['2025-10-01','2025-10-03'],
  3 => ['2025-10-22','2025-10-24'],
  4 => ['2025-11-05','2025-11-07'],
  5 => ['2025-11-26','2025-11-28'],
  6 => ['2025-12-10','2025-12-12'],
  7 => ['2026-01-21','2026-01-23'],
  8 => ['2026-02-04','2026-02-06'],
];

/* Aide: date aléatoire dans la fenêtre + horaire type UCL */
function randKick($start, $end){
  $t1 = strtotime($start.' 18:45:00');
  $t2 = strtotime($end.' 21:00:00');
  $t  = rand($t1, $t2);
  // force soit 18:45 soit 21:00
  $hour = (rand(0,1) ? '18:45:00' : '21:00:00');
  return date('Y-m-d', $t).' '.$hour;
}

/* Générateur de calendrier: chaque équipe joue 8 adversaires distincts */
$opponents = array_fill(0, $N, []); // sets d'adversaires joués
$homeCount = array_fill(0, $N, 0);
$awayCount = array_fill(0, $N, 0);

$ins = $pdo->prepare("INSERT INTO matches(stage, md, kickoff, home_team, away_team, home_score, away_score, is_finished)
                      VALUES('Phase de ligue', ?, ?, ?, ?, ?, ?, 1)");

$totalInserted = 0;
for ($md = 1; $md <= 8; $md++) {
  $avail = range(0, $N-1);
  shuffle($avail);
  $pairs = [];

  while (count($avail) >= 2) {
    $t = array_pop($avail);

    // cherche un adversaire jamais rencontré dans cette phase
    $pickIdx = null; $u = null;
    foreach ($avail as $k => $cand) {
      if (empty($opponents[$t][$cand])) { $pickIdx = $k; $u = $cand; break; }
    }
    if ($u === null) {
      // fallback: prend le dernier dispo, même s'il y a déjà eu un match (très rare)
      $u = array_pop($avail);
    } else {
      array_splice($avail, $pickIdx, 1);
    }

    // équilibre domicile/extérieur
    $home = ($homeCount[$t] <= $homeCount[$u]) ? $t : $u;
    $away = ($home === $t) ? $u : $t;
    $homeCount[$home]++; $awayCount[$away]++;

    $opponents[$t][$u] = true;
    $opponents[$u][$t] = true;

    $pairs[] = [$home,$away];
  }

  foreach ($pairs as [$h,$a]) {
    $kick = randKick($windows[$md][0], $windows[$md][1]);
    $hs = rand(0,4);
    $as = rand(0,4);
    $ins->execute([$md, $kick, $teams[$h], $teams[$a], $hs, $as]);
    $totalInserted++;
  }
}

$_SESSION['flash'] = "Seed terminé: $totalInserted matchs insérés. Va voir League et Classement Ligue.";
header('Location: league.php');
