<?php
/**
 * L'Atelier Berlingot — Traitement du formulaire de contact
 * avec défense anti-spam en profondeur (sans captcha visible).
 *
 * Couches de protection :
 *   1. Honeypots : champs invisibles que seuls les robots remplissent
 *   2. Jeton temporel : le formulaire doit être rempli en plusieurs secondes
 *   3. Limite de débit : maximum N messages par heure et par adresse IP
 *   4. Analyse du contenu : liens, BBCode, mots-clés spam, alphabets
 *      étrangers, domaines jetables, origine de la requête
 *   5. Journal hors-web de tout ce qui est envoyé ou rejeté (et pourquoi)
 *
 * Chaque signal douteux ajoute des points ; au-delà du seuil, le message
 * est refusé avec une alternative (email / téléphone) pour ne jamais
 * bloquer un vrai parent sans solution de secours.
 */

// ---- Configuration ---------------------------------------------------------
ini_set('display_errors', '0');

$DESTINATAIRE = 'eaje.atelierberlingot@gmail.com'; // boîte qui reçoit les demandes
$EXPEDITEUR   = 'noreply@latelierberlingot.com';   // doit rester sur le domaine hébergé
$SITE_URL     = 'https://latelierberlingot.com/';

$MAX_PAR_HEURE = 3;   // messages (envoyés ou rejetés comme spam) par IP et par heure
$DELAI_MINI    = 3;   // secondes minimales de remplissage du formulaire
$SCORE_MAXI    = 2;   // score de suspicion au-delà duquel on refuse

// ---- Journal et compteurs, stockés hors du répertoire web ------------------
// NB : l'arborescence réelle du serveur n'est pas inscriptible au-dessus de
// www ; on utilise donc un dossier pointé à l'intérieur de www, rendu
// inaccessible par le web via le .htaccess qu'il contient.
function espace_stockage() {
    $candidats = [
        ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) . '/.antispam',
        sys_get_temp_dir() . '/berlingot-antispam',
    ];
    foreach ($candidats as $dir) {
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        if (is_dir($dir) && is_writable($dir)) return $dir;
    }
    return null; // mode dégradé : filtrage actif, journalisation désactivée
}

function journal($statut, $raison = '', $contexte = '') {
    $dir = espace_stockage();
    if (!$dir) return;
    $contexte = str_replace(["\r", "\n", "\t"], ' ', mb_substr($contexte, 0, 120));
    $ligne = date('Y-m-d H:i:s') . "\t" . ($_SERVER['REMOTE_ADDR'] ?? '?') .
             "\t" . $statut . ($raison ? " ($raison)" : '') . "\t" . $contexte . "\n";
    @file_put_contents($dir . '/journal-' . date('Y-m') . '.txt', $ligne, FILE_APPEND | LOCK_EX);
}

// Compte les tentatives récentes (fenêtre glissante d'une heure) pour une IP.
// NB : closures classiques (pas de fn(), absent avant PHP 7.4).
function debits_ip($ip) {
    $dir = espace_stockage();
    if (!$dir) return 0;
    $fichier = $dir . '/ip-' . preg_replace('/[^0-9a-f.:]/i', '', $ip) . '.json';
    if (!is_file($fichier)) return 0;
    $moments = json_decode((string)@file_get_contents($fichier), true);
    if (!is_array($moments)) return 0;
    $limite = time() - 3600;
    $frais = array_values(array_filter($moments, function ($t) use ($limite) { return $t > $limite; }));
    @file_put_contents($fichier, json_encode($frais), LOCK_EX);
    return count($frais);
}

function debits_enregistrer($ip) {
    $dir = espace_stockage();
    if (!$dir) return;
    $fichier = $dir . '/ip-' . preg_replace('/[^0-9a-f.:]/i', '', $ip) . '.json';
    $moments = json_decode((string)@file_get_contents($fichier), true) ?: [];
    $moments[] = time();
    $limite = time() - 3600;
    $frais = array_values(array_filter($moments, function ($t) use ($limite) { return $t > $limite; }));
    @file_put_contents($fichier, json_encode($frais), LOCK_EX);
}

// ---- Réponses --------------------------------------------------------------
function repondre($ok, $message, $ajax) {
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message]);
    } else {
        header('Location: ' . $GLOBALS['SITE_URL'] . 'index.html?statut=' . ($ok ? 'ok' : 'erreur') . '#contact');
    }
    exit;
}

$ajax = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    repondre(false, 'Méthode non autorisée.', $ajax);
}

