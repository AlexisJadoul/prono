<?php
// =======================================================
// Config générale de l'app
// =======================================================

date_default_timezone_set('Europe/Paris');

// Mode debug - affiche les erreurs en local
define('APP_DEBUG', true);
if (APP_DEBUG) {
  ini_set('display_errors', '1');
  error_reporting(E_ALL);
} else {
  ini_set('display_errors', '0');
  error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
}

define('APP_ENV', 'prod');
define('APP_URL', 'https://pronoldc.alwaysdata.net'); // change si tu utilises un domaine perso

// --- MySQL AlwaysData (vu sur ton screen) ---
define('DB_HOST', 'mysql-pronoldc.alwaysdata.net');
define('DB_PORT', 3306);
define('DB_NAME', 'pronoldc_prono');   // nom de la base à gauche dans phpMyAdmin
define('DB_USER', 'pronoldc');         // utilisateur MySQL
define('DB_PASS', '18081995Tintin'); // mets le vrai mot de passe défini dans le Manager

// --- Twitch OAuth (pronoldc) ---
define('TWITCH_CLIENT_ID',     '9yc78exsbe36l5jsg2add938mwfwju');
define('TWITCH_CLIENT_SECRET', '5cegxhugw7q5w65i7mdh25gebiglzp'); // colle exactement ton secret
define('TWITCH_REDIRECT_URI',  APP_URL.'/twitch_callback.php');  // https://pronoldc.alwaysdata.net/twitch_callback.php
define('TWITCH_SCOPES',        'user:read:email');               // ou '' si tu ne veux pas l’email


// Mot de passe admin pour /admin.php
define('ADMIN_PASS', 'a_changer');

// =======================================================
// Paramètres pronostics
// =======================================================

define('LOCK_MINUTES_BEFORE', 1); // verrou X minutes avant le coup d'envoi
define('PTS_EXACT', 3);           // bon score exact
define('PTS_TENDANCE', 1);        // bonne tendance seulement

// =======================================================
// API football-data.org - LDC
// =======================================================

define('FD_API_BASE',  'https://api.football-data.org/v4');
define('FD_COMP_CODE', 'CL'); // Champions League

// Ta clé - déjà collée depuis ta capture
define('FD_API_TOKEN', '30bb51eb09094d239aef756c33bd9721');

// En local tu peux contourner les soucis de certificats SSL
// Mets à false quand tu mets l'app en ligne
define('FD_SSL_SKIP_VERIFY', true);

// =======================================================
// OpenRouter - chatbot IA du site
// =======================================================

define('OR_BASE', 'https://openrouter.ai/api/v1');

// Mets ta clé OpenRouter ici
define('OR_API_KEY', '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');

// Choisis le modèle que tu veux utiliser
// Exemples: 'anthropic/claude-3.5-sonnet', 'openai/gpt-4o-mini', 'google/gemini-1.5-pro'
define('OR_MODEL', 'deepseek/deepseek-chat-v3.1:free');

// Laisse à false sauf si tu as un souci SSL en local
define('OR_SSL_SKIP_VERIFY', true);

// En-têtes facultatifs recommandés par OpenRouter
define('OR_HTTP_REFERER', APP_URL);
define('OR_TITLE', 'LDC Pronostics Chat');



// =======================================================
// Fin de config
// =======================================================
