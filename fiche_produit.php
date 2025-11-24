<?php
// Fichier : fiche_produit.php (Version Finale Complète)
session_start();
require 'db.php';
require 'navbar.php';
if (!isset($_GET['ref'])) die("Référence manquante");
$ref = $_GET['ref'];

// 1. Infos Produit + Prix Achat + Coût Réparations + FOURNISSEUR
$sql = "SELECT e.*, r.libelle, r.description, r.prix_jour, r.image_url, r.categorie, r.prix_achat,
               f.nom_societe as fournisseur, f.telephone as tel_sav
        FROM equipement_physique e
        JOIN reference_materiel r ON e.id_reference = r.id_reference
        LEFT JOIN fournisseur f ON e.id_fournisseur = f.id_fournisseur
        WHERE e.num_serie = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$ref]);
$item = $stmt->fetch();

if (!$item) die("Produit introuvable");

// 2. Historique
$sql_hist = "SELECT c.id_commande, c.date_debut, c.date_fin, cl.nom_societe, c.etat
             FROM reservation_equipement re
             JOIN commande c ON re.id_commande = c.id_commande
             JOIN client cl ON c.id_client = cl.id_client
             WHERE re.num_serie = ? AND c.etat IN ('validee', 'terminee')
             ORDER BY c.date_debut DESC";
$stmt_hist = $pdo->prepare($sql_hist);
$stmt_hist->execute([$ref]);
$historique = $stmt_hist->fetchAll();

// --- CALCUL RENTABILITÉ NETTE ---
$ca_genere = 0;
$nb_jours_total = 0;

foreach($historique as $h) {
    $ts_debut = strtotime($h['date_debut']);
    $ts_fin = strtotime($h['date_fin']);
    $duree = max(1, ($ts_fin - $ts_debut) / 86400 + 1);
    $nb_jours_total += $duree;
    $ca_genere += $item['prix_jour'] * $duree;
}

$cout_achat = $item['prix_achat'] > 0 ? $item['prix_achat'] : 1;
$cout_reparations = $item['cout_reparations']; 

