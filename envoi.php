<?php
/**
 * L'Atelier Berlingot — Traitement du formulaire de contact
 *
 * Reçoit le POST du formulaire (index.html#contact) et envoie le message
 * par la fonction mail() de l'hébergement.
 * Répond en JSON si la requête vient du JS (fetch), sinon redirige
 * vers index.html#contact avec un code de statut.
 */

// ---- Configuration ---------------------------------------------------------
ini_set('display_errors', '0'); // jamais d'erreur PHP visible par les visiteurs

$DESTINATAIRE = 'eaje.atelierberlingot@gmail.com'; // boîte qui reçoit les demandes
$EXPEDITEUR   = 'noreply@latelierberlingot.com';   // doit rester sur le domaine hébergé
$SITE_URL     = 'https://latelierberlingot.com/';

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
$nom    = trim($_POST['name'] ?? '');
$email  = trim($_POST['email'] ?? '');
$tel    = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');
$piege  = trim($_POST['website'] ?? ''); // honeypot anti-spam : doit rester vide

if ($piege !== '') {
    // Bot détecté : on répond "ok" sans rien envoyer pour ne pas lui donner d'indice.
    repondre(true, 'Merci ! Votre message a bien été envoyé.', $ajax);
}

// ---- Validation ------------------------------------------------------------
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
    repondre(false, 'Merci de renseigner : ' . implode(', ', $erreurs) . '.', $ajax);
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

if ($envoye) {
    repondre(true, 'Merci ! Votre message a bien été envoyé, nous vous répondrons vite.', $ajax);
} else {
    repondre(false, 'L\'envoi a échoué. Vous pouvez nous écrire directement à ' . $DESTINATAIRE . '.', $ajax);
}
