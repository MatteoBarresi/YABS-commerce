    <footer class="site-footer">
        <p>Progetto universitario — BDD Barresi 2026</p>
    </footer>
    <script src="<?= BASE_URL ?>/assets/js/validation.js"></script>

<?php 
/* 
extraJS  è un array con path dei file js da includere, presente molte pagine . 
è messo nel footer perché il resto della pagina è sicuramente disponibile (src come immagini non per forza)
alternativa era mettere in head e usare defer (anche qui non aspetta immagini - per quello serve istruzione in js)


*/

if (!empty($extraJs)):  //se c'è extra js, lo esegue/include  qui  ?>
<?php 
//$extraJs = array_unique($extraJs); //ulteriore check 
foreach ($extraJs as $js): ?>
    <script src="<?= BASE_URL ?>/<?= htmlspecialchars($js) ?>"></script>
    <?php echo "<script>console.log('$js');</script>"; ?>
<?php endforeach; ?>
<?php endif; ?>
</body>
</html>