// ---- Champs ----------------------------------------------------------------
$nom     = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$tel     = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');
$piege1  = trim($_POST['website'] ?? '');   // honeypot n°1 : doit rester vide
$piege2  = trim($_POST['societe'] ?? '');   // honeypot n°2 : doit rester vide
$ts      = $_POST['ts'] ?? '';              // instant d'affichage du formulaire (ms)

$MESSAGE_SECOURS = 'Votre message n\'a pas pu être envoyé automatiquement. ' .
    'Écrivez-nous directement à ' . $DESTINATAIRE . ' ou appelez le 09 53 96 33 48.';

// ---- Couche 1 : honeypots --------------------------------------------------
if ($piege1 !== '' || $piege2 !== '') {
    journal('piege', $piege1 !== '' ? 'honeypot-website' : 'honeypot-societe', $nom . ' <' . $email . '> ' . $message);
    // Faux succès : le robot croit que son message est parti.
    repondre(true, 'Merci ! Votre message a bien été envoyé.', $ajax);
}

// ---- Couche 3 : limite de débit par IP -------------------------------------
$ip = $_SERVER['REMOTE_ADDR'] ?? 'inconnue';
if (debits_ip($ip) >= $MAX_PAR_HEURE) {
    journal('rejet', 'limite-debit', $nom . ' <' . $email . '>');
    repondre(false, 'Trop de messages envoyés depuis votre connexion. Réessayez dans une heure, ' .
        'ou écrivez-nous directement à ' . $DESTINATAIRE . '.', $ajax);
}

// ---- Couche 2 : jeton temporel + origine de la requête ---------------------
$score = 0;
$raisons = [];

if ($ts === '') {
    $score += 2;
    $raisons[] = 'jeton-absent';
} elseif (!ctype_digit($ts) || (int)$ts < 1000000000000 || (int)$ts > (time() + 60) * 1000) {
    $score += 2;
    $raisons[] = 'jeton-invalide';
} else {
    $secondes = (microtime(true) * 1000 - (int)$ts) / 1000;
    if ($secondes < $DELAI_MINI) {
        $score += 2;
        $raisons[] = 'remplissage-' . round($secondes, 1) . 's';
    } elseif ($secondes > 48 * 3600) {
        $score += 1;
        $raisons[] = 'jeton-trop-vieux';
    }
}

$origine = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
if ($origine === '') {
    $score += 1;
    $raisons[] = 'origine-absente';
} elseif (strpos($origine, 'latelierberlingot.com') === false) {
    $score += 2;
    $raisons[] = 'origine-etrangere';
}

// ---- Validation classique des champs ---------------------------------------
$erreurs = [];
if ($nom === '' || mb_strlen($nom) > 100) {
    $erreurs[] = 'le nom';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
    $erreurs[] = 'un email valide';
}
if ($message === '' || mb_strlen($message) > 5000) {
    $erreurs[] = 'le message';
}
if ($tel !== '' && (mb_strlen($tel) > 30 || preg_match('/[\r\n]/', $tel))) {
    $erreurs[] = 'le téléphone';
}

if ($erreurs) {
    // Erreur de saisie bénigne : ne consomme pas le quota anti-spam.
    repondre(false, 'Merci de renseigner : ' . implode(', ', $erreurs) . '.', $ajax);
}

// ---- Couche 4 : analyse du contenu -----------------------------------------
$domaine_email = strtolower(substr(strrchr($email, '@'), 1));
$jetables = [
    'mailinator.com', 'yopmail.com', 'yopmail.fr', 'tempmail.com', 'temp-mail.org',
    'guerrillamail.com', '10minutemail.com', 'trashmail.com', 'sharklasers.com',
    'getnada.com', 'dispostable.com', 'maildrop.cc', 'fakeinbox.com', 'throwawaymail.com',
];
if (in_array($domaine_email, $jetables, true)) {
    journal('rejet', 'email-jetable', $nom . ' <' . $email . '> ' . $message);
    debits_enregistrer($ip);
    repondre(false, $MESSAGE_SECOURS, $ajax);
}

$contenu = $message . ' ' . $nom;

// Liens et BBCode : un message honnête de parent n'en contient quasiment jamais.
$liens = substr_count(strtolower($contenu), 'http') + substr_count(strtolower($contenu), 'www.');
$liens += preg_match_all('/\[url|\[link/i', $contenu);
if ($liens >= 2) {
    journal('rejet', 'liens x' . $liens, $nom . ' <' . $email . '> ' . $message);
    debits_enregistrer($ip);
    repondre(false, $MESSAGE_SECOURS, $ajax);
}
if ($liens === 1) {
    $score += 2;
    $raisons[] = '1-lien';
}

