<?php
/**
 * lib.php — helpers génériques + outils locaux + wrappers football-data.org
 * Dépendances facultatives :
 *   - config.php doit définir FD_API_TOKEN / FD_API_BASE / FD_SSL_SKIP_VERIFY si l’API est utilisée.
 *   - db.php doit exposer pdo() pour les fonctions "cu_*" (base locale).
 */

/* -------------------------------------------------------
 *  Base
 * ----------------------------------------------------- */

if (!function_exists('h')) {
  function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('is_post')) {
  function is_post(): bool {
    return (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST');
  }
}

if (!function_exists('redirect')) {
  function redirect(string $url): void {
    header('Location: '.$url);
    exit;
  }
}

/* Démarrage de session si nécessaire (utile pour flash() lorsque lib.php est chargé seul) */
if (!function_exists('lib_ensure_session')) {
  function lib_ensure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
      @session_start();
    }
  }
}

/* -------------------------------------------------------
 *  Flash messages (globaux)
 * ----------------------------------------------------- */

if (!function_exists('flash')) {
  function flash(string $msg, string $type='ok'): void {
    lib_ensure_session();
    $_SESSION['flash'][] = ['t'=>$type,'m'=>$msg];
  }
}

if (!function_exists('flash_all')) {
  function flash_all(): array {
    lib_ensure_session();
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
  }
}

/* -------------------------------------------------------
 *  Helpers "club utils" sur la base locale (cu_*)
 *  - cu_search_teams(PDO $pdo, string $needle): array<string>
 *  - cu_recent_form(PDO $pdo, string $team, int $n=5): array
 *  - cu_h2h(PDO $pdo, string $teamA, string $teamB, int $n=5): array
 * ----------------------------------------------------- */

if (!function_exists('cu_normalize_team')) {
  function cu_normalize_team(string $name): string {
    // Normalisation légère pour les recherches
    $name = trim($name);
    $name = preg_replace('~\s+~',' ',$name);
    return $name;
  }
}

