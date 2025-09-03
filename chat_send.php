<?php
// chat_send.php - Chat pur OpenRouter, sans API externe ni contexte local (hors historique)
// Historique stocké en BDD. Filtre toute mention de "Source:".

require_once __DIR__.'/auth.php';
require_once __DIR__.'/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $pdo = pdo();

  // lecture input
  $raw = file_get_contents('php://input');
  $in  = json_decode($raw, true);
  if (!is_array($in)) { $in = $_POST; }
  $q   = trim($in['q'] ?? '');
  if ($q === '') { echo json_encode(['error' => 'Question vide']); exit; }

  // modèle courant
  $model = $_SESSION['or_model'] ?? (defined('OR_MODEL') ? OR_MODEL : 'openrouter/auto');

  // commande /model <provider/model>
  if (preg_match('~^/model\s+([a-z0-9._/\-]+)~i', $q, $mset)) {
    $new = trim($mset[1]);
    $_SESSION['or_model'] = $new;
    if (!empty($_SESSION['chat_session_id'])) {
      $pdo->prepare("UPDATE chat_sessions SET model=? WHERE id=?")
          ->execute([$new, $_SESSION['chat_session_id']]);
    }
    echo json_encode(['text' => "✅ Modèle changé: **{$new}**\nAstuce: /model openrouter/auto pour utiliser ta préférence OpenRouter."]);
    exit;
  }

  if (!defined('OR_API_KEY') || !OR_API_KEY || OR_API_KEY === 'TA_CLE_OPENROUTER') {
    echo json_encode(['error' => 'Clé OpenRouter manquante dans config.php']); exit;
  }

  // session de chat
  $chatId = $_SESSION['chat_session_id'] ?? null;
  if (!$chatId) {
    $uid = null;
    if (!empty($_SESSION['username'])) {
      $st = $pdo->prepare("SELECT id FROM users WHERE username=?");
      $st->execute([$_SESSION['username']]);
      $uid = $st->fetchColumn() ?: null;
    }
    $pdo->prepare("INSERT INTO chat_sessions(user_id, php_session_id, model) VALUES(?,?,?)")
        ->execute([$uid, session_id(), $model]);
    $chatId = (int)$pdo->lastInsertId();
    $_SESSION['chat_session_id'] = $chatId;
  } else {
    $pdo->prepare("UPDATE chat_sessions SET last_active=NOW() WHERE id=?")->execute([$chatId]);
  }

  // enregistre le message user
  $pdo->prepare("INSERT INTO chat_messages(session_id, role, content) VALUES(?, 'user', ?)")
      ->execute([$chatId, $q]);

  // récupère l'historique et le FILTRE
  $st = $pdo->prepare("SELECT role, content FROM chat_messages WHERE session_id=? ORDER BY id ASC LIMIT 20");
  $st->execute([$chatId]);
  $rawHistory = $st->fetchAll(PDO::FETCH_ASSOC);

  $history = [];
  foreach ($rawHistory as $h) {
    if ($h['role'] !== 'user' && $h['role'] !== 'assistant') continue;
    $lc = mb_strtolower($h['content'], 'UTF-8');
    // on retire toute ligne antérieure qui prime des "Source:" ou sites externes
    if (strpos($lc, 'football-data.org') !== false) continue;
    if (preg_match('/^\s*source\s*:/i', $h['content'])) continue;
    if (preg_match('/sofascore|onefootball|whoscored|flashscore|espn/i', $h['content'])) continue;
    $history[] = $h;
  }

  // prompt système - interdit toute mention de sources ou d'API
  $msgs = [];
  $msgs[] = [
    'role'    => 'system',
    'content' =>
      "Tu es un assistant en français.\n".
      "- Ne mentionne JAMAIS de sources ni d'API (pas de ligne commençant par 'Source:').\n".
      "- N'invente pas que tu as consulté un site. Réponds simplement à la question.\n".
      "- Si on te demande des résultats précis et que tu n'es pas certain, indique que tu ne peux pas garantir l'exactitude.\n"
  ];
  foreach ($history as $h) {
    $msgs[] = ['role'=>$h['role'], 'content'=>$h['content']];
  }
  $msgs[] = ['role'=>'user', 'content'=>$q];

  // appel OpenRouter
  $endpoint = rtrim(defined('OR_BASE') ? OR_BASE : 'https://openrouter.ai/api/v1', '/').'/chat/completions';
  $body = ['model'=>$model, 'messages'=>$msgs, 'temperature'=>0.4];

  $ch = curl_init($endpoint);
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => array_values(array_filter([
      'Content-Type: application/json',
      'Authorization: Bearer '.OR_API_KEY,
      defined('OR_HTTP_REFERER') && OR_HTTP_REFERER ? 'HTTP-Referer: '.OR_HTTP_REFERER : null,
      defined('OR_TITLE') && OR_TITLE ? 'X-Title: '.OR_TITLE : null,
    ])),
    CURLOPT_POSTFIELDS     => json_encode($body),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => defined('OR_SSL_SKIP_VERIFY') && OR_SSL_SKIP_VERIFY ? 0 : 1,
    CURLOPT_SSL_VERIFYHOST => defined('OR_SSL_SKIP_VERIFY') && OR_SSL_SKIP_VERIFY ? 0 : 2,
  ]);
  $ca = __DIR__.'/cacert.pem';
  if (is_file($ca)) curl_setopt($ch, CURLOPT_CAINFO, $ca);

  $res  = curl_exec($ch);
  if ($res === false) { $err = curl_error($ch); curl_close($ch); echo json_encode(['error'=>"cURL: $err"]); exit; }
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($code >= 300) { echo json_encode(['error'=>"HTTP $code: $res"]); exit; }

  $data = json_decode($res, true);
  $text = $data['choices'][0]['message']['content'] ?? '(réponse vide)';

  // nettoyage - supprime toute ligne "Source:" ou sites externes si le modèle en ajoute quand même
  $lines = preg_split("/\R+/", (string)$text);
  $clean = [];
  foreach ($lines as $ln) {
    $lnTrim = trim($ln);
    $lc = mb_strtolower($lnTrim, 'UTF-8');
    if ($lnTrim === '') { $clean[] = $ln; continue; }
    if (preg_match('/^\s*source\s*:/i', $lnTrim)) continue;
    if (strpos($lc, 'football-data.org') !== false) continue;
    if (preg_match('/sofascore|onefootball|whoscored|flashscore|espn/i', $lnTrim)) continue;
    $clean[] = $ln;
  }
  $text = trim(implode("\n", $clean));

  // enregistre la réponse assistant
  $pdo->prepare("INSERT INTO chat_messages(session_id, role, content) VALUES(?, 'assistant', ?)")
      ->execute([$chatId, $text]);

  echo json_encode(['text'=>$text]);

} catch (Throwable $e) {
  echo json_encode(['error'=>$e->getMessage()]);
}
