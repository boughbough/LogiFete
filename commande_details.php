<?php
// Fichier : commande_details.php (Avec Envoi de la date pour pré-remplissage)
session_start();
require 'db.php';
require 'navbar.php';
// Sécurité
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$id_commande = $_GET['id'];
$message = "";

// 1. INFOS COMMANDE
$stmt = $pdo->prepare("SELECT c.*, cl.nom_societe, cl.contact_nom, u.signature_data as signature_logifete 
                       FROM commande c 
                       JOIN client cl ON c.id_client = cl.id_client
                       LEFT JOIN utilisateur u ON c.id_commercial = u.id_user 
                       WHERE c.id_commande = ?");
$stmt->execute([$id_commande]);
$commande = $stmt->fetch();

if (!$commande) die("Commande introuvable.");

// --- VÉRIFICATION DU STATUT DES MISSIONS ---
$mission_livraison_statut = $pdo->prepare("SELECT statut FROM mission WHERE id_commande = ? AND type_mission = 'livraison' ORDER BY date_mission DESC LIMIT 1");
$mission_livraison_statut->execute([$id_commande]);
$livraison_statut = $mission_livraison_statut->fetchColumn();

$mission_recuperation_creee = $pdo->prepare("SELECT COUNT(*) FROM mission WHERE id_commande = ? AND type_mission = 'recuperation'");
$mission_recuperation_creee->execute([$id_commande]);
$recuperation_existe = $mission_recuperation_creee->fetchColumn() > 0;

$livraison_terminee = ($livraison_statut == 'terminee');
$livraison_creee = !empty($livraison_statut);
// -----------------------------------------------------------

// --- TRAITEMENT 1 : OPTIONS COMMERCIALES (Remise & Note) ---
if (isset($_POST['update_options'])) {
    $remise = intval($_POST['remise']);
    $note = $_POST['note'];
    
    if ($remise < 0) $remise = 0;
    if ($remise > 50) $remise = 50; 
    
    $pdo->prepare("UPDATE commande SET remise_percent = ?, note_interne = ? WHERE id_commande = ?")->execute([$remise, $note, $id_commande]);
    header("Location: commande_details.php?id=$id_commande");
    exit;
}

// --- TRAITEMENT 2 : AJOUT KIT ---
if (isset($_POST['ajout_kit'])) {
    $id_kit = $_POST['id_kit'];
    $compo = $pdo->prepare("SELECT id_reference, quantite FROM kit_contenu WHERE id_kit = ?");
    $compo->execute([$id_kit]);
    $items = $compo->fetchAll();
    
    $count_added = 0;
    foreach($items as $i) {
        $sql_find = "SELECT num_serie FROM equipement_physique 
                     WHERE id_reference = ? AND statut = 'disponible' 
                     AND num_serie NOT IN (SELECT num_serie FROM reservation_equipement WHERE id_commande = ?)
                     LIMIT " . intval($i['quantite']);
        $stmt_find = $pdo->prepare($sql_find);
        $stmt_find->execute([$i['id_reference'], $id_commande]);
        $dispos = $stmt_find->fetchAll(PDO::FETCH_COLUMN);
        
        foreach($dispos as $serie) {
            $pdo->prepare("INSERT IGNORE INTO reservation_equipement (id_commande, num_serie) VALUES (?, ?)")
                ->execute([$id_commande, $serie]);
            $count_added++;
        }
    }
    $message = "✅ Pack ajouté ! ($count_added équipements trouvés)";
}

// --- TRAITEMENT 3 : AJOUT UNITAIRE ---
if (isset($_POST['ajout_item'])) {
    $num_serie = $_POST['num_serie'];
    $check = $pdo->prepare("SELECT COUNT(*) FROM reservation_equipement WHERE id_commande = ? AND num_serie = ?");
    $check->execute([$id_commande, $num_serie]);
    
    if ($check->fetchColumn() > 0) {
        $message = "<span style='color:red'>⚠️ Erreur : Déjà dans la liste !</span>";
    } else {
        $pdo->prepare("INSERT INTO reservation_equipement (id_commande, num_serie) VALUES (?, ?)")->execute([$id_commande, $num_serie]);
        $message = "✅ Équipement ajouté.";
    }
}

// --- TRAITEMENT 4 : VALIDER (CRITIQUE : ENVOI DU MESSAGE FLASH) ---
if (isset($_POST['valider_commande'])) {
    // Sécurité Anti-Doublon + Log
    if ($commande['etat'] == 'devis') {
        $pdo->prepare("UPDATE commande SET etat = 'validee' WHERE id_commande = ?")->execute([$id_commande]);
        if(function_exists('ajouterLog')) ajouterLog($pdo, "VALIDATION COMMANDE", "Validation de la commande #" . $id_commande);
        
        // --- C'EST CETTE LIGNE QUI ENVOIE LE MESSAGE AU DASHBOARD ---
        $_SESSION['flash_message'] = "✅ Devis validé ! La commande #$id_commande est maintenant active.";
        $_SESSION['flash_cmd_id'] = $id_commande; // Cette ligne est CRITIQUE
    }
    header("Location: dashboard.php"); 
    exit;
}

// --- CALCULS & DONNÉES ---

$kits_list = $pdo->query("SELECT * FROM kit")->fetchAll();
$date_debut = $commande['date_debut'];
$date_fin = $commande['date_fin'];

// Matériel disponible
$sql_dispo = "SELECT e.num_serie, r.libelle, r.prix_jour FROM equipement_physique e JOIN reference_materiel r ON e.id_reference = r.id_reference WHERE e.statut = 'disponible' AND e.num_serie NOT IN (SELECT re.num_serie FROM reservation_equipement re JOIN commande c ON re.id_commande = c.id_commande WHERE c.etat != 'annulee' AND c.id_commande != :current_id AND ((c.date_debut <= :date_fin) AND (c.date_fin >= :date_debut))) ORDER BY r.libelle";
$stmt = $pdo->prepare($sql_dispo);
$stmt->execute(['date_fin' => $date_fin, 'date_debut' => $date_debut, 'current_id' => $id_commande]);
$equipements_dispos = $stmt->fetchAll();

// Panier actuel
$sql_panier = "SELECT re.id_reservation, e.num_serie, r.libelle, r.prix_jour FROM reservation_equipement re JOIN equipement_physique e ON re.num_serie = e.num_serie JOIN reference_materiel r ON e.id_reference = r.id_reference WHERE re.id_commande = ?";
$stmt = $pdo->prepare($sql_panier);
$stmt->execute([$id_commande]);
$panier = $stmt->fetchAll();
$already_in_cart = array_column($panier, 'num_serie');

// Calculs
$ts_debut = strtotime($commande['date_debut']);
$ts_fin = strtotime($commande['date_fin']);
$duree_contrat = max(1, ($ts_fin - $ts_debut) / 86400 + 1);

$total_brut = 0; $total_journalier = 0; 
foreach($panier as $p) { $total_brut += $p['prix_jour'] * $duree_contrat; $total_journalier += $p['prix_jour']; }

$remise_percent = $commande['remise_percent'];
$remise_auto_msg = "";

if ($commande['etat'] == 'devis' && $remise_percent == 0) {
    if ($duree_contrat >= 7) { $remise_percent = 10; $remise_auto_msg = "(Suggéré : -10%)"; }
    elseif ($duree_contrat >= 3) { $remise_percent = 5; $remise_auto_msg = "(Suggéré : -5%)"; }
}

$remise_montant = $total_brut * ($remise_percent / 100);
$total_net = $total_brut - $remise_montant;

$penalite = 0; $is_retard = false; $retard_jours = 0;
if ($commande['etat'] == 'terminee' && $commande['penalite'] > 0) {
    $penalite = $commande['penalite']; $is_retard = true;
} elseif ($commande['etat'] == 'validee') {
    $retard_jours = floor((time() - $ts_fin) / 86400);
    if ($retard_jours > 0) { $is_retard = true; $penalite = $total_journalier * $retard_jours * 1.5; }
}
$total_final_a_payer = $total_net + $penalite;
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>Détails Commande #<?= $id_commande ?></title>
    <style>
        .tooltip { position: relative; display: inline-block; cursor: help; color: white; background:#3498db; border-radius:50%; width:20px; height:20px; text-align:center; line-height:20px; font-size:0.8em; margin-left:5px; font-weight:bold; }
        .tooltip .tooltiptext { visibility: hidden; width: 220px; background-color: #2c3e50; color: #fff; text-align: left; border-radius: 6px; padding: 10px; position: absolute; z-index: 1; bottom: 135%; left: 50%; margin-left: -110px; opacity: 0; transition: opacity 0.3s; font-size: 0.85em; line-height: 1.4; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        .tooltip .tooltiptext::after { content: ""; position: absolute; top: 100%; left: 50%; margin-left: -5px; border-width: 5px; border-style: solid; border-color: #2c3e50 transparent transparent transparent; }
        .tooltip:hover .tooltiptext { visibility: visible; opacity: 1; }
        
        /* Styles pour les boutons Mission */
        .btn-mission-disabled {
            background: #95a5a6 !important; /* Gris */
            cursor: not-allowed !important;
            opacity: 0.6;
        }
    </style>
</head>
<body>
<?= renderNavbar($_SESSION['role'], $current_page) ?>
<div class="container">
    <a href="dashboard.php">⬅ Retour Tableau de bord</a>
    
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>Commande N°<?= $id_commande ?> (<?= ucfirst($commande['etat']) ?>)</h1>
        
        <div style="text-align:right;">
            <?php if($commande['etat'] == 'devis'): ?>
                <a href="imprimer.php?id=<?= $id_commande ?>" target="_blank" class="btn" style="background:#3498db;">🖨️ Imprimer Devis</a>
            <?php elseif($commande['etat'] == 'validee' || $commande['etat'] == 'terminee'): ?>
                <a href="facture.php?id=<?= $id_commande ?>" target="_blank" class="btn" style="background:#2c3e50;">📄 Facture</a>
                <a href="bon_livraison.php?id=<?= $id_commande ?>" target="_blank" class="btn" style="background:#7f8c8d;">🚚 Bon</a>
                
                <?php if (empty($commande['signature_client'])): ?>
                    <a href="sign.php?id=<?= $id_commande ?>" class="btn" style="background:#e67e22;">✍️ Faire Signer</a>
                <?php else: ?>
                    <span class="btn" style="background:#27ae60; cursor:default;">✅ Signé</span>
                <?php endif; ?>
                
                <?php if ($commande['etat'] == 'validee'): ?>
                    <hr style="margin-top: 15px; margin-bottom: 5px; border: 0; border-top: 1px dashed #ccc;">
                    
                    <?php 
                        // LIVRAISON : Clicable si pas encore créée
                        $livraison_active = !$livraison_creee;
                        $livraison_class = $livraison_active ? "background:#2980b9;" : "btn-mission-disabled";
                        
                        // RÉCUPÉRATION : Clicable si livraison créée ET terminée ET si mission de récupération n'existe pas encore
                        $recuperation_active = $livraison_terminee && !$recuperation_existe;
                        $recuperation_class = $recuperation_active ? "background:#9b59b6;" : "btn-mission-disabled";
                    ?>
                    
                    <a href="missions.php?action=prefill&type=livraison&cmd_id=<?= $id_commande ?>&date=<?= $commande['date_debut'] ?>" 
                       class="btn <?= $livraison_class ?>" 
                       style="margin-top:5px; font-size:0.9em; <?= $livraison_active ? '' : 'pointer-events: none;' ?>">
                        + Mission Livraison
                    </a>
                    
                    <a href="missions.php?action=prefill&type=recuperation&cmd_id=<?= $id_commande ?>&date=<?= $commande['date_fin'] ?>" 
                       class="btn <?= $recuperation_class ?>" 
                       style="margin-top:5px; font-size:0.9em; <?= $recuperation_active ? '' : 'pointer-events: none;' ?>">
                        + Mission Récupération
                    </a>
                    
                    <?php if ($livraison_creee && !$livraison_terminee): ?>
                        <div style="font-size:0.8em; color:#e67e22; margin-top:5px;">(Livraison en cours - Récup. bloquée)</div>
                    <?php endif; ?>

                <?php endif; ?>
                <?php endif; ?>
        </div>
    </div>
    
    <div class="header-info">
        <p><strong>Client :</strong> <?= htmlspecialchars($commande['nom_societe']) ?></p>
        <p><strong>Période :</strong> Du <?= $commande['date_debut'] ?> au <?= $commande['date_fin'] ?> (<?= $duree_contrat ?> jours)</p>
        
        <?php if(!empty($commande['note_interne'])): ?>
            <div style="background:#fffbe6; border:1px solid #f1c40f; padding:10px; margin-top:10px; border-radius:4px; color:#7f8c8d;">
                <strong>📝 Note Interne :</strong> <?= nl2br(htmlspecialchars($commande['note_interne'])) ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if($message) echo "<p>$message</p>"; ?>

    <?php if ($is_retard): ?>
    <div style="background:#fff5f5; border:2px solid #c0392b; color:#c0392b; padding:15px; border-radius:8px; margin:20px 0;">
        <h3 style="margin-top:0; color:#c0392b;">⚠️ ATTENTION : COMMANDE EN RETARD</h3>
        <div style="display:flex; justify-content:space-between; font-size:1.1em;">
            <span>Montant Contrat (Net) :</span><span><?= number_format($total_net, 2) ?> €</span>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:1.1em; font-weight:bold;">
            <span>+ Pénalités (<?= $retard_jours ?> jours) :</span><span><?= number_format($penalite, 2) ?> €</span>
        </div>
        <hr style="border-top:2px solid #c0392b;">
        <div style="display:flex; justify-content:space-between; align-items:center; font-size:1.4em; font-weight:bold;">
            <span>TOTAL À PAYER :</span><span><?= number_format($total_final_a_payer, 2) ?> €</span>
        </div>
    </div>
    <?php endif; ?>

    <?php if($commande['etat'] == 'devis'): ?>
        <div style="display:flex; gap:20px; margin-bottom:20px;">
            <div style="flex:1; background:#f0f9ff; padding:15px; border:1px solid #bee3f8;">
                <h3>➕ Ajouter Matériel</h3>
                <form method="POST">
                    <select name="num_serie" required style="width:100%; margin-bottom:10px;">
                        <option value="">-- Choisir --</option>
                        <?php foreach($equipements_dispos as $eq): $dis=in_array($eq['num_serie'], $already_in_cart); ?>
                            <option value="<?= $eq['num_serie'] ?>" <?=$dis?'disabled':''?>><?= $eq['libelle'] ?> (<?= $eq['num_serie'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="ajout_item" class="btn-add">Ajouter</button>
                </form>
            </div>
            
            <?php if(count($kits_list) > 0): ?>
            <div style="flex:1; background:#e3f2fd; padding:15px; border:1px solid #90cdf4;">
                <h3>📦 Ajouter un Pack</h3>
                <form method="POST" style="display:flex; gap:10px;">
                    <select name="id_kit" required style="margin:0; flex:1;"><option value="">-- Kit --</option><?php foreach($kits_list as $k) echo "<option value='{$k['id_kit']}'>{$k['libelle']}</option>"; ?></select>
                    <button type="submit" name="ajout_kit" class="btn" style="background:#2980b9;">Importer</button>
                </form>
            </div>
            <?php endif; ?>

            <div style="flex:1; background:#fff3cd; padding:15px; border:1px solid #ffeeba;">
                <h3>🤝 Options</h3>
                <form method="POST">
                    <div style="display:flex; gap:10px; align-items:center; margin-bottom:10px;">
                        <label>Remise %</label>
                        <div class="tooltip">?
                            <span class="tooltiptext"><strong>Règles de remise auto :</strong><br>• Durée > 3 jours : -5%<br>• Durée > 7 jours : -10%</span>
                        </div>
                        <input type="number" name="remise" value="<?= $remise_percent ?>" min="0" max="50" style="width:60px; margin:0;">
                        <?php if($remise_auto_msg) echo "<small style='color:#27ae60; font-weight:bold;'>$remise_auto_msg</small>"; ?>
                    </div>
                    <textarea name="note" placeholder="Note interne" rows="2" style="margin-bottom:10px; width:100%;"><?= htmlspecialchars($commande['note_interne']) ?></textarea>
                    <button type="submit" name="update_options" class="btn" style="background:#f39c12; font-size:0.9em;">Mettre à jour</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <h3>🛒 Panier</h3>
    <table>
        <thead><tr><th>Matériel</th><th>Série</th><th>Prix Unitaire</th><th>Total Ligne</th></tr></thead>
        <tbody>
            <?php foreach($panier as $ligne): ?>
                <tr>
                    <td><?= $ligne['libelle'] ?></td>
                    <td><?= $ligne['num_serie'] ?></td>
                    <td><?= $ligne['prix_jour'] ?> €</td>
                    <td><?= $ligne['prix_jour'] * $duree_contrat ?> €</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="text-align:right;">
        <p>Total Brut : <?= number_format($total_brut, 2) ?> €</p>
        <?php if($remise_percent > 0): ?>
            <p style="color:#27ae60;">Remise (<?= $remise_percent ?>%) : -<?= number_format($remise_montant, 2) ?> €</p>
        <?php endif; ?>
        <h3>NET À PAYER (Hors pénalités) : <?= number_format($total_net, 2) ?> €</h3>
    </div>

    <?php if($commande['etat'] == 'devis' && count($panier) > 0): ?>
        <form method="POST" style="text-align:right; margin-top:20px;">
            <button type="submit" name="valider_commande" class="btn-validate">✅ VALIDER LA COMMANDE</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>