<?php
$pageTitle = $pageTitle ?? 'BDD E-commerce';

$extraJs = $extraJs ?? []; //js da eseguire nel footer (form validation / ajax)

$withNavbar = $withNavbar ?? false; 


?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <!-- ??? --> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
     
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Font Awesome-->
    <link rel="stylesheet" 
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />
    
    <!-- include bootstrap--> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- include css custom--> 
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
    
    
    <!-- importa ajax e jquery validator -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/localization/messages_it.min.js"></script>

    <!-- parent dir script in esecuzione - root del progetto-->
    <script> const baseUrl = "<?=  htmlspecialchars(BASE_URL) ?>" ; </script>

    <?php require_once APP_ROOT . '/includes/enums.php'; //funzioni enum
    //costanti JS : da array associativo php a oggetto json. se aggiorno enums.php, aggiorna anche queste 
    ?>
    <script>
        const ORDINE_STATO_MAP = <?= json_encode(ordine_stato_map(), JSON_UNESCAPED_UNICODE) //flag caratteri accentati ecc non diventano \u0000 ?>;
        const NOTIF_TIPO_MAP   = <?= json_encode(notif_tipo_map(),   JSON_UNESCAPED_UNICODE) ?>;
    </script>
</head>

<!--body data-base-url="<?php //htmlspecialchars(BASE_URL) ?>"-->
<body>

<?php 
if ($withNavbar) { 
    echo "<script>console.log('navbar inclusa');</script>";
    //aggiunge script search - va bene in qualunque punto, visto che è usato nel footer
    $extraJs = array_values( //no associativi
        array_unique( //set - no duplicati
            array_merge( //unisce js per form con quello per search
                $extraJs, ['assets/js/search.js'])
        )
    );

    require APP_ROOT . '/includes/navbar.php'; 
} 
?>