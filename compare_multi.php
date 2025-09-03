<?php
require_once __DIR__.'/header.php';
$pdo = pdo();

function _init_team(&$a,$n){ if(!isset($a[$n])) $a[$n]=['team'=>$n,'PJ'=>0,'G'=>0,'N'=>0,'P'=>0,'BP'=>0,'BC'=>0,'Diff'=>0,'Pts'=>0,'AG'=>0,'AW'=>0]; }
function _sort_tbl($t){ usort($t,function($x,$y){ foreach(['Pts','Diff','BP','AG','AW'] as $k){ if($x[$k]!==$y[$k]) return $y[$k]<=>$x[$k]; } return strcasecmp($x['team'],$y['team']); }); return $t; }
function _map_rank($t){ $r=1;$m=[]; foreach($t as $x){ $m[$x['team']]=['rank'=>$r++,'pts'=>$x['Pts'],'diff'=>$x['Diff']]; } return $m; }

$mode = $_GET['mode'] ?? 'to_date';
if(!in_array($mode, ['to_date','all'], true)) $mode = 'to_date';

$cutRaw = trim($_GET['cut'] ?? 'now');
$cut = ($cutRaw===''||strtolower($cutRaw)==='now') ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', strtotime($cutRaw));

$uParam = $_GET['u'] ?? [];
if(!is_array($uParam)) $uParam = explode(',', $uParam);
$names = array_values(array_unique(array_filter(array_map('trim', $uParam), fn($x)=>$x!=='')));
$names = array_slice($names, 0, 5);
$stAll = $pdo->query("SELECT username FROM users ORDER BY username");
$allUsers = $stAll->fetchAll(PDO::FETCH_COLUMN);

echo "<div class='card'>";
echo "  <h2>Comparer des joueurs</h2>";
echo "  <form method='get'>";
echo "    <p class='muted'>Choisissez jusqu'à 5 joueurs (Ctrl/Cmd pour sélectionner plusieurs)</p>";
echo "    <input type='hidden' name='mode' value='".h($mode)."'>";
if($mode==='to_date') echo "    <input type='hidden' name='cut' value='".h($cutRaw)."'>";
echo "    <select name='u[]' multiple size='10'>";
foreach($allUsers as $u){
  $sel = in_array($u, $names, true) ? " selected" : "";
  echo "      <option value=\"".h($u)."\"$sel>".h($u)."</option>";
}
echo "    </select><br>";
echo "    <button type='submit'>Comparer</button>";
echo "  </form>";
echo "</div>";

if(!$names){
  require_once __DIR__.'/footer.php';
  exit;
}

$predMaps = [];
$validNames = [];
foreach($names as $uname){
  $st=$pdo->prepare("SELECT id,username FROM users WHERE username=?");
  $st->execute([$uname]);
  $user=$st->fetch(PDO::FETCH_ASSOC);
  if(!$user){
    echo "<div class='card'><p class='err'>Utilisateur ".h($uname)." inconnu</p></div>";
    continue;
  }
  $uid=(int)$user['id'];
  if($mode==='all'){
    $q=$pdo->prepare("SELECT m.home_team,m.away_team,p.pred_home,p.pred_away,p.pick FROM matches m JOIN predictions p ON p.match_id=m.id WHERE p.user_id=? AND m.md IS NOT NULL");
    $q->execute([$uid]);
  }else{
    $q=$pdo->prepare("SELECT m.home_team,m.away_team,p.pred_home,p.pred_away,p.pick FROM matches m JOIN predictions p ON p.match_id=m.id WHERE p.user_id=? AND m.md IS NOT NULL AND m.is_finished=1 AND m.kickoff<=?");
    $q->execute([$uid,$cut]);
  }
  $pred=[];
  while($r=$q->fetch(PDO::FETCH_ASSOC)){
    $h=$r['home_team']; $a=$r['away_team'];
    if($r['pred_home']!==null && $r['pred_away']!==null && $r['pred_home']!=='' && $r['pred_away']!==''){ $ph=(int)$r['pred_home']; $pa=(int)$r['pred_away']; }
    elseif($r['pick']==='H'){ $ph=1;$pa=0; } elseif($r['pick']==='A'){ $ph=0;$pa=1; } elseif($r['pick']==='D'){ $ph=0;$pa=0; } else { continue; }
    _init_team($pred,$h); _init_team($pred,$a);
    $pred[$h]['PJ']++; $pred[$a]['PJ']++;
    $pred[$h]['BP']+=$ph; $pred[$h]['BC']+=$pa; $pred[$a]['BP']+=$pa; $pred[$a]['BC']+=$ph;
    $pred[$h]['Diff']=$pred[$h]['BP']-$pred[$h]['BC']; $pred[$a]['Diff']=$pred[$a]['BP']-$pred[$a]['BC'];
    $pred[$a]['AG']+=$pa;
    if($ph>$pa){ $pred[$h]['G']++; $pred[$h]['Pts']+=3; $pred[$a]['P']++; }
    elseif($ph<$pa){ $pred[$a]['G']++; $pred[$a]['Pts']+=3; $pred[$a]['AW']++; $pred[$h]['P']++; }
    else { $pred[$h]['N']++; $pred[$h]['Pts']+=1; $pred[$a]['N']++; $pred[$a]['Pts']+=1; }
  }
  $predTbl=_sort_tbl(array_values($pred));
  $predMaps[$user['username']] = _map_rank($predTbl);
  $validNames[] = $user['username'];
}