if (!function_exists('cu_search_teams')) {
  /** Recherche de clubs présents dans la table matches (home/away) */
  function cu_search_teams(PDO $pdo, string $needle): array {
    $q = cu_normalize_team($needle);
    if ($q === '') return [];
    $st = $pdo->prepare("
      SELECT team FROM (
        SELECT DISTINCT home_team AS team FROM matches
        UNION
        SELECT DISTINCT away_team FROM matches
      ) t
      WHERE team LIKE ?
      ORDER BY team
      LIMIT 20
    ");
    $st->execute(['%'.$q.'%']);
    return array_values(array_filter($st->fetchAll(PDO::FETCH_COLUMN) ?: []));
  }
}

if (!function_exists('cu_recent_form')) {
  /**
   * Derniers N matchs terminés d’une équipe (côté local)
   * @return array{team:string,items:array<array{date:string,home:string,away:string,score:string,result:string}>}
   */
  function cu_recent_form(PDO $pdo, string $team, int $n=5): array {
    $team = cu_normalize_team($team);
    if ($team === '') return ['team'=>'','items'=>[]];

    $st = $pdo->prepare("
      SELECT kickoff, home_team, away_team, home_score, away_score
      FROM matches
      WHERE is_finished=1 AND (home_team=? OR away_team=?)
      ORDER BY kickoff DESC
      LIMIT ?
    ");
    $st->bindValue(1, $team);
    $st->bindValue(2, $team);
    $st->bindValue(3, $n, PDO::PARAM_INT);
    $st->execute();

    $out = ['team'=>$team,'items'=>[]];
    while ($m = $st->fetch(PDO::FETCH_ASSOC)) {
      $hs = $m['home_score']; $as = $m['away_score'];
      $date = $m['kickoff'] ? date('Y-m-d', strtotime($m['kickoff'])) : null;
      $res = '-';
      if ($hs !== null && $as !== null) {
        if ($m['home_team'] === $team) {
          $res = $hs > $as ? 'W' : ($hs < $as ? 'L' : 'D');
        } else {
          $res = $as > $hs ? 'W' : ($as < $hs ? 'L' : 'D');
        }
      }
      $out['items'][] = [
        'date' => $date,
        'home' => $m['home_team'],
        'away' => $m['away_team'],
        'score'=> ($hs===null||$as===null) ? '-' : ($hs.' - '.$as),
        'result'=>$res,
      ];
    }
    return $out;
  }
}

if (!function_exists('cu_h2h')) {
  /**
   * Confrontations directes locales (terminées) entre 2 équipes
   * @return array{teamA:string,teamB:string,last:array<array{date:string,home:string,away:string,score:string}>}
   */
  function cu_h2h(PDO $pdo, string $teamA, string $teamB, int $n=5): array {
    $a = cu_normalize_team($teamA);
    $b = cu_normalize_team($teamB);
    if ($a==='' || $b==='') return ['teamA'=>$a,'teamB'=>$b,'last'=>[]];

    $st = $pdo->prepare("
      SELECT kickoff, home_team, away_team, home_score, away_score
      FROM matches
      WHERE is_finished=1
        AND ((home_team=? AND away_team=?) OR (home_team=? AND away_team=?))
      ORDER BY kickoff DESC
      LIMIT ?
    ");
    $st->bindValue(1, $a); $st->bindValue(2, $b);
    $st->bindValue(3, $b); $st->bindValue(4, $a);
    $st->bindValue(5, $n, PDO::PARAM_INT);
    $st->execute();

    $out = ['teamA'=>$a,'teamB'=>$b,'last'=>[]];
    while ($m = $st->fetch(PDO::FETCH_ASSOC)) {
      $hs = $m['home_score']; $as = $m['away_score'];
      $out['last'][] = [
        'date' => $m['kickoff'] ? date('Y-m-d', strtotime($m['kickoff'])) : null,
        'home' => $m['home_team'],
        'away' => $m['away_team'],
        'score'=> ($hs===null||$as===null) ? '-' : ($hs.' - '.$as),
      ];
    }
    return $out;
  }
}

/* -------------------------------------------------------
 *  football-data.org (fd_*)
 *  Nécessite : FD_API_TOKEN, FD_API_BASE, FD_SSL_SKIP_VERIFY (config.php)
 * ----------------------------------------------------- */

if (!function_exists('fd_api_get')) {
  /**
   * GET sur l’API football-data.org
   * @param string $path   Ex: '/matches' (slash optionnel)
   * @param array  $params Ex: ['ids'=>'123,456']
   * @return array         Réponse JSON (array) — vide si erreur récupérée
   * @throws RuntimeException si configuration manquante
   */
  function fd_api_get(string $path, array $params = []): array {
    if (!defined('FD_API_TOKEN') || FD_API_TOKEN === '') {
      throw new RuntimeException('FD_API_TOKEN manquante dans config.php');
    }
    $base = defined('FD_API_BASE') && FD_API_BASE ? FD_API_BASE : 'https://api.football-data.org/v4';
    $base = rtrim($base, '/');
    if ($path === '' || $path[0] !== '/') $path = '/'.$path;

    $url = $base.$path;
    if ($params) $url .= '?'.http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER     => [
        'X-Auth-Token: '.FD_API_TOKEN,
        'Accept: application/json',
      ],
      CURLOPT_TIMEOUT        => 25,
      CURLOPT_SSL_VERIFYPEER => defined('FD_SSL_SKIP_VERIFY') && FD_SSL_SKIP_VERIFY ? 0 : 1,
      CURLOPT_SSL_VERIFYHOST => defined('FD_SSL_SKIP_VERIFY') && FD_SSL_SKIP_VERIFY ? 0 : 2,
    ]);
    // CA bundle local (optionnel)
    $ca = __DIR__.'/cacert.pem';
    if (is_file($ca)) curl_setopt($ch, CURLOPT_CAINFO, $ca);

    $res = curl_exec($ch);
    if ($res === false) {
      // on ne jette pas d'exception pour ne pas casser l'app → retour vide
      curl_close($ch);
      return [];
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($res, true);
    if ($code >= 300) {
      // on renvoie un tableau explicatif minimal plutôt qu'une exception
      return ['error'=>true,'status'=>$code,'raw'=>$data ?? $res];
    }
    return is_array($data) ? $data : [];
  }
}

if (!function_exists('fd_find_team_by_name')) {
  /**
   * Essaie de retrouver une équipe par nom côté API.
   * Stratégie:
   *  - tentative /teams?name=... (si dispo)
   *  - fallback: /competitions/CL/teams puis filtrage
   *  - si rien: null
   * Retourne array('id'=>int,'name'=>string) ou null.
   */
  function fd_find_team_by_name(string $name, string $compCode = 'CL'): ?array {
    $name = trim($name);
    if ($name === '') return null;

    // 1) tentative générique
    $try = fd_api_get('/teams', ['name'=>$name]);
    if (!empty($try['teams']) && is_array($try['teams'])) {
      // on prend le plus proche
      foreach ($try['teams'] as $t) {
        if (isset($t['id'],$t['name'])) {
          if (stripos($t['name'], $name) !== false || strcasecmp($t['name'],$name)===0) {
            return ['id'=>$t['id'],'name'=>$t['name']];
          }
        }
      }
      // sinon premier
      $t = $try['teams'][0];
      if (isset($t['id'],$t['name'])) return ['id'=>$t['id'],'name'=>$t['name']];
    }

    // 2) fallback compétition (CL par défaut)
    $teams = fd_api_get("/competitions/".$compCode."/teams");
    if (!empty($teams['teams']) && is_array($teams['teams'])) {
      foreach ($teams['teams'] as $t) {
        if (isset($t['id'],$t['name']) && (stripos($t['name'],$name)!==false || strcasecmp($t['name'],$name)===0)) {
          return ['id'=>$t['id'],'name'=>$t['name']];
        }
      }
      $t = $teams['teams'][0] ?? null;
      if ($t && isset($t['id'],$t['name'])) return ['id'=>$t['id'],'name'=>$t['name']];
    }

    return null;
  }
}

if (!function_exists('fd_recent_h2h_by_names')) {
  /**
   * H2H online via API entre deux équipes par leurs noms.
   * Retourne ['teamA'=>..., 'teamB'=>..., 'items'=>[...]] ; items = {date,home,away,score}
   * Se contente de retourner [] si l’API ne répond pas.
   */
  function fd_recent_h2h_by_names(string $teamA, string $teamB, int $limit=5): array {
    $out = ['teamA'=>$teamA,'teamB'=>$teamB,'items'=>[]];

    $a = fd_find_team_by_name($teamA);
    $b = fd_find_team_by_name($teamB);
    if (!$a || !$b) return $out;

    // Endpoint probable: /teams/{id}/matches?status=FINISHED&opponents={idB}&limit=...
    // (si non supporté par l'API, on renverra simplement vide)
    $data = fd_api_get("/teams/{$a['id']}/matches", [
      'status'     => 'FINISHED',
      'opponents'  => $b['id'],
      'limit'      => $limit
    ]);

    if (empty($data['matches']) || !is_array($data['matches'])) return $out;

    foreach ($data['matches'] as $m) {
      $dt = $m['utcDate'] ?? null;
      $date = $dt ? date('Y-m-d', strtotime($dt)) : null;
      $hs = $m['score']['fullTime']['home'] ?? null;
      $as = $m['score']['fullTime']['away'] ?? null;
      $out['items'][] = [
        'date' => $date,
        'home' => $m['homeTeam']['name'] ?? '',
        'away' => $m['awayTeam']['name'] ?? '',
        'score'=> ($hs===null||$as===null) ? '-' : ($hs.' - '.$as),
      ];
    }
    return $out;
  }
}
