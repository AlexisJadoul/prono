<?php
require_once __DIR__.'/db.php';

function cu_search_teams(PDO $pdo, string $q): array {
  $q = trim($q); if ($q==='') return [];
  $st=$pdo->prepare("SELECT DISTINCT home_team AS team FROM matches WHERE home_team LIKE ?
                     UNION SELECT DISTINCT away_team FROM matches WHERE away_team LIKE ? ORDER BY team ASC LIMIT 20");
  $like='%'.$q.'%'; $st->execute([$like,$like]); return $st->fetchAll(PDO::FETCH_COLUMN);
}

function cu_h2h(PDO $pdo, string $a, string $b, int $limitLast=5): array {
  $st=$pdo->prepare("SELECT kickoff,home_team,away_team,home_score,away_score,stage
                     FROM matches WHERE (home_team=:a AND away_team=:b) OR (home_team=:b AND away_team=:a)
                     ORDER BY kickoff DESC");
  $st->execute([':a'=>$a,':b'=>$b]); $rows=$st->fetchAll(PDO::FETCH_ASSOC);
  $tot=['A'=>0,'B'=>0,'N'=>0,'bpA'=>0,'bpB'=>0]; $last=[];
  foreach($rows as $r){
    $hs=$r['home_score']; $as=$r['away_score']; if($hs===null||$as===null) continue;
    $isAhome=($r['home_team']===$a); $bpA=$isAhome?$hs:$as; $bpB=$isAhome?$as:$hs;
    $tot['bpA']+=$bpA; $tot['bpB']+=$bpB;
    if($bpA>$bpB) $tot['A']++; elseif($bpA<$bpB) $tot['B']++; else $tot['N']++;
    if(count($last)<$limitLast){
      $last[]=['date'=>$r['kickoff'],'affiche'=>$r['home_team'].' - '.$r['away_team'],'score'=>"$hs - $as",'stage'=>$r['stage']];
    }
  }
  return ['summary'=>$tot,'last'=>$last,'total_matches'=>count($rows)];
}

function cu_recent_form(PDO $pdo, string $team, int $n=5): array {
  $st=$pdo->prepare("SELECT kickoff,home_team,away_team,home_score,away_score FROM matches WHERE (home_team=? OR away_team=?) AND is_finished=1 ORDER BY kickoff DESC LIMIT ".$n);
  $st->execute([$team,$team]); $rows=$st->fetchAll(PDO::FETCH_ASSOC);
  $form=[]; foreach($rows as $r){
    $isHome=$r['home_team']===$team; $hs=(int)$r['home_score']; $as=(int)$r['away_score'];
    $res=$hs===$as?'N':($hs>$as?($isHome?'V':'D'):($isHome?'D':'V')); $opp=$isHome?$r['away_team']:$r['home_team'];
    $form[]=['date'=>$r['kickoff'],'opp'=>$opp,'home'=>$r['home_team'],'away'=>$r['away_team'],'score'=>"$hs - $as",'res'=>$res];
  }
  return $form;
}
