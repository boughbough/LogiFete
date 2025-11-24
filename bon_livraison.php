<?php
// Fichier : bon_livraison.php (Avec Horodatage de la validation)
session_start();
require 'db.php';

if (!isset($_GET['id'])) die("ID manquant");
$id = $_GET['id'];

// Récupération Lignes existantes pour comparer l'état des checks (FIX DE LA PDOEXCEPTION)
$stmt_lignes_old = $pdo->prepare("SELECT num_serie, check_depart, check_retour FROM reservation_equipement WHERE id_commande = ?");
$stmt_lignes_old->execute([$id]);
$temp_lignes_old = $stmt_lignes_old->fetchAll(PDO::FETCH_ASSOC); // FIXED: Utilisation de FETCH_ASSOC

// Reformate le tableau pour que num_serie soit la clé (structure requise par la logique POST)
$lignes_old = [];
foreach ($temp_lignes_old as $row) {
    $lignes_old[$row['num_serie']] = $row;
}


// --- TRAITEMENT : SAUVEGARDE DE L'ÉTAT DES LIEUX ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_bl'])) {
    $now = date('Y-m-d H:i:s');
    
    if (isset($_POST['rows'])) {
        foreach ($_POST['rows'] as $num_serie => $data) {
            $check_d = isset($data['check_depart']) ? 1 : 0;
            $check_r = isset($data['check_retour']) ? 1 : 0;
            $obs = $data['observations'];
            $etat = $data['etat_materiel'];
            
            // Logique d'horodatage
            $date_depart_update = "";
            $date_retour_update = "";

            // Le départ est coché MAINTENANT et n'était pas coché avant : on met à jour le DATETIME
            if (isset($lignes_old[$num_serie]) && $check_d == 1 && $lignes_old[$num_serie]['check_depart'] == 0) {
                $date_depart_update = ", date_depart_check = '$now'";
            }
            // Le retour est coché MAINTENANT et n'était pas coché avant : on met à jour le DATETIME
            if (isset($lignes_old[$num_serie]) && $check_r == 1 && $lignes_old[$num_serie]['check_retour'] == 0) {
                $date_retour_update = ", date_retour_check = '$now'";
            }

            // LOGIQUE SUPPLÉMENTAIRE : MISE À JOUR IMMÉDIATE DU STATUT PHYSIQUE
            $new_statut_physique = 'disponible';
            if ($etat == 'Endommagé') {
                $new_statut_physique = 'panne';
                if(function_exists('ajouterLog')) ajouterLog($pdo, "DECLA_DEFAUT", "Défaut déclaré sur $num_serie (état: $etat).");
            }

            $sql_update = "UPDATE reservation_equipement 
                           SET check_depart = ?, check_retour = ?, observations = ?, etat_materiel = ? $date_depart_update $date_retour_update
                           WHERE id_commande = ? AND num_serie = ?";
            $pdo->prepare($sql_update)->execute([$check_d, $check_r, $obs, $etat, $id, $num_serie]);
            
            // Mise à jour du statut dans la table équipement_physique
            $pdo->prepare("UPDATE equipement_physique SET statut = ? WHERE num_serie = ?")
                ->execute([$new_statut_physique, $num_serie]);
        }
    }
    header("Location: bon_livraison.php?id=$id&saved=1");
    exit;
}

// Récupération Infos Commande (AJOUT DE LA JOINTURE UTILISATEUR)
$sql = "SELECT c.*, cl.*, u.signature_data as visa_logifete 
        FROM commande c 
        JOIN client cl ON c.id_client = cl.id_client
        LEFT JOIN utilisateur u ON c.id_commercial = u.id_user 
        WHERE c.id_commande = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$inf = $stmt->fetch();