// Formule : (Gains - Réparations) / Achat
$benefice_net = $ca_genere - $cout_reparations;
$pourcentage_rentabilite = ($benefice_net / $cout_achat) * 100;
$est_rentable = $benefice_net >= $cout_achat;
$bar_width = max(0, min($pourcentage_rentabilite, 100));
$current_page = basename($_SERVER['PHP_SELF']);?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>Fiche : <?= htmlspecialchars($item['libelle']) ?></title>
    <style>
        .fiche-header { display: flex; gap: 30px; background: white; padding: 30px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .fiche-img { width: 200px; height: 200px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; }
        .fiche-info { flex: 1; }
        .qr-big { border: 1px solid #eee; padding: 10px; background: white; }
        
        .roi-container { margin-top: 20px; background: #f1f1f1; border-radius: 10px; overflow: hidden; height: 25px; width: 100%; border: 1px solid #ddd; position: relative; }
        .roi-bar { height: 100%; background: linear-gradient(90deg, #f39c12, #2ecc71); width: 0%; transition: width 1s ease-in-out; }
        .roi-text { position: absolute; width: 100%; text-align: center; line-height: 25px; font-weight: bold; font-size: 0.8em; color: #333; top:0; }
    </style>
</head>
<body>

<?= renderNavbar($_SESSION['role'], $current_page) ?>

<div class="container">
    
    <div class="fiche-header">
        <img src="<?= $item['image_url'] ?>" class="fiche-img" alt="Produit">
        
        <div class="fiche-info">
            <div style="display:flex; justify-content:space-between;">
                <h1 style="margin-top:0;"><?= htmlspecialchars($item['libelle']) ?></h1>
                <span class="badge status-<?= $item['statut'] ?>" style="font-size:1.2em; padding:10px 20px;">
                    <?= strtoupper($item['statut']) ?>
                </span>
            </div>
            
            <p><strong>N° Série :</strong> <span style="font-family:monospace; font-size:1.2em;"><?= $item['num_serie'] ?></span></p>
            <p><strong>Catégorie :</strong> <?= htmlspecialchars($item['categorie']) ?></p>
            <div style="display:flex; gap:20px;">
                <p><strong>Tarif Loc :</strong> <?= number_format($item['prix_jour'], 2) ?> €/j</p>
                <p style="color:#7f8c8d;">(Achat : <?= number_format($item['prix_achat'], 2) ?> €)</p>
            </div>

            <div style="margin-top:15px; padding:10px; background:#e8f6f3; border:1px solid #a2d9ce; border-radius:5px;">
                <strong>🛡️ Garantie Fournisseur :</strong>
                <?php if($item['fournisseur']): ?>
                    <br>Fournisseur : <strong><?= htmlspecialchars($item['fournisseur']) ?></strong> (SAV: <?= $item['tel_sav'] ?>)
                    <br>Fin de garantie : 
                    <?php 
                        if($item['fin_garantie'] > date('Y-m-d')) 
                            echo "<span style='color:#27ae60; font-weight:bold;'>✅ Valide jusqu'au ".date('d/m/Y', strtotime($item['fin_garantie']))."</span>";
                        else 
                            echo "<span style='color:#c0392b; font-weight:bold;'>❌ Expirée le ".date('d/m/Y', strtotime($item['fin_garantie']))."</span>";
                    ?>
                <?php else: ?>
                    <span style="color:#777;">Non renseignée (Achat ancien ou inconnu)</span>
                <?php endif; ?>
            </div>
            
            <div style="background:#f9f9f9; padding:15px; border-radius:5px; border:1px solid #eee; margin-top:10px;">
                <strong>💰 Analyse de Rentabilité Nette :</strong>
                <ul style="margin:5px 0 10px 20px; font-size:0.9em; color:#555;">
                    <li>Chiffre d'affaires brut : <strong>+<?= number_format($ca_genere, 2) ?> €</strong></li>
                    <li>Coût des réparations : <strong style="color:#c0392b;">-<?= number_format($cout_reparations, 2) ?> €</strong></li>
                </ul>
                
                <div class="roi-container">
                    <div class="roi-bar" style="width: <?= $bar_width ?>%;"></div>
                    <div class="roi-text">Amorti à <?= number_format($pourcentage_rentabilite, 1) ?>%</div>
                </div>
                
                <p style="margin:5px 0 0 0; font-size:0.9em;">
                    <?php if($est_rentable): ?>
                        <span style="color:#27ae60;">✅ <strong>BÉNÉFICIAIRE</strong> (+<?= number_format($benefice_net - $cout_achat, 2) ?> €)</span>
                    <?php else: ?>
                        <span style="color:#e67e22;">⏳ <strong>EN COURS</strong> (Reste à couvrir : <?= number_format($cout_achat - $benefice_net, 2) ?> €)</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div style="text-align:center;">
            <img src="https://quickchart.io/qr?text=<?= urlencode($item['num_serie']) ?>&size=150" class="qr-big">
            <br><small>Scan Rapide</small>
        </div>
    </div>

    <div class="card">
        <h3>📜 Historique des mouvements</h3>
        <?php if(count($historique) == 0): ?>
            <p style="color:gray;">Cet équipement est neuf, aucun historique.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Commande</th><th>Client</th><th>Dates</th><th>État</th></tr></thead>
                <tbody>
                    <?php foreach($historique as $h): ?>
                    <tr>
                        <td><a href="commande_details.php?id=<?= $h['id_commande'] ?>">#<?= $h['id_commande'] ?></a></td>
                        <td><?= htmlspecialchars($h['nom_societe']) ?></td>
                        <td>Du <?= date('d/m/y', strtotime($h['date_debut'])) ?> au <?= date('d/m/y', strtotime($h['date_fin'])) ?></td>
                        <td><?= strtoupper($h['etat']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if ($_SESSION['role'] != 'commercial'): ?>
        <br>
        <div style="text-align:right;">
            <?php if($item['statut'] != 'panne'): ?>
                <a href="signaler_panne.php" class="btn-danger">🚨 Signaler une Panne</a>
            <?php else: ?>
                <a href="maintenance.php" class="btn" style="background:#e67e22;">🛠️ Aller à l'atelier</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

</body>
</html>