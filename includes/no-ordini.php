<div class="card border-0 text-center py-5"
             style="background:var(--white); border:1px solid var(--lilac)!important; border-radius:12px;">
    <div class="card-body">
        <p style="font-size:3rem;">📭</p>
        <h2 class="h5 mb-2">Nessun ordine trovato</h2>

        <?php if ($tipo === 'cliente'): ?>
            <p class="text-muted mb-4">Non hai ancora effettuato ordini.</p>
            <a href="<?= BASE_URL ?>/home.php" class="btn btn-primary"
            style="width:auto; padding:0.6rem 1.5rem;">Inizia a fare acquisti</a>
        <?php else: ?>
            <p class="text-muted mb-0">Non hai ancora ricevuto ordini per i tuoi prodotti.</p>
        <?php endif; ?>
    </div>
</div>