// Récupération Lignes avec les nouveaux champs d'état
$lignes = $pdo->query("SELECT re.*, r.libelle 
                       FROM reservation_equipement re 
                       JOIN equipement_physique e ON re.num_serie = e.num_serie 
                       JOIN reference_materiel r ON e.id_reference = r.id_reference 
                       WHERE re.id_commande = $id")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>Bon de Livraison BL-<?= $id ?></title>
    <style>
        /* Styles Généraux */
        body { font-family: 'Arial', sans-serif; padding: 40px; max-width: 900px; margin: auto; color: #333; background: #fff; }
        .header { border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; display:flex; justify-content:space-between;}
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.9em; }
        th, td { border: 1px solid #999; padding: 8px; text-align: left; vertical-align: middle; }
        th { background: #eee; text-align: center; }
        .check-col { width: 50px; text-align: center; }
        
        /* Styles spécifiques pour l'édition */
        input[type="text"], select { width: 90%; padding: 5px; border: 1px solid #ccc; border-radius: 3px; }
        input[type="checkbox"] { transform: scale(1.5); cursor: pointer; }
        .actions-bar { background: #f0f0f0; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #ddd; }
        
        /* CSS IMPRESSION (Ce qui change sur le papier) */
        @media print {
            body { padding: 0; margin: 0; }
            .no-print, .actions-bar { display: none !important; }
            
            /* Remplacement des inputs par leur valeur ou un carré pour le checkbox */
            input[type="text"], select { 
                border: none; 
                background: transparent; 
                width: 100%; 
                font-family: inherit; 
                font-size: inherit; 
            }
            
            /* Affichage du checkmark pour l'impression */
            .print-check::before {
                content: "■"; /* Caractère carré pour la case */
                font-size: 1.2em;
                margin-right: 5px;
                color: gray;
            }
            .print-checked::before {
                content: "✅"; /* Checkmark pour l'état coché */
                font-size: 1.2em;
                margin-right: 5px;
                color: green;
            }
        }
    </style>
</head>
<body>
    <div class="actions-bar no-print" style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-card); padding:15px; border:1px solid var(--border-color); margin-bottom:20px;">
        <div>
            <a href="commande_details.php?id=<?= $id ?>" class="btn-back" style="margin:0;">
                &larr; Retour à la commande
            </a>
        </div>
        
        <div style="font-weight:700; color:var(--primary); text-transform:uppercase; letter-spacing:1px;">
            📝 Mode Édition : Départ & Retour
        </div>
        
        <div style="display:flex; gap:10px;">
            <button onclick="document.getElementById('bl-form').submit();" class="btn btn-add">
                💾 Sauvegarder
            </button>
            
            <a href="sign.php?id=<?= $id ?>" class="btn">
                ✍️ Faire Signer
            </a>
            
            <a href="javascript:window.print()" class="btn">
                🖨️ Imprimer
            </a>
        </div>
    </div>

    <div class="header">
        <div>
            <h2 style="margin:0;">LOGIFÊTE SERVICES</h2>
            <p style="margin:5px 0;">Bon de Livraison / État des Lieux</p>
        </div>
        <div style="text-align:right;">
            <h3 style="margin:0;">BL N° <?= $id ?></h3>
            <p style="margin:5px 0;">Client : <strong><?= htmlspecialchars($inf['nom_societe']) ?></strong></p>
            <p style="margin:0;">Contact : <?= htmlspecialchars($inf['contact_nom']) ?></p>
        </div>
    </div>

    <p><strong>Période :</strong> Du <?= date('d/m/Y', strtotime($inf['date_debut'])) ?> au <?= date('d/m/Y', strtotime($inf['date_fin'])) ?></p>

    <form method="POST" id="bl-form">
        <input type="hidden" name="save_bl" value="1">
        
        <table>
            <thead>
                <tr>
                    <th style="width:30%;">Matériel (Série)</th>
                    <th style="width:5%;">Départ<br><small>(Ok)</small></th>
                    <th style="width:5%;">Retour<br><small>(Ok)</small></th>
                    <th style="width:20%;">État Général (Retour)</th>
                    <th style="width:40%;">Observations / Dégâts</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $today_date = date('Y-m-d');
                foreach($lignes as $l): 
                    $serie = $l['num_serie'];
                    
                    // LOGIQUE DÉPART
                    $depart_disabled = (!$l['check_depart'] && $today_date < $inf['date_debut']) ? 'disabled' : ''; 

                    // LOGIQUE RETOUR
                    $retour_checkbox_disabled = (!$l['check_retour'] && $today_date < $inf['date_fin']) ? 'disabled' : '';
                    
                    // Désactivation du SELECT/INPUT (État & Obs) si la case Retour est désactivée OU si le Départ n'est pas coché
                    $retour_form_disabled = ($retour_checkbox_disabled || !$l['check_depart']) ? 'disabled' : '';
                ?>
                <tr> 
                    <td>
                        <?= $l['libelle'] ?><br>
                        <small>Ref: <strong><?= $serie ?></strong></small>
                    </td>
                    
                    <td>
                        <span class="no-print">
                            <input type="checkbox" name="rows[<?= $serie ?>][check_depart]" value="1" 
                                <?= $l['check_depart'] ? 'checked' : '' ?>
                                <?= $depart_disabled ?>>
                            <?php if ($depart_disabled): ?>
                                <small style="color:#e74c3c; display:block; font-size:0.7em;">(Départ le <?= date('d/m/Y', strtotime($inf['date_debut'])) ?>)</small>
                            <?php endif; ?>
                            <?php if ($l['date_depart_check']): ?>
                                <small style="color:#2ecc71; display:block; font-size:0.7em;">(Validé le <?= date('d/m/Y H:i', strtotime($l['date_depart_check'])) ?>)</small>
                            <?php endif; ?>
                        </span>
                        <span class="no-screen">
                            <span class="<?= $l['check_depart'] ? 'print-checked' : 'print-check' ?>"></span>
                        </span>
                    </td>
                    
                    <td>
                        <span class="no-print">
                            <input type="checkbox" name="rows[<?= $serie ?>][check_retour]" value="1" 
                                <?= $l['check_retour'] ? 'checked' : '' ?>
                                <?= $retour_checkbox_disabled ?>>
                            <?php if ($retour_checkbox_disabled): ?>
                                <small style="color:#e74c3c; display:block; font-size:0.7em;">(Retour le <?= date('d/m/Y', strtotime($inf['date_fin'])) ?>)</small>
                            <?php endif; ?>
                            <?php if ($l['date_retour_check']): ?>
                                <small style="color:#2ecc71; display:block; font-size:0.7em;">(Validé le <?= date('d/m/Y H:i', strtotime($l['date_retour_check'])) ?>)</small>
                            <?php endif; ?>
                        </span>
                        <span class="no-screen">
                            <span class="<?= $l['check_retour'] ? 'print-checked' : 'print-check' ?>"></span>
                        </span>
                    </td>
                    
                    <td>
                        <select name="rows[<?= $serie ?>][etat_materiel]" <?= $retour_form_disabled ?>>
                            <option value="Neuf" <?= $l['etat_materiel']=='Neuf'?'selected':'' ?>>Neuf</option>
                            <option value="Bon état" <?= $l['etat_materiel']=='Bon état'?'selected':'' ?>>Bon état</option>
                            <option value="Usagé" <?= $l['etat_materiel']=='Usagé'?'selected':'' ?>>Usagé</option>
                            <option value="Endommagé" <?= $l['etat_materiel']=='Endommagé'?'selected':'' ?>>Endommagé</option>
                        </select>
                    </td>

                    <td>
                        <input type="text" name="rows[<?= $serie ?>][observations]" value="<?= htmlspecialchars($l['observations']) ?>" placeholder="R.A.S." <?= $retour_form_disabled ?>>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>

    <div class="signature-zone">
        
        <div class="sign-box">
            <strong>Visa LogiFête (Départ)</strong>
            <br>
            <?php if (!empty($inf['visa_logifete'])): ?>
                <img src="<?= $inf['visa_logifete'] ?>" style="max-width:100%; max-height:80px; display:block; margin:5px auto;">
                <div style="text-align:center; color:green; font-size:0.8em; font-weight:bold;">
                    ✅ Approuvé par Commercial
                </div>
            <?php else: ?>
                <span style="font-family:'Brush Script MT', cursive; font-size:1.5em;">Validé le <?= date('d/m/Y', strtotime($inf['date_creation'])) ?></span>
            <?php endif; ?>
        </div>
        
        <div class="sign-box">
            <strong>Signature Client (Bon pour accord)</strong>
            <br>
            <small style="color:#777;">En signant, le client accepte l'état des lieux.</small>
            
            <?php if (!empty($inf['signature_client'])): ?>
                <img src="<?= $inf['signature_client'] ?>" style="max-width:100%; max-height:80px; display:block; margin:5px auto;">
                <div style="text-align:center; color:green; font-size:0.8em; font-weight:bold;">
                    ✅ Signé électroniquement
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>