<?php
// Fichier : navbar.php
// Ce fichier contient uniquement la fonction de génération du menu

function renderNavbar($role, $current_page) {
    // Notez l'utilisation des guillemets simples ' pour englober tout le HTML
    // Et des guillemets doubles " pour les attributs HTML (class, src, href)
    $html = '<div class="navbar">
                <a href="dashboard.php" class="nav-brand">
                    <img src="img/logo.png" alt="Logo" class="nav-logo"> LogiFête PGI
                </a>
                <div class="nav-links">';

    // 1. Menu VENTES (Pour Admin & Commercial)
    if ($role == 'commercial' || $role == 'admin') {
        $active = (in_array($current_page, ['commandes.php', 'nouvelle_commande.php', 'clients.php', 'planning.php'])) ? 'active' : '';
        $html .= '<div class="dropdown">
                    <a href="#" class="nav-item '.$active.'">Ventes ▾</a>
                    <div class="dropdown-content">
                        <a href="nouvelle_commande.php">➕ Nouveau Devis</a>
                        <a href="commandes.php">📑 Liste Commandes</a>
                        <a href="clients.php">👥 Clients</a>
                        <a href="planning.php">📅 Planning</a>
                        <a href="comptabilite.php">💰 Finance / Paiements</a>
                    </div>
                  </div>';
    }

    // 2. Menu LOGISTIQUE (Pour Tous)
    $active_log = (in_array($current_page, ['stock.php', 'missions.php', 'retours.php', 'maintenance.php'])) ? 'active' : '';
    $html .= '<div class="dropdown">
                <a href="#" class="nav-item '.$active_log.'">Logistique ▾</a>
                <div class="dropdown-content">
                    <a href="stock.php">📦 Inventaire / Stock</a>';
    
    if ($role == 'technicien' || $role == 'admin') {
        $html .= '  <a href="missions.php">⚙️ Mes Missions</a>
                    <a href="retours.php">📥 Retours Matériel</a>
                    <a href="maintenance.php">🛠️ Atelier / Pannes</a>
                    <a href="signaler_panne.php">🚨 Signaler Panne</a>
                    <a href="fournisseurs.php">🏭 Fournisseurs</a>';
    }
    $html .= '  </div>
              </div>';

    // 3. Menu ADMINISTRATION (Admin Only)
    if ($role == 'admin') {
        $active_admin = (in_array($current_page, ['admin_users.php', 'admin_logs.php', 'admin_stock.php'])) ? 'active' : '';
        $html .= '<div class="dropdown">
                    <a href="#" class="nav-item '.$active_admin.'">Admin ▾</a>
                    <div class="dropdown-content">
                        <a href="admin_users.php">👤 Utilisateurs</a>
                        <a href="admin_stock.php">📝 Catalogue (Ref)</a>
                        <a href="admin_kits.php">🧰 Gestion Kits</a>
                        <a href="admin_logs.php">🕵️‍♂️ Logs / Audit</a>
                        <a href="admin_backup.php">💾 Sauvegarde BDD</a>
                    </div>
                  </div>';
    }

    $html .= '</div> <div class="nav-profile">
                  <span class="role-badge-nav">'.strtoupper($role).'</span>
                  <a href="profil.php" style="color:white; text-decoration:none;">'.htmlspecialchars($_SESSION['nom']).'</a>
                  <a href="logout.php" class="btn-danger" style="padding:5px 10px; font-size:0.8em;">Déconnexion</a>
              </div>
            </div>';
    
    return $html;
}
?>