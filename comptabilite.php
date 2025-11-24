<?php
// Fichier : comptabilite.php
session_start();
require 'db.php';
require 'navbar.php';

// Sécurité : Pas de techniciens ici
if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'technicien') { header("Location: dashboard.php"); exit; }

$msg = "";
$msg_type = ""; // success, error, info

// --- ACTION 1 : ENCAISSEMENT ---
if (isset($_POST['action_payer'])) {
    $ids_to_pay = [];
    if (isset($_POST['payer_id'])) { $ids_to_pay[] = $_POST['payer_id']; } 
    elseif (isset($_POST['ids']) && !empty($_POST['ids'])) { $ids_to_pay = $_POST['ids']; }

    if (!empty($ids_to_pay)) {
        $date = date('Y-m-d');
        $placeholders = implode(',', array_fill(0, count($ids_to_pay), '?'));
        $params = array_merge([$date], $ids_to_pay);
        
        $sql = "UPDATE commande SET date_paiement = ? WHERE id_commande IN ($placeholders)";
        $pdo->prepare($sql)->execute($params);
        
        $count = count($ids_to_pay);
        $msg = "✅ $count paiement(s) enregistré(s) !";
        $msg_type = "success";
        if(function_exists('ajouterLog')) ajouterLog($pdo, "ENCAISSEMENT MASSE", "Encaissement de $count factures.");
    } else {
        $msg = "⚠️ Aucune facture sélectionnée.";
        $msg_type = "error";
    }
}

// --- ACTION 2 : ANNULER ---
if (isset($_POST['annuler_id'])) {
    $id = $_POST['annuler_id'];
    $pdo->prepare("UPDATE commande SET date_paiement = NULL WHERE id_commande = ?")->execute([$id]);
    $msg = "⚠️ Paiement annulé pour la commande #$id.";
    $msg_type = "error";
}

// --- ACTION 3 : RELANCE ---
if (isset($_POST['relancer_id'])) {
    $id = $_POST['relancer_id'];
    $email = $_POST['relancer_email'];
    $pdo->prepare("UPDATE commande SET date_relance = NOW() WHERE id_commande = ?")->execute([$id]);
    $msg = "📧 Relance envoyée à <em>$email</em>.";
    $msg_type = "info";
    if(function_exists('ajouterLog')) ajouterLog($pdo, "RELANCE", "Relance client facture #$id");
}

// --- FILTRES & RECHERCHE ---
$where = ["c.etat IN ('validee', 'terminee')"]; // Base : Seulement les commandes actives/finies
$params = [];

// Filtre Client
if (!empty($_GET['client'])) {
    $where[] = "c.id_client = ?";
    $params[] = $_GET['client'];
}

// Filtre Statut Paiement
if (!empty($_GET['statut'])) {
    if ($_GET['statut'] == 'paye') {
        $where[] = "c.date_paiement IS NOT NULL";
    } elseif ($_GET['statut'] == 'attente') {
        $where[] = "c.date_paiement IS NULL";
    }
}

// LISTE DES FACTURES
$sql = "SELECT c.*, cl.nom_societe, cl.email as client_email,
        (SELECT SUM(r.prix_jour) FROM reservation_equipement re JOIN reference_materiel r ON re.num_serie = (SELECT num_serie FROM equipement_physique WHERE num_serie = re.num_serie LIMIT 1) WHERE re.id_commande = c.id_commande) as valeur_jour
        FROM commande c 
        JOIN client cl ON c.id_client = cl.id_client 
        WHERE " . implode(" AND ", $where) . "
        ORDER BY c.date_paiement ASC, c.date_fin DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$factures = $stmt->fetchAll();

// Récupération Clients pour le filtre
$clients_list = $pdo->query("SELECT DISTINCT cl.id_client, cl.nom_societe FROM client cl JOIN commande c ON cl.id_client = c.id_client WHERE c.etat IN ('validee', 'terminee') ORDER BY cl.nom_societe")->fetchAll();

