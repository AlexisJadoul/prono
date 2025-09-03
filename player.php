<?php
require_once __DIR__.'/header.php';
$pdo = pdo();

$uname = isset($_GET['u']) ? trim($_GET['u']) : '';
if ($uname==='' && current_user()) $uname=current_user();
$mode = $_GET['mode'] ?? 'to_date'; // to_date ou all
$cutRaw = trim($_GET['cut'] ?? 'now');
$cut = ($cutRaw===''||strtolower($cutRaw)==='now') ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', strtotime($cutRaw));

$st = $pdo->prepare("SELECT id,username FROM users WHERE username=?"); $st->execute([$uname]);
$user = $st->fetch(PDO::FETCH_ASSOC);
if (!$user){ echo "<div class='card'><h2>Classement predit</h2><p class='err'>Utilisateur inconnu</p></div>"; require_once __DIR__.'/footer.php'; exit; }
$uid = (int)$user['id'];

if ($mode==='to_date') {
  $q = $pdo->prepare("SELECT m.home_team,m.away_team,p.pred_home,p.pred_away,p.pick
    FROM matches m JOIN predictions p ON p.match_id=m.id
    WHERE p.user_id=? AND m.md IS NOT NULL AND m.is_finished=1 AND m.kickoff<=?");
  $q->execute([$uid,$cut]);
  $subtitle = "a date - coupe ".$cut;
} else {
  $q = $pdo->prepare("SELECT m.home_team,m.away_team,p.pred_home,p.pred_away,p.pick
    FROM matches m JOIN predictions p ON p.match_id=m.id
    WHERE p.user_id=? AND m.md IS NOT NULL");
  $q->execute([$uid]);
  $subtitle = "tous les pronos";
}

$T=[];
function t_init(&$a,$n){ if(!isset($a[$n])) $a[$n]=['team'=>$n,'PJ'=>0,'G'=>0,'N'=>0,'P'=>0,'BP'=>0,'BC'=>0,'Diff'=>0,'Pts'=>0,'AG'=>0,'AW'=>0]; }
while($r=$q->fetch(PDO::FETCH_ASSOC)){
  $h=$r['home_team']; $a=$r['away_team'];
  if ($r['pred_home']!==null && $r['pred_away']!==null && $r['pred_home']!=='' && $r['pred_away']!==''){ $ph=(int)$r['pred_home']; $pa=(int)$r['pred_away']; }
  elseif($r['pick']==='H'){ $ph=1;$pa=0; } elseif($r['pick']==='A'){ $ph=0;$pa=1; } elseif($r['pick']==='D'){ $ph=0;$pa=0; } else { continue; }
  t_init($T,$h); t_init($T,$a);
  $T[$h]['PJ']++; $T[$a]['PJ']++;
  $T[$h]['BP']+=$ph; $T[$h]['BC']+=$pa;
  $T[$a]['BP']+=$pa; $T[$a]['BC']+=$ph;
  $T[$h]['Diff']=$T[$h]['BP']-$T[$h]['BC']; $T[$a]['Diff']=$T[$a]['BP']-$T[$a]['BC'];
  $T[$a]['AG']+=$pa;
  if($ph>$pa){ $T[$h]['G']++; $T[$h]['Pts']+=3; $T[$a]['P']++; }
  elseif($ph<$pa){ $T[$a]['G']++; $T[$a]['Pts']+=3; $T[$a]['AW']++; $T[$h]['P']++; }
  else { $T[$h]['N']++; $T[$h]['Pts']+=1; $T[$a]['N']++; $T[$a]['Pts']+=1; }
}
$tbl=array_values($T);
usort($tbl,function($x,$y){ foreach(['Pts','Diff','BP','AG','AW'] as $k){ if($x[$k]!==$y[$k]) return $y[$k]<=>$x[$k]; } return strcasecmp($x['team'],$y['team']); });

echo "<div class='card'><h2>Phase de ligue - classement predit par ".h($user['username'])."</h2>
<p class='muted'>$subtitle. Score pris en priorité, sinon 1X2 converti en 1-0, 0-1 ou 0-0.</p>
<p style='margin-top:6px'>
  <a class='badge' href='player.php?u=".urlencode($user['username'])."&mode=to_date&cut=now'>A date - maintenant</a>
  <a class='badge' href='player.php?u=".urlencode($user['username'])."&mode=all'>Tous les pronos</a>
  <a class='badge' href='compare.php?u=".urlencode($user['username'])."&cut=now'>Comparer avec le reel</a>
  <a class='badge' href='compare_md.php?u=".urlencode($user['username'])."'>Comparer par journee</a>
</p></div>";

if(!$tbl){ echo "<div class='card'><p class='muted'>Aucun prono exploitable.</p></div>"; require_once __DIR__.'/footer.php'; exit; }

echo "<div class='card'><table>
<tr><th>#</th><th>Equipe</th><th>PJ</th><th>G</th><th>N</th><th>P</th><th>BP</th><th>BC</th><th>Diff</th><th>Pts</th></tr>";
$rank=1; foreach($tbl as $t){
  echo "<tr><td>".$rank++."</td><td>".h($t['team'])."</td><td>".$t['PJ']."</td><td>".$t['G']."</td><td>".$t['N']."</td><td>".$t['P']."</td><td>".$t['BP']."</td><td>".$t['BC']."</td><td>".($t['Diff']>=0?'+':'').$t['Diff']."</td><td><strong>".$t['Pts']."</strong></td></tr>";
}
echo "</table></div>";
require_once __DIR__.'/footer.php';
