<?php
require_once __DIR__ . '/includes/bootstrap.php';

unset_cookies();
session_destroy(); //rimuove variabili senza disattivarla 

header('Location: ' . BASE_URL . '/login.php');
exit;