if(!$predMaps){
  require_once __DIR__.'/footer.php';
  exit;
}

if($mode==='all'){
  $st2=$pdo->prepare("SELECT home_team,away_team,home_score,away_score FROM matches WHERE md IS NOT NULL AND is_finished=1");
  $st2->execute();
}else{
  $st2=$pdo->prepare("SELECT home_team,away_team,home_score,away_score FROM matches WHERE md IS NOT NULL AND is_finished=1 AND kickoff<=?");
  $st2->execute([$cut]);
}
$real=[];
while($m=$st2->fetch(PDO::FETCH_ASSOC)){
  $h=$m['home_team']; $a=$m['away_team']; $hs=$m['home_score']; $as=$m['away_score'];
  if($hs===null||$as===null) continue;
  _init_team($real,$h); _init_team($real,$a);
  $real[$h]['PJ']++; $real[$a]['PJ']++;
  $real[$h]['BP']+=$hs; $real[$h]['BC']+=$as; $real[$a]['BP']+=$as; $real[$a]['BC']+=$hs;
  $real[$h]['Diff']=$real[$h]['BP']-$real[$h]['BC']; $real[$a]['Diff']=$real[$a]['BP']-$real[$a]['BC'];
  $real[$a]['AG']+=$as;
  if($hs>$as){ $real[$h]['G']++; $real[$h]['Pts']+=3; $real[$a]['P']++; }
  elseif($hs<$as){ $real[$a]['G']++; $real[$a]['Pts']+=3; $real[$a]['AW']++; $real[$h]['P']++; }
  else { $real[$h]['N']++; $real[$h]['Pts']+=1; $real[$a]['N']++; $real[$a]['Pts']+=1; }
}
$realTbl=_sort_tbl(array_values($real));
$realMap=_map_rank($realTbl);

$teams = array_keys($realMap);
foreach($predMaps as $map){ $teams = array_merge($teams, array_keys($map)); }
$teams = array_values(array_unique($teams));
usort($teams,function($a,$b) use ($realMap){ $ar=$realMap[$a]['rank']??999; $br=$realMap[$b]['rank']??999; if($ar!==$br) return $ar<=>$br; return strcasecmp($a,$b); });

echo "<div class='card'>";
if($mode==='all'){
  echo "  <h2>Comparaison tous les pronos</h2>";
  echo "  <p class='muted'>Basé sur tous les matches disponibles. Δ rang = reel - predit.</p>";
}else{
  echo "  <h2>Comparaison a date</h2>";
  echo "  <p class='muted'>Date de coupe: <strong>".h($cut)."</strong>. Δ rang = reel - predit.</p>";
}
$qsToDate = http_build_query(['u'=>$names,'mode'=>'to_date','cut'=>$cutRaw]);
$qsAll = http_build_query(['u'=>$names,'mode'=>'all']);
echo "  <p style='margin-top:6px'><a class='badge' href='compare_multi.php?".$qsToDate."'>A date</a> ";
echo "  <a class='badge' href='compare_multi.php?".$qsAll."'>Tous les pronos</a></p>";
echo "</div>";

echo "<div class='card'>";
echo "<table>";
echo "  <tr><th>Equipe</th><th>Rang reel</th>";
foreach($validNames as $u){ echo "<th>".h($u)."</th>"; }
echo "</tr>";
foreach($teams as $t){
  $rr=$realMap[$t]['rank']??null;
  echo "  <tr><td>".h($t)."</td><td>".($rr?:'—')."</td>";
  foreach($validNames as $u){
    $pr=$predMaps[$u][$t]['rank']??null;
    $dr=($pr!==null && $rr!==null)?($rr-$pr):null;
    $drTxt=$dr===null?'—':($dr>0?'+'.$dr:$dr);
    $style='';
    if($dr!==null){ if($dr<0) $style=" style='background:#0b2a1b'"; elseif($dr>0) $style=" style='background:#2a0b0b'"; }
    $cell=$pr===null?'—':$pr."<br><span class='muted'>".$drTxt."</span>";
    echo "<td$style>".$cell."</td>";
  }
  echo "</tr>";
}
echo "</table>";
echo "</div>";

require_once __DIR__.'/footer.php';
