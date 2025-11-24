<?php
// Fichier : admin_stock.php (Version Finale : Avec Fournisseurs & Garantie)
session_start();
require 'db.php';
require 'navbar.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] == 'commercial')) { header("Location: dashboard.php"); exit; }

$msg = "";

// 1. AJOUT REF
if (isset($_POST['add_ref'])) {
    $sql = "INSERT INTO reference_materiel (libelle, description, prix_jour, image_url, categorie, seuil_alerte) VALUES (?, ?, ?, ?, ?, ?)";
    $pdo->prepare($sql)->execute([$_POST['libelle'], $_POST['description'], $_POST['prix'], $_POST['image_url'], $_POST['categorie'], $_POST['seuil']]);
    $msg = "✅ Référence ajoutée !";
}

// 2. GÉNÉRATEUR STOCK (Mis à jour)
if (isset($_POST['add_bulk_stock'])) {
    $id_ref = $_POST['id_ref'];
    $prefix = $_POST['prefix'];
    $quantity = $_POST['quantity'];
    $id_fournisseur = $_POST['id_fournisseur'];
    $garantie_mois = $_POST['garantie_mois'];

    // Calcul date fin garantie
    $date_fin_garantie = date('Y-m-d', strtotime("+$garantie_mois months"));

    // Détection numéro
    $stmt = $pdo->prepare("SELECT num_serie FROM equipement_physique WHERE num_serie LIKE ? ORDER BY LENGTH(num_serie) DESC, num_serie DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $start = $last ? intval(str_replace($prefix, '', $last)) + 1 : 1;

    $sql = "INSERT INTO equipement_physique (num_serie, id_reference, statut, date_ajout, id_fournisseur, fin_garantie) VALUES (?, ?, 'disponible', NOW(), ?, ?)";
    $stmt = $pdo->prepare($sql);

    $count = 0;
    for ($i = 0; $i < $quantity; $i++) {
        try {
            $stmt->execute([$prefix . str_pad($start + $i, 3, '0', STR_PAD_LEFT), $id_ref, $id_fournisseur, $date_fin_garantie]);
            $count++;
        } catch (Exception $e) {}
    }
    $msg = "✅ $count équipements générés (Fournisseur lié).";
    if(function_exists('ajouterLog')) ajouterLog($pdo, "ACHAT STOCK", "Achat de $count unités (Réf #$id_ref)");
}

$refs = $pdo->query("SELECT r.*, (SELECT COUNT(*) FROM equipement_physique e WHERE e.id_reference = r.id_reference) as qte FROM reference_materiel r ORDER BY r.libelle")->fetchAll();
$fournisseurs = $pdo->query("SELECT * FROM fournisseur ORDER BY nom_societe")->fetchAll();
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>Gestion Stock</title>
</head>
<body>
    <?= renderNavbar($_SESSION['role'], $current_page) ?>
<div class="container">
    <h1>🏭 Gestion Catalogue & Approvisionnement</h1>
    <?php if($msg) echo "<p style='color:green; font-weight:bold;'>$msg</p>"; ?>

    <div style="background:#f8f9fa; padding:20px; border-radius:8px; border:1px solid #ddd; margin-bottom:30px;">
        <h3>➕ Nouvelle Référence Catalogue</h3>
        <form method="POST" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
            <input type="text" name="libelle" placeholder="Nom" required style="margin:0">
            <select name="categorie" required style="margin:0">
                <option value="Son">🔊 Son</option><option value="Lumière">💡 Lumière</option><option value="Vidéo">🎥 Vidéo</option><option value="Structure">🏗️ Structure</option><option value="Autre">📦 Autre</option>
            </select>
            <input type="number" step="0.01" name="prix" placeholder="Prix Loc/j (€)" required style="margin:0">
            <input type="number" name="seuil" placeholder="Seuil Alerte" value="2" style="margin:0">
            <input type="text" name="description" placeholder="Desc" style="margin:0; grid-column:span 2;">
            <input type="text" name="image_url" placeholder="Image URL" value="https://placehold.co/100x100" style="margin:0; grid-column:span 2;">
            <button type="submit" name="add_ref" class="btn-add" style="grid-column:span 2;">Ajouter</button>
        </form>
    </div>

    <h3>Approvisionnement (Ajout Stock)</h3>
    <table>
        <thead><tr><th>Matériel</th><th>Stock</th><th>Achat / Ajout Rapide</th></tr></thead>
        <tbody>
            <?php foreach($refs as $r): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($r['libelle']) ?></strong><br>
                    <a href="edit_ref.php?id=<?= $r['id_reference'] ?>" style="color:#f39c12; font-size:0.9em;">✏️ Modifier</a>
                </td>
                <td><span class="role-badge"><?= $r['qte'] ?></span></td>
                <td style="background:#f9f9f9;">
                    <form method="POST" style="display:flex; gap:5px; align-items:center; flex-wrap:wrap;">
                        <input type="hidden" name="id_ref" value="<?= $r['id_reference'] ?>">
                        
                        <input type="text" name="prefix" placeholder="Préfixe (ex: ENC-)" required style="width:100px; padding:5px; margin:0;">
                        <input type="number" name="quantity" value="1" min="1" required style="width:50px; padding:5px; margin:0;">
                        
                        <select name="id_fournisseur" required style="margin:0; padding:5px; width:120px;">
                            <option value="">-- Fournisseur --</option>
                            <?php foreach($fournisseurs as $f) echo "<option value='{$f['id_fournisseur']}'>{$f['nom_societe']}</option>"; ?>
                        </select>
                        
                        <select name="garantie_mois" style="margin:0; padding:5px; width:100px;">
                            <option value="12">Gar. 1 an</option>
                            <option value="24">Gar. 2 ans</option>
                            <option value="36">Gar. 3 ans</option>
                            <option value="0">Aucune</option>
                        </select>

                        <button type="submit" name="add_bulk_stock" class="btn" style="padding:5px 10px; font-size:0.8em;">🛒 Acheter</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>