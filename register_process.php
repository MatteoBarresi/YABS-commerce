<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_guest();

//se non si arriva con un post
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/register.php');
}

//primo controllo: se sono settati, altrimenti stringa vuota
$username = sanitize_string($_POST['username'] ?? ''); //spazi bianchi e tag html
$email = strtolower(sanitize_string($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';
$indirizzo = isset($_POST['indirizzo']) ? sanitize_string($_POST['indirizzo']) : '';

/* 
    per ogni campo, esegue controllo (functions.php)
    funzioni che restituiscono una stringa di errore o null (se tutto va bene) 
*/
foreach ([
    validate_username($username),
    validate_email($email),
    validate_password($password),
    validate_indirizzo($indirizzo !== '' ? $indirizzo : null),
] as $err) {
    if ($err !== null) {
        failed_request($err, '/register.php');
    }
}

//controlla password
if ($password !== $passwordConfirm) {
    failed_request('Le password non coincidono.', '/register.php');
}

//cerca se esiste già un utente con questo user
try {
    $pdo = getConnection();
    if (user_exists($pdo, $username, $email)) {
        failed_request('Username o email già in uso.', '/register.php');
    }
   
} catch (PDOException $e) {
    //error_log($e->getMessage());
    failed_request('Errore di sistema. Riprova più tardi.', '/register.php');
}

//salva dati in sessione
$_SESSION['register_draft'] = [
    'username' => $username,
    'email' => $email,
    'password_hash' => hash_password($password, $username), //password_hash($password, PASSWORD_DEFAULT);
    'indirizzo' => $indirizzo,
];

//altro step di registrazione
redirect('/register_tipo.php');
