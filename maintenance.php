<?php
// Fichier : maintenance.php (Version Finale : Avec Coûts)
session_start();
require 'db.php';
require 'navbar.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] == 'commercial')) {
    header("Location: dashboard.php");
    exit;
}

$msg = "";

// ACTION : RÉPARER
if (isset($_POST['reparer_id'])) {
    $id_a_reparer = $_POST['reparer_id'];
    $cout = floatval($_POST['cout']); // Nouveau champ
    
    // 1. Mise à jour statut + ajout du coût au cumul
    $sql = "UPDATE equipement_physique 
            SET statut = 'disponible', cout_reparations = cout_reparations + ? 
            WHERE num_serie = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cout, $id_a_reparer]);
    
    // 2. Clôture mission
    $sql_mission = "UPDATE mission SET statut = 'terminee' WHERE description LIKE ? AND statut = 'a_faire'";
    $pdo->prepare($sql_mission)->execute(["%" . $id_a_reparer . "%"]);
    
    ajouterLog($pdo, "REPARATION", "Réparation $id_a_reparer (Coût: $cout €)");
    $msg = "✅ Équipement réparé et remis en stock (Coût enregistré : $cout €).";
}

// ACTION : JETER
if (isset($_POST['supprimer_id'])) {
    $id_a_jeter = $_POST['supprimer_id'];
    $pdo->prepare("DELETE FROM equipement_physique WHERE num_serie = ?")->execute([$id_a_jeter]);
    
    // Clôture mission
    $sql_mission = "UPDATE mission SET statut = 'terminee' WHERE description LIKE ? AND statut = 'a_faire'";
    $pdo->prepare($sql_mission)->execute(["%" . $id_a_jeter . "%"]);
    
    ajouterLog($pdo, "MISE AU REBUT", "Destruction $id_a_jeter");
    $msg = "🗑️ Équipement retiré de l'inventaire.";
}

$pannes = $pdo->query("SELECT e.num_serie, r.libelle, r.prix_jour FROM equipement_physique e JOIN reference_materiel r ON e.id_reference = r.id_reference WHERE e.statut = 'panne'")->fetchAll();
$current_page = basename($_SERVER['PHP_SELF']);?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>Maintenance - LogiFête</title>
</head>
<body>

<?= renderNavbar($_SESSION['role'], $current_page) ?>

<div class="container">
    <h1 style="color:#e67e22;">🛠️ Atelier de Réparation</h1>
    <p>Déclaration des coûts de réparation pour le calcul de rentabilité.</p>

    <?php if($msg) echo "<div style='background:#d4edda; color:#155724; padding:15px; border-radius:5px; margin-bottom:20px;'>$msg</div>"; ?>

    <?php if(count($pannes) == 0): ?>
        <div style="text-align:center; padding:50px; color:gray;">
            <h2>🎉 Tout va bien !</h2>
            <p>Aucun matériel en panne.</p>
        </div>
    <?php else: ?>

    <table>
        <thead>
            <tr>
                <th>Matériel</th>
                <th>Valeur Locative</th>
                <th>Coût Intervention</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($pannes as $p): ?>
                <tr>
                    <td>
                        <strong style="color:#c0392b;"><?= $p['num_serie'] ?></strong><br>
                        <?= htmlspecialchars($p['libelle']) ?>
                    </td>
                    <td><?= $p['prix_jour'] ?> €/j</td>
                    <td>
                        <form method="POST" style="display:flex; gap:10px; align-items:center;">
                            <input type="hidden" name="reparer_id" value="<?= $p['num_serie'] ?>">
                            
                            <div style="display:flex; flex-direction:column;">
                                <label style="font-size:0.7em; margin:0;">Coût (€)</label>
                                <input type="number" name="cout" value="0" min="0" step="0.01" style="width:80px; padding:5px; margin:0;">
                            </div>
                            
                            <button type="submit" class="btn" style="background-color:#2ecc71; padding:5px 10px; font-size:0.9em; height:35px; margin-top:15px;">
                                ✅ Valider & Réparer
                            </button>
                        </form>
                    </td>
                    <td style="vertical-align:middle;">
                        <form method="POST" onsubmit="return confirm('Jeter définitivement ?');">
                            <input type="hidden" name="supprimer_id" value="<?= $p['num_serie'] ?>">
                            <button type="submit" class="btn" style="background-color:#7f8c8d; padding:5px 10px; font-size:0.9em;">
                                🗑️ Jeter
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

</body>
</html>