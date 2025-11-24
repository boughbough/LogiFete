<?php
// Fichier : commandes.php
session_start();
require 'db.php';
require 'navbar.php';

// Sécurité
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'commercial' && $_SESSION['role'] != 'admin')) {
    header("Location: dashboard.php");
    exit;
}

$msg = "";
$msg_type = "";

// --- TRAITEMENT 1 : ACTION DE RELANCE DEVIS ---
if (isset($_POST['relancer_devis_id'])) {
    $id = $_POST['relancer_devis_id'];
    
    // Mise à jour de la date de relance
    $pdo->prepare("UPDATE commande SET date_relance_devis = NOW() WHERE id_commande = ?")->execute([$id]);
    
    // Message Flash pour confirmation UX
    $_SESSION['flash_message'] = "📧 Relance du devis #$id enregistrée.";
    $_SESSION['flash_cmd_id'] = $id; 
    
    // LOG
    if(function_exists('ajouterLog')) ajouterLog($pdo, "RELANCE DEVIS", "Relance envoyée pour le devis #$id.");

    header("Location: commandes.php");
    exit;
}
// --- FIN TRAITEMENT RELANCE DEVIS ---

// --- FILTRES ---
$where = ["1=1"];
$params = [];

// Filtre par Client
if (!empty($_GET['client'])) {
    $where[] = "c.id_client = ?";
    $params[] = $_GET['client'];
}
// Filtre par Statut
if (!empty($_GET['statut'])) {
    $where[] = "c.etat = ?";
    $params[] = $_GET['statut'];
}

// NOUVEAU : FILTRE DEVIS EN RETARD DE RELANCE (Plus vieux que 15 jours et statut 'devis')
if (!empty($_GET['alerte_devis']) && $_GET['alerte_devis'] == 'oui') {
    // 15 jours est la durée de validité supposée
    $where[] = "c.etat = 'devis' AND c.date_creation < DATE_SUB(CURDATE(), INTERVAL 15 DAY)";
}


$sql = "SELECT c.*, cl.nom_societe, u.nom as commercial 
        FROM commande c
        JOIN client cl ON c.id_client = cl.id_client
        JOIN utilisateur u ON c.id_commercial = u.id_user
        WHERE " . implode(" AND ", $where) . "
        ORDER BY c.date_creation DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$commandes = $stmt->fetchAll();

// Liste des clients pour le filtre
$clients_list = $pdo->query("SELECT id_client, nom_societe FROM client ORDER BY nom_societe")->fetchAll();

// KPI : Nombre de devis à relancer
$nb_devis_a_relancer = $pdo->query("SELECT COUNT(*) FROM commande WHERE etat = 'devis' AND date_creation < DATE_SUB(CURDATE(), INTERVAL 15 DAY) AND (date_relance_devis IS NULL OR date_relance_devis < DATE_SUB(CURDATE(), INTERVAL 7 DAY))")->fetchColumn();
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>Suivi des Commandes</title>
</head>
<body>

<?= renderNavbar($_SESSION['role'], $current_page) ?>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>📑 Suivi des Commandes</h1>
        <a href="nouvelle_commande.php" class="btn-add">+ Nouveau Devis</a>
    </div>

    <?php if ($nb_devis_a_relancer > 0 && (!isset($_GET['alerte_devis']) || $_GET['alerte_devis'] != 'oui')): ?>
        <div style="background:#fff3cd; color:#856404; padding:15px; border:1px solid #ffeeba; border-radius:5px; margin-bottom:20px; text-align:center;">
            🔔 **ALERTE :** Il y a **<?= $nb_devis_a_relancer ?>** devis en attente de relance ! 
            <a href="?alerte_devis=oui" style="color:#856404; font-weight:bold; text-decoration:underline;">
                Afficher la liste complète
            </a>
        </div>
    <?php endif; ?>
    <form method="GET" style="background:#f1f1f1; padding:15px; border-radius:5px; margin-bottom:20px; display:flex; gap:10px; align-items:center;">
        <label>Filtrer par :</label>
        
        <select name="client" onchange="this.form.submit()" style="margin:0; cursor:pointer;">
            <option value="">-- Tous les clients --</option>
            <?php foreach($clients_list as $cl): ?>
                <option value="<?= $cl['id_client'] ?>" <?= (isset($_GET['client']) && $_GET['client'] == $cl['id_client']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cl['nom_societe']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="statut" onchange="this.form.submit()" style="margin:0; cursor:pointer;">
            <option value="">-- Tous statuts --</option>
            <option value="devis" <?= (isset($_GET['statut']) && $_GET['statut'] == 'devis') ? 'selected' : '' ?>>Devis</option>
            <option value="validee" <?= (isset($_GET['statut']) && $_GET['statut'] == 'validee') ? 'selected' : '' ?>>Validée</option>
            <option value="terminee" <?= (isset($_GET['statut']) && $_GET['statut'] == 'terminee') ? 'selected' : '' ?>>Terminée</option>
        </select>
        
        <a href="commandes.php" style="color:#666; margin-left:10px;">Tout réinitialiser</a>
    </form>

    <table>
        <thead>
            <tr>
                <th>N°</th>
                <th>Date Création</th>
                <th>Client</th>
                <th>Période</th>
                <th>Statut</th>
                <th>Relance Devis</th> <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($commandes as $c): ?>
            <tr>
                <td><strong>#<?= $c['id_commande'] ?></strong></td>
                <td><?= date('d/m/Y', strtotime($c['date_creation'])) ?></td>
                <td><?= htmlspecialchars($c['nom_societe']) ?></td>
                <td>
                    Du <?= date('d/m', strtotime($c['date_debut'])) ?> 
                    au <?= date('d/m', strtotime($c['date_fin'])) ?>
                </td>
                <td>
                    <?php 
                        $badges = [
                            'devis' => 'background:#f39c12; color:white;',
                            'validee' => 'background:#27ae60; color:white;',
                            'terminee' => 'background:#7f8c8d; color:white;',
                            'annulee' => 'background:#c0392b; color:white;'
                        ];
                        $style = $badges[$c['etat']] ?? '';
                    ?>
                    <span style="padding:4px 8px; border-radius:4px; font-size:0.85em; font-weight:bold; <?= $style ?>">
                        <?= strtoupper($c['etat']) ?>
                    </span>
                </td>
                
                <td>
                    <?php if ($c['etat'] == 'devis'): ?>
                        <form method="POST">
                            <input type="hidden" name="relancer_devis_id" value="<?= $c['id_commande'] ?>">
                            <button type="submit" class="btn" style="background:#f39c12; padding:5px 10px; font-size:0.7em;">
                                📧 Relancer Client
                            </button>
                        </form>
                        <?php if($c['date_relance_devis']): ?>
                            <small style="color:#e67e22;">Dernière: <?= date('d/m/Y', strtotime($c['date_relance_devis'])) ?></small>
                        <?php endif; ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                
                <td>
                    <a href="commande_details.php?id=<?= $c['id_commande'] ?>" class="btn" style="padding:5px 10px; font-size:0.8em;">
                        👁️ Gérer
                    </a>
                    
                    <?php if($c['etat'] == 'validee'): ?>
                        <a href="facture.php?id=<?= $c['id_commande'] ?>" target="_blank" class="btn" style="padding:5px 10px; font-size:0.8em; background:#2c3e50;">
                            📄 Facture
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>