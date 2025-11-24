<?php
// Fichier : admin_kits.php
session_start();
require 'db.php';
require 'navbar.php'; // 1. Inclusion de la navbar

if (!isset($_SESSION['user_id'])) { header("Location: dashboard.php"); exit; }

$msg = "";

// 1. CRÉER UN KIT
if (isset($_POST['create_kit'])) {
    $pdo->prepare("INSERT INTO kit (libelle) VALUES (?)")->execute([$_POST['nom_kit']]);
    $msg = "✅ Kit créé ! Ajoutez des objets dedans.";
}

// 2. AJOUTER OBJET DANS KIT
if (isset($_POST['add_item'])) {
    $pdo->prepare("INSERT INTO kit_contenu (id_kit, id_reference, quantite) VALUES (?, ?, ?)")
        ->execute([$_POST['id_kit'], $_POST['id_ref'], $_POST['qte']]);
}

$kits = $pdo->query("SELECT * FROM kit")->fetchAll();
$refs = $pdo->query("SELECT * FROM reference_materiel ORDER BY libelle")->fetchAll();

// 2. Définition de la page courante
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="img/logo.png">
    <title>Gestion des Kits</title>
</head>
<body>

    <?= renderNavbar($_SESSION['role'], $current_page) ?>

    <div class="container">
        <h1>📦 Gestion des Kits (Bundles)</h1>
        <?php if($msg) echo "<p style='color:green'>$msg</p>"; ?>

        <form method="POST" style="background:#f9f9f9; padding:15px; border-radius:5px; display:flex; gap:10px; border: 1px solid #ddd;">
            <input type="text" name="nom_kit" placeholder="Nom du Kit (ex: Pack Son)" required style="margin:0">
            <button type="submit" name="create_kit" class="btn-add">Créer Kit</button>
        </form>
        <br>

        <?php foreach($kits as $k): 
            $contenu = $pdo->prepare("SELECT kc.*, r.libelle FROM kit_contenu kc JOIN reference_materiel r ON kc.id_reference = r.id_reference WHERE id_kit = ?");
            $contenu->execute([$k['id_kit']]);
            $items = $contenu->fetchAll();
        ?>
        <div style="border:1px solid #ddd; padding:15px; border-radius:5px; margin-bottom:15px; background: white;">
            <h3 style="margin-top:0;">📂 <?= htmlspecialchars($k['libelle']) ?></h3>
            <ul style="margin-bottom:10px;">
                <?php foreach($items as $i): ?>
                    <li><?= $i['quantite'] ?>x <?= htmlspecialchars($i['libelle']) ?></li>
                <?php endforeach; ?>
            </ul>
            
            <form method="POST" style="display:flex; gap:5px; align-items:center; background:#f1f1f1; padding:10px; border-radius: 4px;">
                <input type="hidden" name="id_kit" value="<?= $k['id_kit'] ?>">
                <select name="id_ref" style="margin:0;">
                    <?php foreach($refs as $r) echo "<option value='{$r['id_reference']}'>{$r['libelle']}</option>"; ?>
                </select>
                <input type="number" name="qte" value="1" style="width:60px; margin:0;">
                <button type="submit" name="add_item" class="btn" style="font-size:0.8em;">+ Ajouter au kit</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>