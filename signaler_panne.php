<?php
// Fichier : signaler_panne.php
session_start();
require 'db.php';
require 'navbar.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$msg = "";

// TRAITEMENT DU FORMULAIRE
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $num = $_POST['num_serie'];
    $motif = $_POST['motif'];
    $id_tech = $_POST['id_technicien']; 
    // NOUVEAU : Récupération de la date planifiée
    $date = $_POST['date_mission']; 
    
    // 1. On met le statut à 'panne'
    $sql_update = "UPDATE equipement_physique SET statut = 'panne' WHERE num_serie = ?";
    $stmt = $pdo->prepare($sql_update);
    $stmt->execute([$num]);
    
    // 2. AUTOMATISATION : Création mission
    $desc = "RÉPARATION URGENTE : Équipement $num.\nMotif : $motif";
    
    $sql_mission = "INSERT INTO mission (type_mission, date_mission, description, statut) VALUES ('installation', ?, ?, 'a_faire')";
    $pdo->prepare($sql_mission)->execute([$date, $desc]);
    $id_mission = $pdo->lastInsertId();

    // 3. Assignation technicien
    $pdo->prepare("INSERT INTO affectation_tech (id_mission, id_technicien) VALUES (?, ?)")->execute([$id_mission, $id_tech]);
    
    // LOG
    ajouterLog($pdo, "SIGNALEMENT PANNE", "Panne déclarée sur $num (Mission #$id_mission créée). Motif : $motif");
    
    $msg = "✅ Panne enregistrée ET Mission de réparation créée pour le technicien !";
}

// LISTES
$materiel = $pdo->query("SELECT e.num_serie, r.libelle FROM equipement_physique e JOIN reference_materiel r ON e.id_reference = r.id_reference WHERE e.statut != 'panne'")->fetchAll();
$techniciens = $pdo->query("SELECT t.id_technicien, u.nom FROM technicien t JOIN utilisateur u ON t.id_user = u.id_user")->fetchAll();
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="img/logo.png">
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <title>Signaler Panne</title>
</head>
<body>

    <?= renderNavbar($_SESSION['role'], $current_page) ?>

<div class="container">
    <h1 style="color:#e74c3c;">🚨 Signaler une Panne / Casse</h1>
    
    <?php if($msg) echo "<div style='background:#d4edda; color:#155724; padding:15px; border-radius:5px; margin-bottom:20px;'>$msg</div>"; ?>

    <div style="background:#fff; border:1px solid #ddd; padding:20px; border-radius:8px;">
        <form method="POST">
            <label>Quel équipement est endommagé ?</label>
            <select name="num_serie" required>
                <option value="">-- Choisir dans la liste --</option>
                <?php foreach($materiel as $m): ?>
                    <option value="<?= $m['num_serie'] ?>">
                        <?= $m['libelle'] ?> (N° <?= $m['num_serie'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Description du problème (Motif) :</label>
            <textarea name="motif" placeholder="Ex: Câble sectionné, ne s'allume plus..." required></textarea>

            <label style="font-weight:bold; color:#2c3e50;">Date d'intervention prévue :</label>
            <input type="date" name="date_mission" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">

            <label>Technicien chargé de la réparation :</label>
            <select name="id_technicien" required style="border: 2px solid #e67e22;">
                <?php foreach($techniciens as $t): ?>
                    <option value="<?= $t['id_technicien'] ?>"><?= htmlspecialchars($t['nom']) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn-danger" style="width:100%; margin-top:15px;">
                ⚠️ DÉCLARER HORS-SERVICE + CRÉER MISSION
            </button>
        </form>
    </div>
</div>

</body>
</html>