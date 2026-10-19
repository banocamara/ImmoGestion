<aside class="sidebar" id="sidebar">

<div class="brand">
    Immo<span>Gestion</span>
</div>

<div class="sidebar-title">Principal</div>

<a href="/ImmoGestion/dashboard.php" class="active">
    🏠 Tableau de bord
</a>

<div class="sidebar-title">Gestion immobilière</div>

<a href="/ImmoGestion/maisons/index.php">
    🏢 Maisons
</a>

<a href="/ImmoGestion/appartements/index.php">
    🏘️ Appartements
</a>

<a href="/ImmoGestion/locataires/index.php">
    👥 Locataires
</a>

<a href="/ImmoGestion/locations/index.php">
    📝 Locations
</a>

<a href="/ImmoGestion/paiements/index.php">
    💰 Paiements
</a>

<a href="/ImmoGestion/paiements/soldes.php">
    📊 Suivi des loyers
</a>

<a href="/ImmoGestion/travaux/depenses.php" class="nav-link">
    <span>🧾</span>
    <span>Dépenses</span>
</a>

<a href="/ImmoGestion/travaux/index.php">
    🔧 Travaux
</a>

<?php if (estSuperAdmin()): ?>

    <div class="sidebar-title">Administration</div>

    <a href="/ImmoGestion/utilisateurs/index.php">
        ⚙️ Utilisateurs
    </a>

<?php endif; ?>

</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
