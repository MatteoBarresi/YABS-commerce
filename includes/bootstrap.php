<?php
/**
 * ======================================
 *  NON CAMBIARE POSIZIONE DI QUESTO FILE
 * ======================================
 * 
 * avvia sessione, definisce root progetto, path relativo all'esecuzione, include funzioni.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) { //sessioni abilitate ma ancora nessuna attiva - verifica perché se si attiva 2 volte, dà errore
    session_start();
}

/*
=============================================================
dir genitore della dir di QUESTO script - parte da disco
=============================================================
*/
define('APP_ROOT', dirname(__DIR__));  //non uso const perché accetta solo valori noti a runtime - non chiamate a funzione

/* 
=============================================================================
dir genitore dello script IN ESECUZIONE (CAMBIA in base a dove viene incluso)
=============================================================================
*/
$currentScriptParentDir = dirname((string)$_SERVER['SCRIPT_NAME']);  //cast in caso di null
$currentScriptParentDir = rtrim($currentScriptParentDir, '/\\'); //toglie "/\" finali - per quando si passa C:\\ oppure /directory/--> se si applica dirname, abbiamo "/" finale
$currentScriptParentDir = str_replace('\\', '/', $currentScriptParentDir); //windows os
define('BASE_URL', $currentScriptParentDir); // sostituisce backslash con slash, nella dir genitore dello script in esecuzione - inutile if finale perché se non ne trova, restituisce già stringa vuota


require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/functions.php';