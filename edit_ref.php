<?php
// Fichier : edit_ref.php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) { header("Location: admin_stock.php"); exit; }

$id = $_GET['id'];
$msg = "";

// MISE À JOUR
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $sql = "UPDATE reference_materiel SET libelle=?, description=?, prix_jour=?, image_url=? WHERE id_reference=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_POST['libelle'], $_POST['description'], $_POST['prix'], $_POST['img'], $id]);
    
    // LOG
    ajouterLog($pdo, "MODIFICATION REF", "Mise à jour de la référence ID $id (" . $_POST['libelle'] . ")");
    
    $msg = "✅ Modifications enregistrées !";
}

// Récupération infos
$ref = $pdo->prepare("SELECT * FROM reference_materiel WHERE id_reference = ?");
$ref->execute([$id]);
$item = $ref->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>Modifier Référence</title>
</head>
<body>
    <div class="container" style="max-width:600px;">
        <h2>✏️ Modifier : <?= htmlspecialchars($item['libelle']) ?></h2>
        <?php if($msg) echo "<p style='color:green; font-weight:bold;'>$msg</p>"; ?>
        
        <form method="POST">
            <label>Nom du matériel</label>
            <input type="text" name="libelle" value="<?= htmlspecialchars($item['libelle']) ?>" required>
            
            <label>Description</label>
            <textarea name="description" rows="3"><?= htmlspecialchars($item['description']) ?></textarea>
            
            <label>Prix Journalier (€)</label>
            <input type="number" step="0.01" name="prix" value="<?= $item['prix_jour'] ?>" required>
            
            <label>URL Image</label>
            <input type="text" name="img" value="<?= htmlspecialchars($item['image_url']) ?>">
            
            <div style="display:flex; justify-content:space-between; margin-top:20px;">
                <a href="admin_stock.php" class="btn-back">Annuler</a>
                <button type="submit" class="btn-add">Enregistrer les modifications</button>
            </div>
        </form>
    </div>
</body>
</html>