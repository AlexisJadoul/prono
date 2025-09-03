<?php
require __DIR__.'/config.php';

echo "<pre>";
echo "PHP cURL: ".(in_array('curl', get_loaded_extensions()) ? "OK" : "ABSENT")."\n";
echo "Token API: ".(FD_API_TOKEN && FD_API_TOKEN!=='TON_TOKEN_ICI' ? "OK" : "MANQUANT")."\n";

$ch = curl_init(rtrim(FD_API_BASE,'/')."/competitions/".FD_COMP_CODE."/matches?season=".date('Y'));
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    'X-Auth-Token: '.FD_API_TOKEN,
    'Accept: application/json',
  ],
  CURLOPT_TIMEOUT => 15,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $code\n";
if ($code >= 200 && $code < 300) {
  $data = json_decode($body, true);
  $count = isset($data['matches']) ? count($data['matches']) : 0;
  echo "Matches renvoyés: $count\n";
} else {
  echo "Réponse: $body\n";
}
