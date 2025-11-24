<?php
// Fichier : fournisseurs.php
session_start();
require 'db.php';
require 'navbar.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'commercial') { header("Location: dashboard.php"); exit; }

$msg = "";

// AJOUT
if (isset($_POST['add_fournisseur'])) {
    $sql = "INSERT INTO fournisseur (nom_societe, telephone, email, site_web) VALUES (?, ?, ?, ?)";
    $pdo->prepare($sql)->execute([$_POST['nom'], $_POST['tel'], $_POST['email'], $_POST['web']]);
    $msg = "✅ Fournisseur ajouté.";
    if(function_exists('ajouterLog')) ajouterLog($pdo, "ACHAT", "Nouveau fournisseur : " . $_POST['nom']);
}

// SUPPRESSION
if (isset($_GET['del'])) {
    // On vérifie si on a du matériel de ce fournisseur
    $check = $pdo->prepare("SELECT COUNT(*) FROM equipement_physique WHERE id_fournisseur = ?");
    $check->execute([$_GET['del']]);
    if ($check->fetchColumn() > 0) {
        $msg = "❌ Impossible : Nous possédons du matériel venant de ce fournisseur.";
    } else {
        $pdo->prepare("DELETE FROM fournisseur WHERE id_fournisseur = ?")->execute([$_GET['del']]);
        $msg = "🗑️ Fournisseur supprimé.";
    }
}

$fournisseurs = $pdo->query("SELECT * FROM fournisseur ORDER BY nom_societe")->fetchAll();
$current_page = basename($_SERVER['PHP_SELF']);?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>Fournisseurs</title>
</head>
<body>

<?= renderNavbar($_SESSION['role'], $current_page) ?>
<div class="container">
    <h1>🏭 Gestion des Fournisseurs & SAV</h1>
    <?php if($msg) echo "<p style='background:#eee; padding:10px;'>$msg</p>"; ?>

    <div style="background:#fff; padding:20px; border:1px solid #ddd; border-radius:8px; margin-bottom:20px;">
        <h3>Nouveau Partenaire</h3>
        <form method="POST" style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
            <input type="text" name="nom" placeholder="Nom Société (ex: Thomann)" required style="margin:0">
            <input type="text" name="tel" placeholder="Téléphone SAV" style="margin:0">
            <input type="email" name="email" placeholder="Email SAV" style="margin:0">
            <input type="text" name="web" placeholder="Site Web" style="margin:0">
            <button type="submit" name="add_fournisseur" class="btn-add" style="grid-column:span 2;">Ajouter</button>
        </form>
    </div>

    <table>
        <thead><tr><th>Société</th><th>Contact SAV</th><th>Site Web</th><th>Matériel en parc</th><th>Action</th></tr></thead>
        <tbody>
            <?php foreach($fournisseurs as $f): 
                $nb_matos = $pdo->prepare("SELECT COUNT(*) FROM equipement_physique WHERE id_fournisseur = ?");
                $nb_matos->execute([$f['id_fournisseur']]);
                $count = $nb_matos->fetchColumn();
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($f['nom_societe']) ?></strong></td>
                <td>
                    📞 <?= htmlspecialchars($f['telephone']) ?><br>
                    📧 <?= htmlspecialchars($f['email']) ?>
                </td>
                <td><a href="<?= htmlspecialchars($f['site_web']) ?>" target="_blank">Visiter</a></td>
                <td><span class="badge" style="background:#34495e; color:white;"><?= $count ?> unités</span></td>
                <td>
                    <a href="?del=<?= $f['id_fournisseur'] ?>" class="btn-danger" style="font-size:0.8em; padding:5px 10px;" onclick="return confirm('Supprimer ?')">Supprimer</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>