<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/lib.php';
require_once __DIR__.'/chat_utils.php';
require_once __DIR__.'/chat_online.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $pdo = pdo();
  $raw = file_get_contents('php://input'); $in = json_decode($raw,true);
  $q = trim($in['q'] ?? ($_POST['q'] ?? ''));
  if ($q===''){ echo json_encode(['error'=>'Question vide']); exit; }
  if (!defined('OR_API_KEY') || !OR_API_KEY || OR_API_KEY==='TA_CLE_OPENROUTER'){ echo json_encode(['error'=>'Clé OpenRouter manquante dans config.php']); exit; }

  $chatId = $_SESSION['chat_session_id'] ?? null;
  if(!$chatId){
    $uid=null; if(!empty($_SESSION['username'])){ $st=$pdo->prepare("SELECT id FROM users WHERE username=?"); $st->execute([$_SESSION['username']]); $uid=$st->fetchColumn()?:null; }
    $pdo->prepare("INSERT INTO chat_sessions(user_id,php_session_id,model) VALUES(?,?,?)")->execute([$uid,session_id(),OR_MODEL]);
    $chatId=(int)$pdo->lastInsertId(); $_SESSION['chat_session_id']=$chatId;
  } else {
    $pdo->prepare("UPDATE chat_sessions SET last_active=NOW() WHERE id=?")->execute([$chatId]);
  }

  $pdo->prepare("INSERT INTO chat_messages(session_id,role,content) VALUES(?, 'user', ?)")->execute([$chatId,$q]);

  $st=$pdo->prepare("SELECT role,content FROM chat_messages WHERE session_id=? ORDER BY id ASC LIMIT 20"); $st->execute([$chatId]); $history=$st->fetchAll(PDO::FETCH_ASSOC);

  $context=[]; $teamA=$teamB=null; $localH2H=null;
  if (preg_match('/^\s*(.+?)\s*(?:vs\.?|contre|-)\s*(.+?)\s*$/i',$q,$m)) {
    $teamA=trim($m[1]); $teamB=trim($m[2]);
    $cA=cu_search_teams($pdo,$teamA); if($cA) $teamA=$cA[0];
    $cB=cu_search_teams($pdo,$teamB); if($cB) $teamB=$cB[0];
    $localH2H=cu_h2h($pdo,$teamA,$teamB,5);
    $context=['intent'=>'h2h','params'=>['teamA'=>$teamA,'teamB'=>$teamB],'h2h_local'=>$localH2H,'formA_local'=>cu_recent_form($pdo,$teamA,5),'formB_local'=>cu_recent_form($pdo,$teamB,5)];
    if(empty($localH2H['last'])){ $online=fd_recent_h2h_by_names($teamA,$teamB,5); if($online && !empty($online['items'])) $context['h2h_online']=$online; }
  } else {
    if (preg_match('/\b(PSG|Paris|Bar[çc]a|Barcelona|Real Madrid|Bayern|Juventus|Inter|Milan|Arsenal|Liverpool|Marseille|Monaco|Lille|Dortmund|Leipzig|Benfica|Porto|Sporting|Ajax|PSV|Feyenoord|Atletico|Napoli|Roma)\b/i',$q,$m2)) {
      $cand=cu_search_teams($pdo,$m2[0]); if($cand){
        $team=$cand[0]; $context=['intent'=>'team','params'=>['team'=>$team],'form_local'=>cu_recent_form($pdo,$team,5)];
        if(empty($context['form_local'])){ $found=fd_find_team_by_name($team); if($found){ try{
          $res=fd_api_get("/teams/{$found['id']}/matches",['status'=>'FINISHED','limit'=>5]); $items=[];
          foreach(($res['matches']??[]) as $mm){
            $dt=$mm['utcDate']??null; $date=$dt?date('Y-m-d',strtotime($dt)):null;
            $hs=$mm['score']['fullTime']['home']??null; $as=$mm['score']['fullTime']['away']??null;
            $items[]=['date'=>$date,'home'=>$mm['homeTeam']['name']??'','away'=>$mm['awayTeam']['name']??'','score'=>($hs===null||$as===null)?'-':($hs.' - '.$as)];
          }
          if($items) $context['form_online']=['team'=>$found,'items'=>$items];
        } catch(Throwable $e){} } }
      }
    }
  }

  $msgs=[]; $msgs[]=['role'=>'system','content'=>"Tu es un assistant football en français.
- Utilise d'abord le CONTEXTE fourni (local et/ou online).
- Si tu utilises Internet, indique 'via l'API football-data.org'.
- Reponds de façon concise, listant date - affiche - score quand utile.
- Si rien n'est trouvé, explique quoi importer via l'admin."];
  if($context) $msgs[]=['role'=>'system','content'=>"CONTEXTE:\n".json_encode($context,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)];
  foreach($history as $h){ if($h['role']==='user'||$h['role']==='assistant') $msgs[]=['role'=>$h['role'],'content'=>$h['content']]; }
  $msgs[]=['role'=>'user','content'=>$q];

  $ch=curl_init(rtrim(OR_BASE,'/').'/chat/completions');
  $body=['model'=>OR_MODEL,'messages'=>$msgs,'temperature'=>0.4];
  curl_setopt_array($ch,[
    CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>array_values(array_filter([
      'Content-Type: application/json',
      'Authorization: Bearer '.OR_API_KEY,
      OR_HTTP_REFERER ? 'HTTP-Referer: '.OR_HTTP_REFERER : null,
      OR_TITLE ? 'X-Title: '.OR_TITLE : null,
    ])),
    CURLOPT_POSTFIELDS=>json_encode($body),
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_TIMEOUT=>25,
    CURLOPT_SSL_VERIFYPEER=> OR_SSL_SKIP_VERIFY ? 0 : 1,
    CURLOPT_SSL_VERIFYHOST=> OR_SSL_SKIP_VERIFY ? 0 : 2,
  ]);
  $ca=__DIR__.'/cacert.pem'; if(is_file($ca)) curl_setopt($ch,CURLOPT_CAINFO,$ca);
  $res=curl_exec($ch); if($res===false){ $err=curl_error($ch); curl_close($ch); echo json_encode(['error'=>"cURL: $err"]); exit; }
  $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if($code>=300){ echo json_encode(['error'=>"HTTP $code: $res"]); exit; }
  $data=json_decode($res,true); $text=$data['choices'][0]['message']['content'] ?? '(réponse vide)';

  $pdo->prepare("INSERT INTO chat_messages(session_id,role,content) VALUES(?, 'assistant', ?)")->execute([$chatId,$text]);
  echo json_encode(['text'=>$text]);

} catch(Throwable $e){ echo json_encode(['error'=>$e->getMessage()]); }
