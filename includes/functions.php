<?php
/**
 * Funzioni condivise: validazione, hash password, risposte errore.
 */

declare(strict_types=1);

/** Lunghezze da schema.sql */
const USERNAME_MAX = 20;
const EMAIL_MAX = 100;
const PASSWORD_MAX_PLAIN = 64; //?
const INDIRIZZO_MAX = 50;
const RECENSIONE_TESTO_MAX = 255;
const RECENSIONE_VOTO_MIN = 1;
const RECENSIONE_VOTO_MAX = 5;


/* 
se c'è un errore (MESSAGGIO custom da passare noi), lo memorizza in sessione 
porta al path specificato (a partire da dove viene chiamata la funzione)
*/
function redirect(string $path, ?string $error = null): void
{
    if (isset($pdo) && $pdo->inTransaction()) { //utile es in pagamento_process
        $pdo->rollBack();
    }
    if ($error !== null) {
        $_SESSION['flash_error'] = $error;
    }
    header('Location: ' . BASE_URL . $path);
    exit;
}

/** 
 * ripetizione di sopra - ma nome parlante 
 */
function failed_request(string $message, string $redirectPath): void
{
    redirect($redirectPath, $message);
}

/*  
    TODO: CANCELLARE FUNZIONE INUTILE
    funzione per salare psw - usare password_hash per memorizzare nel db e password_verify(input_user, hashed_psw) per confontare
*/
function hash_password(string $password, string $username): string
{
    $salt = strtolower($username);
    $raw = hash('sha256', strtolower($password) . $salt);
    return substr($raw, 0, 25);
}


/* 
se c'è un errore lo restituisce, dopo aver svuotato placeholder errori sessione
*/
function flash_error(): ?string
{
    if (!empty($_SESSION['flash_error'])) {
        $msg = $_SESSION['flash_error'];
        unset($_SESSION['flash_error']);
        return $msg;
    }
    return null;
}

/*
come sopra
*/
function flash_success(): ?string
{
    if (!empty($_SESSION['flash_success'])) {
        $msg = $_SESSION['flash_success'];
        unset($_SESSION['flash_success']);
        return $msg;
    }
    return null;
}

/* 
prende stringa e dice se contiene questo tipo di testo:
<script
javascript:
onxxx = 
case insensitive
non bastava htmlspecialchars in questo caso
*/
function contains_dangerous_markup(string $value): bool
{
    return (bool) preg_match('/<script|javascript:|on\w+\s*=/i', $value);
}

/* 
    toglie spazi bianchi e tag html
*/
function sanitize_string(string $value): string
{
    $value = trim($value);
    $value = strip_tags($value);
    return $value;
}

/* 
    check se vuoto OR troppo lungo 
    poi regex per caratteri - dice min 3, max 20 (ma non usa const per qualche motivo)
    poi se contiene markup
*/

function validate_username(string $username): ?string
{
    $username = sanitize_string($username);
    if ($username === '') {
        return 'Username obbligatorio.';
    }
    if (strlen($username) > USERNAME_MAX) {
        return 'Username troppo lungo (max ' . USERNAME_MAX . ' caratteri).';
    }
    //'/^[a-zA-Z0-9_]{3,'. USERNAME_MAX .'}$/'      usare questa stringa
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        return 'Username non valido (3–20 caratteri: lettere, numeri, _).';
    }
    if (contains_dangerous_markup($username)) {
        return 'Username non consentito.';
    }
    return null;
}

/* 
    vuoto, max caratteri, rejects commenti, whitespace, dotless domains 
    infine regex - forse bastava questa (e max caratteri perché non c'è nella regex)
*/
function validate_email(string $email): ?string
{
    $email = strtolower(sanitize_string($email));
    if ($email === '') {
        return 'Email obbligatoria.';
    }
    if (strlen($email) > EMAIL_MAX) {
        return 'Email troppo lunga.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Email non valida.';
    }

    if (!preg_match('/^[\w._-]+@[\w.-]+\.[a-zA-Z]{2,}$/', $email)) {
        return 'Formato email non valido.';
    }
    return null;
}

/* 
psw vuota, corta, lunga
*/
function validate_password(string $password): ?string
{
    if ($password === '') {
        return 'Password obbligatoria.';
    }
    if (strlen($password) < 6) {
        return 'Password troppo corta (minimo 6 caratteri).';
    }
    if (strlen($password) > PASSWORD_MAX_PLAIN) {
        return 'Password troppo lunga.';
    }
    return null;
}


/* 
    vuoto/null, poi sanifica, poi max, poi tags/js
*/
function validate_indirizzo(?string $indirizzo): ?string
{
    if ($indirizzo === null || trim($indirizzo) === '') {
        return null;
    }
    $indirizzo = sanitize_string($indirizzo);
    if (strlen($indirizzo) > INDIRIZZO_MAX) {
        return 'Indirizzo troppo lungo (max ' . INDIRIZZO_MAX . ' caratteri).';
    }
    if (contains_dangerous_markup($indirizzo)) {
        return 'Indirizzo non consentito.';
    }
    return null;
}


/* 
    classe utente o catalog.php
    select 1 from utente in base a user o email - con select * prendeva il valore della prima row
    restituisce la colonna (poi cast a bool) o false se non c'è corrispondenza
*/
function user_exists(PDO $pdo, string $username, string $email): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM utente WHERE LOWER(username) = LOWER(:u) OR LOWER(email) = LOWER(:e) LIMIT 1'
    );
    $stmt->execute([':u' => $username, ':e' => $email]);
    return (bool) $stmt->fetchColumn(); //1 or 0
}

/* 
    rimanda a home se ha fatto login (quindi non è guest) - usata in register e login 
*/
function require_guest(): void
{
    if (!empty($_SESSION['username'])) {
        redirect('/home.php');
    }
}

/* 
    rimanda a login se utente non ha fatto accesso (var non dichiarata / vuota)
*/
function require_login(): void
{
    if (empty($_SESSION['username'])) {
        redirect('/login.php', 'Accedi per continuare.');
    }
}

/**
 * non uso nested cookies (es saluto[eng]) altrimenti dovrei utilizzare $_SERVER['HTTP_COOKIE'] o funzione ricorsiva
 * https://stackoverflow.com/questions/2310558/how-to-delete-all-cookies-of-my-website-in-php
 */
function unset_cookies(){
    foreach($_COOKIE as $key => $value) 
        setcookie($key, '', 1);        
}
/* 
usata con script js e extraJS, per non avere duplicati (equivale a include once per array / set)
*/
function add_once_safe(string $script, array &$arr) :void{
    if(!in_array($script, $arr)) //controllo per script inclusi più volte tramite foreach
        array_push($arr, $script); //non posso farlo direttamente perché sovrascriverebbe azione in script inclusi prima nella stessa pagina
}
