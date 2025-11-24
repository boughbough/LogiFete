<?php
// Fichier : retours.php (Version Finale : Logique Financière + Réconciliation Stock)
session_start();
require 'db.php';
require 'navbar.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$msg = "";

// --- CALCULATEUR CENTRALISÉ (Fonction helper) ---
function calculerMontants($data_commande, $valeur_jour_total) {
    // 1. Montant du Contrat Initial
    $ts_debut = strtotime($data_commande['date_debut']);
    $ts_fin = strtotime($data_commande['date_fin']);
    $duree_contrat = max(1, ($ts_fin - $ts_debut) / 86400 + 1);
    $montant_contrat = $valeur_jour_total * $duree_contrat;

    // 2. Montant Pénalité
    $retard_sec = time() - $ts_fin;
    $jours_retard = floor($retard_sec / 86400);
    $penalite = 0;
    
    if ($jours_retard > 0) {
        $penalite = $valeur_jour_total * $jours_retard * 1.5;
    }

    return [
        'contrat' => $montant_contrat,
        'penalite' => $penalite,
        'total' => $montant_contrat + $penalite,
        'jours_retard' => $jours_retard
    ];
}

// --- ACTION : CLÔTURER ---
if (isset($_POST['cloturer_id'])) {
    $id = $_POST['cloturer_id'];
    
    // Récupération données pour calcul
    $sql_calc = "SELECT c.date_debut, c.date_fin, 
                (
                    SELECT SUM(r.prix_jour) 
                    FROM reservation_equipement re 
                    JOIN equipement_physique e ON re.num_serie = e.num_serie
                    JOIN reference_materiel r ON e.id_reference = r.id_reference
                    WHERE re.id_commande = c.id_commande
                ) as valeur_jour
                FROM commande c WHERE c.id_commande = ?";
    $stmt_calc = $pdo->prepare($sql_calc);
    $stmt_calc->execute([$id]);
    $data = $stmt_calc->fetch();
    
    // Calcul
    $res = calculerMontants($data, $data['valeur_jour']);
    $amende = $res['penalite'];
    
    // 1. Update Commande (Statut et Pénalité)
    $sql_update = "UPDATE commande SET etat = 'terminee', penalite = ? WHERE id_commande = ?";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([$amende, $id]);
    
    // 2. RÉCONCILIATION DE STOCK BASÉE SUR LE BON DE RETOUR (NOUVEAU)
    $stmt_reco = $pdo->prepare("SELECT num_serie, etat_materiel FROM reservation_equipement WHERE id_commande = ?");
    $stmt_reco->execute([$id]);
    $retours_materiel = $stmt_reco->fetchAll();
    
    $nb_dommage = 0;
    
    foreach($retours_materiel as $r) {
        $new_statut = 'disponible';
        if (in_array($r['etat_materiel'], ['Endommagé'])) { // Si Endommagé, on le met en panne
            $new_statut = 'panne';
            $nb_dommage++;
        }
        
        // Mise à jour du statut de l'équipement physique
        $pdo->prepare("UPDATE equipement_physique SET statut = ? WHERE num_serie = ?")
            ->execute([$new_statut, $r['num_serie']]);
    }
    
    // Log
    if(function_exists('ajouterLog')) ajouterLog($pdo, "RETOUR CLIENT", "Clôture commande #$id. Pénalité : $amende €. Matériel endommagé : $nb_dommage.");
    
    $msg = "✅ Commande #$id clôturée. Total facturé : " . number_format($res['total'], 2) . " €. $nb_dommage équipements transférés à l'atelier.";
}

// --- FILTRES PHP (Serveur) ---
$where = ["c.etat = 'validee'"]; 
$params = [];

if (!empty($_GET['client'])) { $where[] = "c.id_client = ?"; $params[] = $_GET['client']; }
if (!empty($_GET['status'])) {
    if ($_GET['status'] == 'retard') { $where[] = "c.date_fin < CURDATE()"; }
    elseif ($_GET['status'] == 'encours') { $where[] = "c.date_fin >= CURDATE()"; }
}

// REQUÊTE D'AFFICHAGE
$sql = "SELECT c.*, cl.nom_societe,
        (
            SELECT SUM(r.prix_jour) 
            FROM reservation_equipement re 
            JOIN equipement_physique e ON re.num_serie = e.num_serie
            JOIN reference_materiel r ON e.id_reference = r.id_reference
            WHERE re.id_commande = c.id_commande
        ) as valeur_jour
        FROM commande c 
        JOIN client cl ON c.id_client = cl.id_client
        WHERE " . implode(" AND ", $where) . "
        ORDER BY c.date_fin ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$encours = $stmt->fetchAll();

