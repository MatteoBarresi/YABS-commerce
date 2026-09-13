<?php
/**
 * Connessione PDO al database 634692 (XAMPP / MariaDB).
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/functions.php';



function getConnection(): PDO
{
    static $pdo = null;

    //tipo singleton...
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    //charset inutile
    $dsn = 'mysql:host=localhost;dbname=634692;charset=utf8mb4';

    try {
        $pdo = new PDO($dsn, 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            
            //questi non servono
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        redirect('/db_error.php');
    }

    return $pdo;
}