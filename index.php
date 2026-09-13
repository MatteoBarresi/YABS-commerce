<?php
require_once __DIR__ . '/includes/bootstrap.php';

//entrata nel sito--> se loggato, vai home, altrimenti pagina login
if (!empty($_SESSION['username'])) {
    redirect('/home.php');
}

redirect('/login.php');