$clients_list = $pdo->query("SELECT DISTINCT cl.id_client, cl.nom_societe FROM client cl JOIN commande c ON cl.id_client = c.id_client WHERE c.etat = 'validee' ORDER BY cl.nom_societe")->fetchAll();
$current_page = basename($_SERVER['PHP_SELF']);?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>Gestion des Retours</title>
    <style>
        .filters { background: #f9f9f9; padding: 15px; display: flex; gap: 15px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px; align-items: center; flex-wrap:wrap; }
        .filter-group { display: flex; align-items: center; gap: 5px; }
        select, input { margin: 0 !important; padding: 8px !important; }
    </style>
</head>
<body>

    <?= renderNavbar($_SESSION['role'], $current_page) ?>

<div class="container">
    <h1>⏳ Suivi des Retours Matériel</h1>
    <?php if($msg) echo "<p style='background:#d4edda; color:#155724; padding:10px; border-radius:5px;'>$msg</p>"; ?>

    <form method="GET" class="filters">
        
        <div class="filter-group" style="flex:2; min-width:250px;">
            <label>🔍</label>
            <input type="text" id="live_search_retours" placeholder="Tapez un nom de client ou N° commande..." style="width:100%;">
        </div>

        <div class="filter-group" style="flex:1;">
            <select name="client" onchange="this.form.submit()" style="width:100%; cursor:pointer;">
                <option value="">-- Tous les clients --</option>
                <?php foreach($clients_list as $cl): ?>
                    <option value="<?= $cl['id_client'] ?>" <?= (isset($_GET['client']) && $_GET['client'] == $cl['id_client']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cl['nom_societe']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-group" style="flex:1;">
            <select name="status" onchange="this.form.submit()" style="width:100%; cursor:pointer;">
                <option value="">-- Tous les états --</option>
                <option value="retard" <?= (isset($_GET['status']) && $_GET['status'] == 'retard') ? 'selected' : '' ?>>⚠️ En Retard</option>
                <option value="encours" <?= (isset($_GET['status']) && $_GET['status'] == 'encours') ? 'selected' : '' ?>>✅ Dans les temps</option>
            </select>
        </div>

        <a href="retours.php" style="margin-left:auto; color:#666; text-decoration:underline; white-space:nowrap;">Réinitialiser</a>
    </form>

    <table>
        <thead>
            <tr>
                <th>Commande</th>
                <th>Client</th>
                <th>Date de Fin Prévue</th>
                <th>Statut</th>
                <th>Total Dû (Estimé)</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if(count($encours)==0): ?>
                <tr><td colspan="6" style="text-align:center; padding:20px; color:gray;">Aucun retour en attente.</td></tr>
            <?php endif; ?>

            <?php foreach($encours as $c): 
                $res = calculerMontants($c, $c['valeur_jour']);
                $is_retard = $res['jours_retard'] > 0;
            ?>
            <tr style="<?= $is_retard ? 'background:#fff5f5;' : '' ?>">
                <td><a href="commande_details.php?id=<?= $c['id_commande'] ?>" target="_blank" style="font-weight:bold; color:#2c3e50;">#<?= $c['id_commande'] ?></a></td>
                
                <td><?= htmlspecialchars($c['nom_societe']) ?></td>
                
                <td><?= date('d/m/Y', strtotime($c['date_fin'])) ?></td>
                
                <td>
                    <?php if($is_retard): ?>
                        <span style="color:#c0392b; font-weight:bold;">⚠️ J+<?= $res['jours_retard'] ?></span>
                    <?php else: ?>
                        <span style="color:#27ae60; font-weight:bold;">En cours</span>
                    <?php endif; ?>
                </td>
                
                <td>
                    <strong style="color:#2c3e50; font-size:1.1em;"><?= number_format($res['total'], 2) ?> €</strong>
                    <?php if($is_retard): ?>
                        <br><small style="color:#c0392b;">(Pénalité incluse)</small>
                    <?php endif; ?>
                </td>
                
                <td>
                    <form method="POST" onsubmit="return confirm('ATTENTION : Le Bon de Livraison a-t-il été complété ? Total à facturer : <?= number_format($res['total'], 2) ?> €');">
                        <input type="hidden" name="cloturer_id" value="<?= $c['id_commande'] ?>">
                        <button type="submit" class="btn" style="padding:5px 10px; font-size:0.8em; background:#2c3e50;">
                            📥 Valider Retour
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
document.getElementById('live_search_retours').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('tbody tr');

    rows.forEach(row => {
        // On concatène tout le texte de la ligne pour chercher dedans
        let text = row.textContent.toLowerCase();
        if (text.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});
</script>

</body>
</html>