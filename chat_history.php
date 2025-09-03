<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/db.php';
header('Content-Type: application/json; charset=utf-8');

try{
  $pdo=pdo();
  $chatId=$_SESSION['chat_session_id'] ?? null;
  if(!$chatId){
    $uid=null;
    if(!empty($_SESSION['username'])){ $st=$pdo->prepare("SELECT id FROM users WHERE username=?"); $st->execute([$_SESSION['username']]); $uid=$st->fetchColumn()?:null; }
    $pdo->prepare("INSERT INTO chat_sessions(user_id,php_session_id,model) VALUES(?,?,?)")->execute([$uid,session_id(),OR_MODEL??null]);
    $chatId=(int)$pdo->lastInsertId(); $_SESSION['chat_session_id']=$chatId;
  }
  $st=$pdo->prepare("SELECT role,content,created_at FROM chat_messages WHERE session_id=? ORDER BY id ASC LIMIT 200"); $st->execute([$chatId]);
  echo json_encode(['messages'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
} catch(Throwable $e){ echo json_encode(['error'=>$e->getMessage(),'messages'=>[]]); }
