<?php
require_once __DIR__.'/header.php';
require_once __DIR__.'/lib.php';     // pour h()

$pdo = pdo();

/* ---------- garde-fou: matches.md existe ? ---------- */
$hasMd = (int)$pdo->query("
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'matches'
    AND COLUMN_NAME = 'md'
")->fetchColumn();

if (!$hasMd) {
  echo "<div class='card'><h2>Comparaison par journée</h2>
  <p class='err'>La colonne <code>md</code> manque dans la table <code>matches</code> de la base courante.<br>
  Ouvre <a class='badge' href='migrate.php'>migrate.php</a> pour corriger automatiquement, puis recharge cette page.</p>
  </div>";
  require_once __DIR__.'/footer.php';
  exit;
}

/* ---------- helpers de classement ---------- */
function _init(&$a,$n){
  if(!isset($a[$n])){
    $a[$n]=['team'=>$n,'PJ'=>0,'G'=>0,'N'=>0,'P'=>0,'BP'=>0,'BC'=>0,'Diff'=>0,'Pts'=>0,'AG'=>0,'AW'=>0];
  }
}
function _sort($t){
  usort($t,function($x,$y){
    foreach(['Pts','Diff','BP','AG','AW'] as $k){
      if($x[$k]!==$y[$k]) return $y[$k]<=>$x[$k];
    }
    return strcasecmp($x['team'],$y['team']);
  });
  return $t;
}
function _rank($t){
  $r=1; $m=[];
  foreach($t as $x){ $m[$x['team']]=['rank'=>$r++,'pts'=>$x['Pts'],'diff'=>$x['Diff']]; }
  return $m;
}

/* ---------- journées dispo (terminées) ---------- */
$mds = $pdo->query("SELECT DISTINCT md FROM matches WHERE md IS NOT NULL AND is_finished=1 ORDER BY md")
           ->fetchAll(PDO::FETCH_COLUMN);
$lastMd = $mds ? (int)max($mds) : 1;

/* ---------- paramètres ---------- */
$uParam = $_GET['u'] ?? null;
if (is_array($uParam)) $uParam = reset($uParam);
$uParam = trim((string)$uParam);

$me = current_user();
$uname = $uParam !== '' ? $uParam : ($me['username'] ?? '');   // prend le pseudo de la session si ?u= vide

$mode = $_GET['mode'] ?? 'cumul';
if(!in_array($mode,['cumul','solo'],true)) $mode='cumul';

$md = isset($_GET['md']) ? (int)$_GET['md'] : $lastMd;
if ($mds && !in_array($md, array_map('intval',$mds), true)) $md = $lastMd;

/* ---------- utilisateur ciblé ---------- */
if ($uname === '') {
  echo "<div class='card'><h2>Comparaison par journée</h2>
        <p class='err'>Connecte-toi ou passe un utilisateur via <code>?u=Pseudo</code>.</p></div>";
  require_once __DIR__.'/footer.php';
  exit;
}

if (ctype_digit($uname)) {
  $st = $pdo->prepare("SELECT id,username FROM users WHERE id=? LIMIT 1");
  $st->execute([(int)$uname]);
} else {
  $st = $pdo->prepare("SELECT id,username FROM users WHERE username=? LIMIT 1");
  $st->execute([$uname]);
}
$user = $st->fetch(PDO::FETCH_ASSOC);

if(!$user){
  echo "<div class='card'><h2>Comparaison par journée</h2><p class='err'>Utilisateur inconnu</p></div>";
  require_once __DIR__.'/footer.php';
  exit;
}
$uid=(int)$user['id'];

/* ---------- périmètre (solo = uniquement Jx, cumul = J1→Jx) ---------- */
$cond   = ($mode==='solo') ? "m.md = :md" : "m.md <= :md";
$params = [':md'=>$md, ':uid'=>$uid];

/* ---------- prédictions du joueur sur le périmètre ---------- */
$q=$pdo->prepare("
  SELECT m.home_team, m.away_team, p.pred_home, p.pred_away, p.pick
  FROM matches m
  JOIN predictions p ON p.match_id = m.id
  WHERE p.user_id = :uid
    AND m.is_finished = 1
    AND m.md IS NOT NULL
    AND $cond
");
$q->execute($params);
$pred=[];
while($r=$q->fetch(PDO::FETCH_ASSOC)){
  $h=$r['home_team']; $a=$r['away_team'];

  // score exact prioritaire, sinon pick 1N2
  if($r['pred_home']!==null && $r['pred_away']!==null && $r['pred_home']!=='' && $r['pred_away']!==''){
    $ph=(int)$r['pred_home']; $pa=(int)$r['pred_away'];
  } elseif($r['pick']==='H'){
    $ph=1; $pa=0;
  } elseif($r['pick']==='A'){
    $ph=0; $pa=1;
  } elseif($r['pick']==='D'){
    $ph=0; $pa=0;
  } else {
    continue; // pas de prono exploitable
  }

  _init($pred,$h); _init($pred,$a);
  $pred[$h]['PJ']++; $pred[$a]['PJ']++;
  $pred[$h]['BP']+=$ph; $pred[$h]['BC']+=$pa; $pred[$a]['BP']+=$pa; $pred[$a]['BC']+=$ph;
  $pred[$h]['Diff']=$pred[$h]['BP']-$pred[$h]['BC']; $pred[$a]['Diff']=$pred[$a]['BP']-$pred[$a]['BC'];
  $pred[$a]['AG']+=$pa;
  if($ph>$pa){ $pred[$h]['G']++; $pred[$h]['Pts']+=3; $pred[$a]['P']++; }
  elseif($ph<$pa){ $pred[$a]['G']++; $pred[$a]['Pts']+=3; $pred[$a]['AW']++; $pred[$h]['P']++; }
  else { $pred[$h]['N']++; $pred[$h]['Pts']+=1; $pred[$a]['N']++; $pred[$a]['Pts']+=1; }
}
$predTbl=_sort(array_values($pred)); $predMap=_rank($predTbl);

/* ---------- réel sur le même périmètre ---------- */
$st2=$pdo->prepare("
  SELECT m.home_team, m.away_team, m.home_score, m.away_score
  FROM matches m
  WHERE m.is_finished = 1
    AND m.md IS NOT NULL
    AND $cond
");
$st2->execute([':md'=>$md]);
$real=[];
while($m=$st2->fetch(PDO::FETCH_ASSOC)){
  $h=$m['home_team']; $a=$m['away_team']; $hs=$m['home_score']; $as=$m['away_score'];
  if($hs===null||$as===null) continue;

  _init($real,$h); _init($real,$a);
  $real[$h]['PJ']++; $real[$a]['PJ']++;
  $real[$h]['BP']+=$hs; $real[$h]['BC']+=$as; $real[$a]['BP']+=$as; $real[$a]['BC']+=$hs;
  $real[$h]['Diff']=$real[$h]['BP']-$real[$h]['BC']; $real[$a]['Diff']=$real[$a]['BP']-$real[$a]['BC'];
  $real[$a]['AG']+=$as;
  if($hs>$as){ $real[$h]['G']++; $real[$h]['Pts']+=3; $real[$a]['P']++; }
  elseif($hs<$as){ $real[$a]['G']++; $real[$a]['Pts']+=3; $real[$a]['AW']++; $real[$h]['P']++; }
  else { $real[$h]['N']++; $real[$h]['Pts']+=1; $real[$a]['N']++; $real[$a]['Pts']+=1; }
}
$realTbl=_sort(array_values($real)); $realMap=_rank($realTbl);

/* ---------- lignes comparatives ---------- */
$teams=array_unique(array_merge(array_keys($predMap),array_keys($realMap)));
sort($teams,SORT_FLAG_CASE|SORT_STRING);

$rows=[];
foreach($teams as $t){
  $pr=$predMap[$t]['rank']??null; $pp=$predMap[$t]['pts']??null;
  $rr=$realMap[$t]['rank']??null; $rp=$realMap[$t]['pts']??null;
  $dr=($pr&&$rr)?($rr-$pr):null;
  $dp=($pp!==null&&$rp!==null)?($rp-$pp):null;
  $rows[]=['team'=>$t,'pr'=>$pr,'pp'=>$pp,'rr'=>$rr,'rp'=>$rp,'dr'=>$dr,'dp'=>$dp];
}
usort($rows,function($a,$b){
  $ar=$a['rr']??999; $br=$b['rr']??999; if($ar!==$br) return $ar<=>$br;
  $ap=$a['pr']??999; $bp=$b['pr']??999; return $ap<=>$bp;
});

/* ---------- stats J sélectionnée ---------- */
$stTot=$pdo->prepare("SELECT COUNT(*) FROM matches WHERE md=?");
$stTot->execute([$md]); $totThis=(int)$stTot->fetchColumn();

$stFin=$pdo->prepare("SELECT COUNT(*) FROM matches WHERE md=? AND is_finished=1");
$stFin->execute([$md]); $finThis=(int)$stFin->fetchColumn();
?>
<div class="card">
  <h2>Comparaison par journée - <?=h($user['username'])?></h2>
  <p class="muted">Cliquer sur Jx affiche toujours J1 → Jx (mode cumul). Le bouton “Mode solo” montre uniquement la journée choisie.<br>
  Journée J<?=$md?> : <?=$finThis?> / <?=$totThis?> matches terminés.</p>

  <?php if ($mds): ?>
    <div class="row" style="gap:6px;flex-wrap:wrap;margin-top:8px">
      <?php foreach ($mds as $x): $active=((int)$x===$md)?"style='font-weight:bold;'":""; ?>
        <a class="badge" <?=$active?> href="compare_md.php?u=<?=urlencode($user['username'])?>&md=<?=$x?>&mode=cumul">J<?=$x?></a>
      <?php endforeach; ?>
      <span class="muted" style="margin-left:8px"> - </span>
      <a class="badge" href="compare_md.php?u=<?=urlencode($user['username'])?>&md=<?=$md?>&mode=cumul">Mode cumul</a>
      <a class="badge" href="compare_md.php?u=<?=urlencode($user['username'])?>&md=<?=$md?>&mode=solo">Mode solo</a>
      <span class="muted" style="margin-left:8px"> - </span>
      <a class="badge" href="player.php?u=<?=urlencode($user['username'])?>">Profil joueur</a>
      <a class="badge" href="compare.php?u=<?=urlencode($user['username'])?>&cut=now">Comparer à date</a>
    </div>
  <?php endif; ?>
</div>

<?php if(!$rows): ?>
  <div class="card"><p class="muted">Pas de données pour ce périmètre.</p></div>
<?php else: ?>
  <div class="card">
    <table>
      <tr><th>Équipe</th><th>Rang prédit</th><th>Pts prédits</th><th>Rang réel</th><th>Pts réels</th><th>Δ rang</th><th>Δ pts</th></tr>
      <?php foreach($rows as $r):
        $drTxt=$r['dr']===null?'-':($r['dr']>0?'+'.$r['dr']:$r['dr']);
        $dpTxt=$r['dp']===null?'-':($r['dp']>0?'+'.$r['dp']:$r['dp']);
        $style=''; if($r['dr']!==null){ if($r['dr']<0) $style=" style='background:#0b2a1b'"; elseif($r['dr']>0) $style=" style='background:#2a0b0b'"; }
      ?>
      <tr<?=$style?>>
        <td><?=h($r['team'])?></td>
        <td><?= $r['pr']?:'-' ?></td>
        <td><?= $r['pp']!==null?(int)$r['pp']:'-' ?></td>
        <td><?= $r['rr']?:'-' ?></td>
        <td><?= $r['rp']!==null?(int)$r['rp']:'-' ?></td>
        <td><?=$drTxt?></td>
        <td><?=$dpTxt?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="card">
    <div class="row">
      <div class="col">
        <h3>Classement prédit - <?=$mode==='solo'?'J'.$md:'J1 → J'.$md?></h3>
        <table>
          <tr><th>#</th><th>Équipe</th><th>PJ</th><th>G</th><th>N</th><th>P</th><th>BP</th><th>BC</th><th>Diff</th><th>Pts</th></tr>
          <?php $rk=1; foreach($predTbl as $t): ?>
            <tr>
              <td><?=$rk++?></td><td><?=h($t['team'])?></td>
              <td><?=$t['PJ']?></td><td><?=$t['G']?></td><td><?=$t['N']?></td><td><?=$t['P']?></td>
              <td><?=$t['BP']?></td><td><?=$t['BC']?></td>
              <td><?=($t['Diff']>=0?'+':'').$t['Diff']?></td>
              <td><strong><?=$t['Pts']?></strong></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <div class="col">
        <h3>Classement réel - <?=$mode==='solo'?'J'.$md:'J1 → J'.$md?></h3>
        <table>
          <tr><th>#</th><th>Équipe</th><th>PJ</th><th>G</th><th>N</th><th>P</th><th>BP</th><th>BC</th><th>Diff</th><th>Pts</th></tr>
          <?php $rk=1; foreach($realTbl as $t): ?>
            <tr>
              <td><?=$rk++?></td><td><?=h($t['team'])?></td>
              <td><?=$t['PJ']?></td><td><?=$t['G']?></td><td><?=$t['N']?></td><td><?=$t['P']?></td>
              <td><?=$t['BP']?></td><td><?=$t['BC']?></td>
              <td><?=($t['Diff']>=0?'+':'').$t['Diff']?></td>
              <td><strong><?=$t['Pts']?></strong></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__.'/footer.php'; ?>
