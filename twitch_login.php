<?php
require_once __DIR__.'/auth.php';

$state = bin2hex(random_bytes(12));
$_SESSION['twitch_oauth_state'] = $state;

// garde la destination finale
if (!empty($_GET['next'])) $_SESSION['oauth_next'] = $_GET['next'];

$params = http_build_query([
  'client_id'     => TWITCH_CLIENT_ID,
  'redirect_uri'  => TWITCH_REDIRECT_URI,
  'response_type' => 'code',
  'scope'         => TWITCH_SCOPES,
  'state'         => $state,
]);
header('Location: https://id.twitch.tv/oauth2/authorize?'.$params);
