<?php
/* 
gestisce dati passati con la form di login.php
condizioni: utente guest; si arriva con post
*/
require_once __DIR__ . '/includes/bootstrap.php';
require_guest();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/login.php');
}

//controlli input
$login = strtolower(sanitize_string($_POST['login'] ?? ''));
$password = $_POST['password'] ?? '';

if ($login === '') {
    failed_request('Inserisci username o email.', '/login.php');
}

$passErr = validate_password($password);
if ($passErr !== null) {
    failed_request($passErr, '/login.php');
}

//seleziona dati utente (email è unique) per confrontarli con quelli inseriti
try {
    $pdo = getConnection();
    $stmt = $pdo->prepare(
        'SELECT username, email, psw, tipo_utente
         FROM utente
         WHERE LOWER(username) = :usn OR LOWER(email) = :em'

    );
    
    $stmt->execute([':usn' => $login, ':em' => $login]);
    $user = $stmt->fetch(); //false on failure or no rows

} catch (PDOException $e) {
    failed_request('Errore di sistema.', '/login.php');
}

if (!$user) { 
    failed_request('Credenziali non valide.', '/login.php');
}

//verifica password
$hash = hash_password($password, $user['username']); //password_hash($password, PASSWORD_DEFAULT); - ma non serve - togliere questa riga

if (!hash_equals($user['psw'] ?? '', $hash)) { //password_verify($password ,$user['psw'])) //post_in_chiaro, hash
    failed_request('Credenziali non valide.', '/login.php');
}

//setto variabili di sessione
$_SESSION['username'] = $user['username'];
$_SESSION['tipo_utente'] = $user['tipo_utente'];
$_SESSION['email'] = $user['email'];

$_SESSION['flash_success'] = 'Bentornato, ' . $user['username'] . '!';
redirect('/home.php');