// Adresses email collées dans le corps du message.
$emails_dans_message = preg_match_all('/[\w.+-]+@[\w-]+\.[a-z]{2,}/i', $message);
if ($emails_dans_message >= 3) {
    journal('rejet', 'emails x' . $emails_dans_message, $nom . ' <' . $email . '> ' . $message);
    debits_enregistrer($ip);
    repondre(false, $MESSAGE_SECOURS, $ajax);
}
if ($emails_dans_message === 2) {
    $score += 2;
    $raisons[] = '2-emails';
}

// Mots-clés récurrents du spam de formulaire.
$motifs_spam = '/viagra|cialis|casino|poker|cryptomonnaie|bitcoin|escort|porn|xxx|' .
    'backlink|seo services|increase (your |website )?traffic|buy followers|acheter des (followers|abonnés)|' .
    'payday|cheap loan|international dating|казино|виагра|порно|代发|广告|引流/iu';
$trouvés = preg_match_all($motifs_spam, $contenu);
if ($trouvés >= 2) {
    journal('rejet', 'mots-spam x' . $trouvés, $nom . ' <' . $email . '> ' . $message);
    debits_enregistrer($ip);
    repondre(false, $MESSAGE_SECOURS, $ajax);
}
if ($trouvés === 1) {
    $score += 2;
    $raisons[] = '1-mot-spam';
}

// Alphabets étrangers : le public du site est exclusivement francophone.
$caracteres = preg_replace('/\s+/u', '', $contenu);
$etrangers = preg_match_all('/[\p{Cyrillic}\p{Han}\p{Hangul}\p{Arabic}\p{Thai}]/u', $caracteres);
if ($etrangers > 0 && $etrangers / max(mb_strlen($caracteres), 1) > 0.15) {
    journal('rejet', 'alphabet-etranger', $nom . ' <' . $email . '> ' . $message);
    debits_enregistrer($ip);
    repondre(false, $MESSAGE_SECOURS, $ajax);
}

// Nom farfelu : URL, arobase, ou uniquement des chiffres.
if (preg_match('/https?:|www\.|@/i', $nom)) {
    journal('rejet', 'nom-avec-lien', $nom . ' <' . $email . '> ' . $message);
    debits_enregistrer($ip);
    repondre(false, $MESSAGE_SECOURS, $ajax);
}
if (preg_match('/^\d+$/', $nom)) {
    $score += 1;
    $raisons[] = 'nom-numerique';
}

// Message très court : les robots d'essai envoient souvent "test" ou "hello".
if (mb_strlen($message) < 10) {
    $score += 1;
    $raisons[] = 'message-court';
}

// ---- Verdict du score ------------------------------------------------------
if ($score > $SCORE_MAXI) {
    journal('rejet', 'score ' . $score . ' : ' . implode(', ', $raisons), $nom . ' <' . $email . '> ' . $message);
    debits_enregistrer($ip);
    repondre(false, $MESSAGE_SECOURS, $ajax);
}

// Nettoyage anti-injection d'en-têtes (retours à la ligne interdits).
$nom_s   = str_replace(["\r", "\n"], ' ', $nom);
$email_s = str_replace(["\r", "\n"], ' ', $email);
$tel_s   = str_replace(["\r", "\n"], ' ', $tel);

// ---- Envoi -----------------------------------------------------------------
$sujet = 'Demande depuis le site — ' . $nom_s;
$corps =
    "Nouveau message depuis le formulaire de contact du site.\n" .
    str_repeat('-', 50) . "\n\n" .
    $message . "\n\n" .
    str_repeat('-', 50) . "\n" .
    "Nom : " . $nom_s . "\n" .
    "Email : " . $email_s . "\n" .
    ($tel_s !== '' ? "Téléphone : " . $tel_s . "\n" : "") .
    "Envoyé le : " . date('d/m/Y à H:i');

$headers =
    'From: L\'Atelier Berlingot <' . $EXPEDITEUR . ">\r\n" .
    'Reply-To: ' . $nom_s . ' <' . $email_s . ">\r\n" .
    'Content-Type: text/plain; charset=utf-8' . "\r\n" .
    'X-Mailer: PHP/' . phpversion();

$envoye = @mail($DESTINATAIRE, $sujet, $corps, $headers);

debits_enregistrer($ip);

if ($envoye) {
    journal('envoye', $score > 0 ? 'ok (score ' . $score . ' : ' . implode(', ', $raisons) . ')' : '', $nom . ' <' . $email . '>');
    repondre(true, 'Merci ! Votre message a bien été envoyé, nous vous répondrons vite.', $ajax);
} else {
    journal('echec-envoi', '', $nom . ' <' . $email . '>');
    repondre(false, 'L\'envoi a échoué. Vous pouvez nous écrire directement à ' . $DESTINATAIRE . '.', $ajax);
}
