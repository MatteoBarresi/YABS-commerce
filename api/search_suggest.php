<?php
/* 
script che gestisce chiamata ajax di search.js = quando l'utente digita nella searchbar
*/


require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog.php';

//che tipo di dato stiamo restituendo
header('Content-Type: application/json; charset=utf-8');

//TODO: utente non loggato non può cercare
if (empty($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['products' => [], 'vendors' => []]);
    exit;
}

//controlli stringa passata
$q = sanitize_string($_GET['q'] ?? '');
if (strlen($q) == 0) { 
    echo json_encode(['products' => [], 'vendors' => []]);
    exit;
}
//criterio di ricerca: stringa non interrotta in mezzo ad altro
$like = '%' . strtolower($q) . '%';

try {
    $pdo = getConnection();

    //TODO: diventano metodi in classi?
    //query: cerca nome di un prodotto in vendita con questo nome
    $stmtProd = $pdo->prepare(
        'SELECT p.id, p.nome
         FROM prodotto p
         WHERE p.user_negoziante IS NOT NULL AND LOWER(p.nome) LIKE :q
         ORDER BY p.nome ASC
         LIMIT 6'
    );
    $stmtProd->execute([':q' => $like]);
    $products = $stmtProd->fetchAll();

    //cerca negoziante con questo nome
    $stmtVendor = $pdo->prepare(
        'SELECT u.username
         FROM utente u
         WHERE u.tipo_utente = \'negoziante\' AND LOWER(u.username) LIKE :q
         ORDER BY u.username ASC
         LIMIT 6'
    );
    $stmtVendor->execute([':q' => $like]);
    $vendors = $stmtVendor->fetchAll();

    //restituisce due oggetti [products=> {} , vendor => {}] ognuno contiene un oggetto per ogni risultato
    echo json_encode([
        
        'products' => array_map( 
            fn ($item) => [
            'id' => (int) $item['id'],
            'nome' => $item['nome'],
            'type' => 'prodotto', 
        ], $products),

        'vendors' => array_map( 
        fn ($item) => [
            'username' => $item['username'],
            'type' => 'negoziante',
        ], $vendors),
    ]);
} catch (Throwable $e) {
    error_log('search_suggest: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['products' => [], 'vendors' => []]);
}
