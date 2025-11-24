<?php
// Fichier : imprimer.php
session_start();
require 'db.php';

if (!isset($_GET['id'])) die("ID manquant");

$id = $_GET['id'];

// Infos Commande + Client + Commercial
$sql = "SELECT c.*, cl.nom_societe, cl.contact_nom, cl.email, cl.telephone, u.nom as nom_commercial
        FROM commande c
        JOIN client cl ON c.id_client = cl.id_client
        JOIN utilisateur u ON c.id_commercial = u.id_user
        WHERE c.id_commande = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$inf = $stmt->fetch();

// Lignes de commande
$sql_lignes = "SELECT e.num_serie, r.libelle, r.prix_jour 
               FROM reservation_equipement re
               JOIN equipement_physique e ON re.num_serie = e.num_serie
               JOIN reference_materiel r ON e.id_reference = r.id_reference
               WHERE re.id_commande = ?";
$stmt = $pdo->prepare($sql_lignes);
$stmt->execute([$id]);
$lignes = $stmt->fetchAll();

// Calculs
$ts_debut = strtotime($inf['date_debut']);
$ts_fin = strtotime($inf['date_fin']);
$duree = ($ts_fin < $ts_debut) ? 1 : ($ts_fin - $ts_debut) / 86400 + 1;
$total_ht = 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Devis #<?= $id ?></title>
    <style>
        body { font-family: 'Helvetica', sans-serif; color: #333; line-height: 1.4; max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 50px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .logo { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .facture-info { text-align: right; }
        
        .client-box { background: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 30px; display: flex; justify-content: space-between; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th { background: #eee; text-align: left; padding: 10px; border-bottom: 2px solid #aaa; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        
        .totals { text-align: right; margin-top: 20px; }
        .total-final { font-size: 1.5em; font-weight: bold; color: #2c3e50; }
        
        .footer { margin-top: 50px; font-size: 0.8em; text-align: center; color: #777; border-top: 1px solid #eee; padding-top: 10px; }

        /* Pour l'impression : on cache le bouton */
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
        
        .btn-print { background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-bottom: 20px; }
    </style>
</head>
<body>

    <div class="no-print" style="text-align:right;">
        <a href="javascript:window.print()" class="btn-print">🖨️ Imprimer / Sauvegarder en PDF</a>
        <a href="commande_details.php?id=<?= $id ?>" class="btn-print" style="background:#7f8c8d;">Retour</a>
    </div>

    <div class="header">
        <div class="logo">LogiFête S.A.</div>
        <div class="facture-info">
            <h1><?= strtoupper($inf['etat']) ?> N°<?= $inf['id_commande'] ?></h1>
            <p>Date : <?= date('d/m/Y', strtotime($inf['date_creation'])) ?></p>
            <p>Commercial : <?= htmlspecialchars($inf['nom_commercial']) ?></p>
        </div>
    </div>

    <div class="client-box">
        <div>
            <strong>ÉMETTEUR :</strong><br>
            LogiFête S.A.<br>
            12 Rue de l'Innovation<br>
            75000 PARIS<br>
            contact@logifete.com
        </div>
        <div style="text-align:right;">
            <strong>CLIENT :</strong><br>
            <?= htmlspecialchars($inf['nom_societe']) ?><br>
            Attn: <?= htmlspecialchars($inf['contact_nom']) ?><br>
            <?= htmlspecialchars($inf['email']) ?><br>
            <?= htmlspecialchars($inf['telephone']) ?>
        </div>
    </div>

    <p><strong>Période de location :</strong> Du <?= date('d/m/Y', strtotime($inf['date_debut'])) ?> au <?= date('d/m/Y', strtotime($inf['date_fin'])) ?> (<?= $duree ?> jours)</p>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Réf (Série)</th>
                <th>Prix Unitaire / Jour</th>
                <th style="text-align:right;">Total Ligne</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($lignes as $l): 
                $prix_ligne = $l['prix_jour'] * $duree;
                $total_ht += $prix_ligne;
            ?>
            <tr>
                <td><?= htmlspecialchars($l['libelle']) ?></td>
                <td><?= htmlspecialchars($l['num_serie']) ?></td>
                <td><?= number_format($l['prix_jour'], 2) ?> €</td>
                <td style="text-align:right;"><?= number_format($prix_ligne, 2) ?> €</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <p>Total HT : <?= number_format($total_ht, 2) ?> €</p>
        <p>TVA (20%) : <?= number_format($total_ht * 0.20, 2) ?> €</p>
        <p class="total-final">NET À PAYER : <?= number_format($total_ht * 1.20, 2) ?> €</p>
    </div>

    <div class="footer">
        LogiFête S.A. au capital de 50.000€ - SIRET 123 456 789 00012 - Code NAF 7739Z<br>
        Document généré automatiquement le <?= date('d/m/Y') ?>
    </div>

</body>
</html>