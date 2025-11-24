<?php
// Fichier : facture.php (Version Finale : Remises & Pénalités)
session_start();
require 'db.php';

if (!isset($_GET['id'])) die("ID manquant");
$id = $_GET['id'];

// Récupération Infos + Remise
$sql = "SELECT c.*, cl.*, u.nom as commercial FROM commande c 
        JOIN client cl ON c.id_client = cl.id_client 
        JOIN utilisateur u ON c.id_commercial = u.id_user WHERE c.id_commande = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$inf = $stmt->fetch();

if ($inf['etat'] == 'devis') die("Commande non validée.");

// Dates
$ts_debut = strtotime($inf['date_debut']);
$ts_fin = strtotime($inf['date_fin']);
$duree = ($ts_fin < $ts_debut) ? 1 : ($ts_fin - $ts_debut) / 86400 + 1;

$lignes = $pdo->query("SELECT e.num_serie, r.libelle, r.prix_jour FROM reservation_equipement re JOIN equipement_physique e ON re.num_serie = e.num_serie JOIN reference_materiel r ON e.id_reference = r.id_reference WHERE re.id_commande = $id")->fetchAll();

$total_brut = 0;
$total_journalier = 0;
foreach($lignes as $l) {
    $total_brut += $l['prix_jour'] * $duree;
    $total_journalier += $l['prix_jour'];
}

// Calcul Remise
$montant_remise = $total_brut * ($inf['remise_percent'] / 100);
$total_net_ht = $total_brut - $montant_remise;

// Calcul Pénalités
$penalite_a_payer = $inf['penalite'];
if ($inf['etat'] == 'validee' && $penalite_a_payer == 0) {
    $retard_jours = (time() - $ts_fin) / 86400;
    if ($retard_jours > 0) {
        $penalite_a_payer = $total_journalier * floor($retard_jours) * 1.5;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>Facture F-2025-<?= $id ?></title>
    <style>
        body { font-family: 'Arial', sans-serif; padding: 40px; max-width: 800px; margin: auto; color: #333; }
        .top-header { border-bottom: 2px solid #2c3e50; padding-bottom: 20px; margin-bottom: 40px; display: flex; justify-content: space-between; }
        .client-addr { background: #f4f4f4; padding: 20px; border-radius: 5px; width: 40%; margin-left: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 30px; }
        th { background: #2c3e50; color: white; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        .totals { margin-top: 30px; text-align: right; }
        .row-penalite { background-color: #fff5f5; color: #c0392b; font-weight: bold; }
    </style>
</head>
<body>

    <div style="text-align:right;" class="no-print">
        <a href="javascript:window.print()" style="background:#2c3e50; color:white; padding:10px; text-decoration:none;">🖨️ Imprimer</a>
    </div>

    <div class="top-header">
        <div>
            <h1 style="color:#2c3e50; margin:0;">LOGIFÊTE S.A.</h1>
            <p>12 Rue de l'Evénement<br>75011 PARIS</p>
        </div>
        <div style="text-align:right;">
            <h1 style="margin:0;">FACTURE</h1>
            <p>N° F-2025-<?= str_pad($id, 4, '0', STR_PAD_LEFT) ?></p>
            <p>Date : <?= date('d/m/Y') ?></p>
        </div>
    </div>

    <div class="client-addr">
        <strong>FACTURÉ À :</strong><br>
        <?= htmlspecialchars($inf['nom_societe']) ?><br>
        <?= htmlspecialchars($inf['contact_nom']) ?>
    </div>

    <p><strong>Objet :</strong> Location du <?= date('d/m/Y', $ts_debut) ?> au <?= date('d/m/Y', $ts_fin) ?></p>

    <table>
        <thead><tr><th>Désignation</th><th>Qté</th><th>PU HT/J</th><th>Total HT</th></tr></thead>
        <tbody>
            <?php foreach($lignes as $l): 
                $ligne_price = $l['prix_jour'] * $duree;
            ?>
            <tr>
                <td><?= $l['libelle'] ?> (<?= $l['num_serie'] ?>)</td>
                <td>1</td>
                <td><?= number_format($l['prix_jour'], 2) ?> €</td>
                <td><?= number_format($ligne_price, 2) ?> €</td>
            </tr>
            <?php endforeach; ?>

            <?php if($penalite_a_payer > 0): ?>
            <tr class="row-penalite">
                <td colspan="3">PÉNALITÉS DE RETARD</td>
                <td><?= number_format($penalite_a_payer, 2) ?> €</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="totals">
        <p>Sous-total Location : <?= number_format($total_brut, 2) ?> €</p>
        
        <?php if($inf['remise_percent'] > 0): ?>
            <p style="color:#27ae60;">Remise Commerciale (<?= $inf['remise_percent'] ?>%) : -<?= number_format($montant_remise, 2) ?> €</p>
        <?php endif; ?>
        
        <?php 
            $base_ht = $total_net_ht + $penalite_a_payer;
            $tva = $base_ht * 0.2;
            $ttc = $base_ht + $tva;
        ?>
        
        <p><strong>Total HT : <?= number_format($base_ht, 2) ?> €</strong></p>
        <p>TVA (20%) : <?= number_format($tva, 2) ?> €</p>
        <h2 style="color:#2c3e50;">NET À PAYER TTC : <?= number_format($ttc, 2) ?> €</h2>
    </div>

</body>
</html>