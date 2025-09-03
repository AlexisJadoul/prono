<?php
require_once __DIR__.'/auth.php';

// 1) Anti-CSRF
if (empty($_GET['state']) || $_GET['state'] !== ($_SESSION['twitch_oauth_state'] ?? '')) {
  http_response_code(400);
  exit('State invalide');
}
if (empty($_GET['code'])) {
  http_response_code(400);
  exit('Code manquant');
}
$code = $_GET['code'];

// 2) Echange code -> access_token
$tokenUrl = 'https://id.twitch.tv/oauth2/token';
$post = [
  'client_id'     => TWITCH_CLIENT_ID,
  'client_secret' => TWITCH_CLIENT_SECRET,
  'code'          => $code,
  'grant_type'    => 'authorization_code',
  'redirect_uri'  => TWITCH_REDIRECT_URI,
];

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => http_build_query($post),
  CURLOPT_HTTPHEADER     => ['Accept: application/json'],
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT        => 20,
]);
$res = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($res === false) {
  $err = curl_error($ch);
  curl_close($ch);
  http_response_code(502);
  exit('Erreur cURL token: '.$err);
}
curl_close($ch);

$data = json_decode($res, true);

// Affiche proprement l’erreur de Twitch si le token est absent
if ($http >= 300 || empty($data['access_token'])) {
  http_response_code(400);
  echo "<pre>Erreur lors de l’échange code→token\n";
  echo "HTTP: $http\n\n";
  echo "Réponse Twitch:\n".($res ?: '(vide)')."\n\n";
  echo "Vérifie dans config.php :\n";
  echo " - TWITCH_CLIENT_ID = ".TWITCH_CLIENT_ID."\n";
  echo " - TWITCH_CLIENT_SECRET = (défini)\n";
  echo " - TWITCH_REDIRECT_URI = ".TWITCH_REDIRECT_URI."\n";
  echo "Et que cette URL est EXACTEMENT déclarée dans la console Twitch.</pre>";
  exit;
}

$access = $data['access_token'];

// 3) Profil utilisateur
$ch = curl_init('https://api.twitch.tv/helix/users');
curl_setopt_array($ch, [
  CURLOPT_HTTPHEADER     => [
    'Authorization: Bearer '.$access,
    'Client-ID: '.TWITCH_CLIENT_ID,
    'Accept: application/json'
  ],
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT        => 20,
]);
$me = curl_exec($ch);
$http2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($me === false) {
  $err = curl_error($ch);
  curl_close($ch);
  http_response_code(502);
  exit('Erreur cURL users: '.$err);
}
curl_close($ch);

$usr = json_decode($me, true);
$u = $usr['data'][0] ?? null;
if (!$u) {
  http_response_code(400);
  echo "<pre>Impossible de récupérer le profil Twitch (HTTP $http2)\nRéponse:\n$me</pre>";
  exit;
}

$tw = [
  'id'     => $u['id'],
  'login'  => $u['login'],
  'email'  => $u['email'] ?? null,
  'avatar' => $u['profile_image_url'] ?? null,
];

login_with_twitch($tw);
unset($_SESSION['twitch_oauth_state']);

$next = $_SESSION['oauth_next'] ?? 'index.php';
unset($_SESSION['oauth_next']);
header('Location: '.$next);
