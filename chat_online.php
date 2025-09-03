<?php
require_once __DIR__.'/lib.php';

function _norm_name(string $s): string {
  $s = mb_strtolower($s,'UTF-8');
  $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
  $s = preg_replace('/[^a-z0-9 ]/',' ',$s);
  $s = preg_replace('/\s+/',' ',$s);
  return trim($s);
}

function fd_find_team_by_name(string $q, int $season=null): ?array {
  $season = $season ?: (int)date('Y');
  $normQ = _norm_name($q);
  try {
    $r = fd_api_get('/teams', ['name'=>$q]);
    if (!empty($r['teams'])) {
      $best=null; $bestScore=-1;
      foreach ($r['teams'] as $t){ $name=$t['name']??''; $score=similar_text(_norm_name($name),$normQ); if($score>$bestScore){ $bestScore=$score; $best=['id'=>$t['id'],'name'=>$name]; } }
      if ($best) return $best;
    }
  } catch(Throwable $e){}

  $comps=['CL','PL','SA','PD','BL1','FL1','PPL','DED','BSA','EC'];
  foreach([$season,$season-1] as $yr){
    foreach($comps as $code){
      try {
        $teams = fd_api_get("/competitions/$code/teams", ['season'=>$yr]);
        foreach(($teams['teams']??[]) as $t){
          $name=$t['name']??''; if($name && (strpos(_norm_name($name),$normQ)!==false || strpos($normQ,_norm_name($name))!==false)) return ['id'=>$t['id'],'name'=>$name];
        }
      } catch(Throwable $e){}
    }
  }
  return null;
}

function fd_recent_h2h_by_names(string $teamA, string $teamB, int $limit=5): ?array {
  $A = fd_find_team_by_name($teamA); $B = fd_find_team_by_name($teamB);
  if(!$A || !$B) return null;
  try {
    $res = fd_api_get("/teams/{$A['id']}/matches", ['opponents'=>$B['id'],'status'=>'FINISHED','limit'=>$limit,'dateFrom'=>'2010-01-01']);
  } catch(Throwable $e){ return null; }
  $out=[]; foreach(($res['matches']??[]) as $m){
    $dt=$m['utcDate']??null; $date=$dt?date('Y-m-d',strtotime($dt)):null;
    $home=$m['homeTeam']['name']??''; $away=$m['awayTeam']['name']??'';
    $hs=$m['score']['fullTime']['home']??null; $as=$m['score']['fullTime']['away']??null;
    $comp=$m['competition']['name']??'';
    $out[]=['date'=>$date,'home'=>$home,'away'=>$away,'score'=>($hs===null||$as===null)?'-':($hs.' - '.$as),'comp'=>$comp];
  }
  return ['items'=>$out,'count'=>count($out),'teams'=>['A'=>$A,'B'=>$B]];
}