// CALCULS GLOBAUX (Sur le résultat filtré)
$ca_total = 0; $ca_encaisse = 0; $ca_attente = 0;
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>Comptabilité & Trésorerie</title>
    <style>
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .filters { background: #f9f9f9; padding: 15px; display: flex; gap: 15px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px; align-items: center; }
        select { margin: 0 !important; cursor: pointer; }
    </style>
    <script>
        function toggleAll(source) {
            document.getElementsByName('ids[]').forEach(c => c.checked = source.checked);
        }
    </script>
</head>
<body>

<?= renderNavbar($_SESSION['role'], $current_page) ?>

<div class="container">
    <h1>💰 Suivi de Trésorerie</h1>
    
    <?php if($msg): ?>
        <div class="alert alert-<?= $msg_type ?>"><?= $msg ?></div>
    <?php endif; ?>

    <form method="GET" class="filters">
        <label>🔍 Filtrer :</label>
        
        <select name="client" onchange="this.form.submit()">
            <option value="">-- Tous les clients --</option>
            <?php foreach($clients_list as $cl): ?>
                <option value="<?= $cl['id_client'] ?>" <?= (isset($_GET['client']) && $_GET['client'] == $cl['id_client']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cl['nom_societe']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="statut" onchange="this.form.submit()">
            <option value="">-- Tout état --</option>
            <option value="attente" <?= (isset($_GET['statut']) && $_GET['statut'] == 'attente') ? 'selected' : '' ?>>🔴 En Attente (Non payé)</option>
            <option value="paye" <?= (isset($_GET['statut']) && $_GET['statut'] == 'paye') ? 'selected' : '' ?>>✅ Encaissé (Payé)</option>
        </select>

        <a href="comptabilite.php" style="margin-left:auto; color:#666; text-decoration:underline;">Réinitialiser</a>
    </form>

    <form method="POST">
        
        <div style="margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
            <button type="submit" name="action_payer" class="btn btn-add">
                💵 Tout Encaisser (Sélection)
            </button>
            <span style="color:#777; font-size:0.9em;">Cochez les factures à encaisser</span>
        </div>

        <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th width="30"><input type="checkbox" onclick="toggleAll(this)"></th>
                    <th>Facture</th>
                    <th>Client</th>
                    <th>Total TTC</th>
                    <th>État Paiement</th>
                    <th>Relance</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($factures as $f): 
                    // CALCULS
                    $ts_debut = strtotime($f['date_debut']);
                    $ts_fin = strtotime($f['date_fin']);
                    $duree = max(1, ($ts_fin - $ts_debut) / 86400 + 1);
                    $total_ht = $f['valeur_jour'] * $duree;
                    if(isset($f['remise_percent'])) { $total_ht = $total_ht * (1 - ($f['remise_percent']/100)); }
                    $total_ttc = ($total_ht * 1.20) + $f['penalite'];

                    // CUMULS POUR LES CARTES DU BAS
                    $ca_total += $total_ttc;
                    
                    if($f['date_paiement']) {
                        $ca_encaisse += $total_ttc;
                        $status = "<span class='badge' style='background:#2ecc71; color:white; padding:2px 6px; border-radius:4px;'>PAYÉ le ".date('d/m', strtotime($f['date_paiement']))."</span>";
                        $is_paid = true;
                    } else {
                        $ca_attente += $total_ttc;
                        $status = "<span class='badge' style='background:#e74c3c; color:white; padding:2px 6px; border-radius:4px;'>EN ATTENTE</span>";
                        $is_paid = false;
                    }
                ?>
                <tr style="<?= $is_paid ? 'opacity:0.6; background:#f9f9f9;' : '' ?>">
                    <td><?php if(!$is_paid): ?><input type="checkbox" name="ids[]" value="<?= $f['id_commande'] ?>"><?php endif; ?></td>

                    <td>
                        <a href="facture.php?id=<?= $f['id_commande'] ?>" target="_blank" style="font-weight:bold; color:#2c3e50; text-decoration:none;">
                            F-2025-<?= str_pad($f['id_commande'], 4, '0', STR_PAD_LEFT) ?>
                        </a>
                        <br><small>Cmd #<?= $f['id_commande'] ?></small>
                    </td>
                    
                    <td><?= htmlspecialchars($f['nom_societe']) ?></td>
                    <td style="font-weight:bold;"><?= number_format($total_ttc, 2) ?> €</td>
                    <td><?= $status ?></td>
                    
                    <td>
                        <?php if(!$is_paid): ?>
                            <button type="submit" name="relancer_id" value="<?= $f['id_commande'] ?>" formaction="" onclick="this.form.relancer_email.value='<?= $f['client_email'] ?>';" class="btn" style="padding:2px 8px; font-size:0.8em; background:#f39c12;">🔔 Relancer</button>
                            <input type="hidden" name="relancer_email">
                            <?php if($f['date_relance']): ?><br><small style="color:#e67e22;">Dernière: <?= date('d/m', strtotime($f['date_relance'])) ?></small><?php endif; ?>
                        <?php else: ?>
                            <small style="color:#2ecc71;">OK</small>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if(!$is_paid): ?>
<button type="submit" name="payer_id" value="<?= $f['id_commande'] ?>" class="btn btn-add" style="padding:5px 10px; font-size:0.8em;">Encasser</button>                        <?php else: ?>
                            <button type="submit" name="annuler_id" value="<?= $f['id_commande'] ?>" class="btn" style="padding:5px 10px; font-size:0.8em; background:#95a5a6;">Annuler</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </form>

    <div style="display:flex; gap:20px; margin-top:30px;">
        <div style="flex:1; background:#ecf0f1; padding:20px; border-radius:8px; text-align:center;">
            <h3>Total Affiché</h3>
            <div style="font-size:2em; color:#2c3e50;"><?= number_format($ca_total, 2) ?> €</div>
        </div>
        <div style="flex:1; background:#d4edda; padding:20px; border-radius:8px; text-align:center;">
            <h3>Déjà Encaissé</h3>
            <div style="font-size:2em; color:#27ae60;"><?= number_format($ca_encaisse, 2) ?> €</div>
        </div>
        <div style="flex:1; background:#fadbd8; padding:20px; border-radius:8px; text-align:center;">
            <h3>Reste à Percevoir</h3>
            <div style="font-size:2em; color:#c0392b;"><?= number_format($ca_attente, 2) ?> €</div>
        </div>
    </div>
</div>

</body>
</html>