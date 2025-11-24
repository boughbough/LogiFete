<?php
// Fichier : stock.php (Version Finale : Filtres Garantie & Précision)
session_start();
require 'db.php';
require 'navbar.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$msg = "";
$msg_type = "";

// --- TRAITEMENT : SUPPRESSION ---
if (isset($_POST['delete_bulk']) && !empty($_POST['ids'])) {
    if ($_SESSION['role'] == 'commercial') {
        $msg = "⛔ Action non autorisée.";
        $msg_type = "error";
    } else {
        $ids = $_POST['ids'];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $sql_check = "SELECT DISTINCT num_serie FROM reservation_equipement WHERE num_serie IN ($placeholders)";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute($ids);
        $bloques = $stmt_check->fetchAll(PDO::FETCH_COLUMN);

        if ($bloques) {
            $msg = "⛔ Suppression impossible pour : " . implode(', ', $bloques);
            $msg_type = "error";
        } else {
            $pdo->prepare("DELETE FROM equipement_physique WHERE num_serie IN ($placeholders)")->execute($ids);
            $msg = "🗑️ Équipements supprimés.";
            $msg_type = "success";
        }
    }
}

// --- FILTRES ---
$where = ["1=1"];
$params = [];

// Recherche Texte
if (!empty($_GET['q'])) {
    $where[] = "(r.libelle LIKE ? OR e.num_serie LIKE ?)";
    $params[] = "%".$_GET['q']."%";
    $params[] = "%".$_GET['q']."%";
}
// Filtre Catégorie
if (!empty($_GET['cat'])) {
    $where[] = "r.categorie = ?";
    $params[] = $_GET['cat'];
}
// Filtre Fournisseur
if (!empty($_GET['fournisseur'])) {
    $where[] = "e.id_fournisseur = ?";
    $params[] = $_GET['fournisseur'];
}

// --- NOUVEAU : FILTRE GARANTIE ---
if (!empty($_GET['garantie'])) {
    if ($_GET['garantie'] == 'active') {
        $where[] = "e.fin_garantie >= CURDATE()";
    } elseif ($_GET['garantie'] == 'expired') {
        $where[] = "e.fin_garantie < CURDATE()";
    } elseif ($_GET['garantie'] == 'none') {
        $where[] = "e.fin_garantie IS NULL";
    }
}

// Tri
$order = "e.date_ajout DESC";
if (!empty($_GET['sort'])) {
    switch ($_GET['sort']) {
        case 'date_asc': $order = "e.date_ajout ASC"; break;
        case 'prix_asc': $order = "r.prix_jour ASC"; break;
        case 'prix_desc': $order = "r.prix_jour DESC"; break;
        case 'nom': $order = "r.libelle ASC"; break;
    }
}

// --- REQUÊTE ---
$sql = "SELECT e.*, r.libelle, r.categorie, r.prix_jour, f.nom_societe as nom_fournisseur,
        (
            SELECT CONCAT(c.id_commande, '|', c.etat) 
            FROM reservation_equipement re
            JOIN commande c ON re.id_commande = c.id_commande
            WHERE re.num_serie = e.num_serie
            AND c.etat IN ('devis', 'validee')
            AND c.date_fin >= CURDATE()
            ORDER BY c.date_debut ASC 
            LIMIT 1
        ) as infos_commande
        FROM equipement_physique e
        JOIN reference_materiel r ON e.id_reference = r.id_reference
        LEFT JOIN fournisseur f ON e.id_fournisseur = f.id_fournisseur
        WHERE " . implode(" AND ", $where) . "
        ORDER BY $order";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$equipements = $stmt->fetchAll();

// Listes
$cats = $pdo->query("SELECT DISTINCT categorie FROM reference_materiel WHERE categorie IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
$fournisseurs_list = $pdo->query("SELECT * FROM fournisseur ORDER BY nom_societe")->fetchAll();
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>Inventaire</title>
    <style>
        .link-badge { font-size: 0.8em; padding: 2px 6px; border-radius: 4px; text-decoration: none; margin-left: 8px; vertical-align: middle; display: inline-block; }
        .link-devis { background: #f39c12; color: white; }
        .link-validee { background: #27ae60; color: white; }
        .link-panne { background: #c0392b; color: white; pointer-events: none; }
        .filters { background: #f9f9f9; padding: 15px; display: flex; gap: 15px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; }
        .filter-group { display: flex; align-items: center; gap: 5px; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
    <script>
        function toggleAll(s) {
            document.getElementsByName('ids[]').forEach(c => c.checked = s.checked);
        }
    </script>
</head>
<body>

        <?= renderNavbar($_SESSION['role'], $current_page) ?>

<div class="container" style="max-width: 1300px;">
    
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>📦 Inventaire Complet</h1>
        <a href="export_stock.php" class="btn" style="background:#27ae60; font-size:0.9em;">📥 Export Excel</a>
    </div>

    <?php if($msg): ?>
        <div class="alert alert-<?= $msg_type ?>"><?= $msg ?></div>
    <?php endif; ?>

    <form method="GET" class="filters">
        <div class="filter-group">
            <label>🔍</label>
            <input type="text" name="q" placeholder="Recherche..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" style="margin:0;">
        </div>

        <div class="filter-group">
            <label>📂</label>
            <select name="cat" onchange="this.form.submit()" style="margin:0; cursor:pointer;">
                <option value="">Toutes catégories</option>
                <?php foreach($cats as $c): ?>
                    <option value="<?= $c ?>" <?= (isset($_GET['cat']) && $_GET['cat'] == $c) ? 'selected' : '' ?>><?= $c ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>🏭</label>
            <select name="fournisseur" onchange="this.form.submit()" style="margin:0; cursor:pointer;">
                <option value="">Tous fournisseurs</option>
                <?php foreach($fournisseurs_list as $f): ?>
                    <option value="<?= $f['id_fournisseur'] ?>" <?= (isset($_GET['fournisseur']) && $_GET['fournisseur'] == $f['id_fournisseur']) ? 'selected' : '' ?>><?= htmlspecialchars($f['nom_societe']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>🛡️</label>
            <select name="garantie" onchange="this.form.submit()" style="margin:0; cursor:pointer;">
                <option value="">Toute garantie</option>
                <option value="active" <?= (isset($_GET['garantie']) && $_GET['garantie'] == 'active') ? 'selected' : '' ?>>✅ Sous garantie</option>
                <option value="expired" <?= (isset($_GET['garantie']) && $_GET['garantie'] == 'expired') ? 'selected' : '' ?>>❌ Expirée</option>
                <option value="none" <?= (isset($_GET['garantie']) && $_GET['garantie'] == 'none') ? 'selected' : '' ?>>⚪ Aucune</option>
            </select>
        </div>

        <div class="filter-group">
            <label>⇅</label>
            <select name="sort" onchange="this.form.submit()" style="margin:0; cursor:pointer;">
                <option value="date_desc" <?= (isset($_GET['sort']) && $_GET['sort'] == 'date_desc') ? 'selected' : '' ?>>📅 Ajout : Récent</option>
                <option value="date_asc" <?= (isset($_GET['sort']) && $_GET['sort'] == 'date_asc') ? 'selected' : '' ?>>📅 Ajout : Ancien</option>
                <option value="prix_asc" <?= (isset($_GET['sort']) && $_GET['sort'] == 'prix_asc') ? 'selected' : '' ?>>€ Prix : Croissant</option>
                <option value="prix_desc" <?= (isset($_GET['sort']) && $_GET['sort'] == 'prix_desc') ? 'selected' : '' ?>>€ Prix : Décroissant</option>
                <option value="nom" <?= (isset($_GET['sort']) && $_GET['sort'] == 'nom') ? 'selected' : '' ?>>Abc Nom</option>
            </select>
        </div>
        <a href="stock.php" style="margin-left:auto; color:#666; font-size:0.9em;">Réinitialiser</a>
    </form>

    <form method="POST" onsubmit="return confirm('Supprimer la sélection ?');">
        <?php if ($_SESSION['role'] != 'commercial'): ?>
            <button type="submit" name="delete_bulk" class="btn-danger" style="font-size:0.8em; padding:5px 10px; margin-bottom:10px;">🗑️ Supprimer sélection</button>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th width="30"><input type="checkbox" onclick="toggleAll(this)"></th>
                    <th width="60">QR</th>
                    <th>Série</th>
                    <th>Catégorie</th>
                    <th>Matériel</th>
                    <th>Fournisseur</th>
                    <th>Garantie</th> <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($equipements as $item): ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?= $item['num_serie'] ?>"></td>

                        <td style="text-align:center;">
                            <img src="https://quickchart.io/qr?text=<?= urlencode($item['num_serie']) ?>&size=60" style="border:1px solid #eee; vertical-align:middle;" alt="QR">
                        </td>

                        <td>
                            <a href="fiche_produit.php?ref=<?= $item['num_serie'] ?>" style="text-decoration:none; color:#2980b9; font-weight:bold; border-bottom:2px solid #3498db;">
                                <?= htmlspecialchars($item['num_serie']) ?>
                            </a>
                        </td>
                        
                        <td><span style="background:#ecf0f1; color:#333; padding:2px 6px; border-radius:4px; font-size:0.8em;"><?= htmlspecialchars($item['categorie']) ?></span></td>
                        
                        <td>
                            <?= htmlspecialchars($item['libelle']) ?>
                            <?php 
                            if ($item['infos_commande']) {
                                list($id_cmd, $etat_cmd) = explode('|', $item['infos_commande']);
                                $class = ($etat_cmd == 'devis') ? 'link-devis' : 'link-validee';
                                $label = ($etat_cmd == 'devis') ? 'Devis' : 'Cmd';
                                echo "<a href='commande_details.php?id=$id_cmd' class='link-badge $class' target='_blank'>🔗 $label #$id_cmd</a>";
                            } elseif ($item['statut'] == 'panne') {
                                echo "<span class='link-badge link-panne'>⚠️ HS</span>";
                            }
                            ?>
                        </td>

                        <td style="font-size:0.9em; color:#555;">
                            <?= $item['nom_fournisseur'] ? htmlspecialchars($item['nom_fournisseur']) : '-' ?>
                        </td>

                        <td style="font-size:0.85em;">
                            <?php 
                            if ($item['fin_garantie']) {
                                $date_fin = strtotime($item['fin_garantie']);
                                if ($date_fin > time()) {
                                    echo "<span style='color:#27ae60; font-weight:bold;'>✅ Valide</span><br><span style='color:#777;'>Jusqu'au ".date('d/m/y', $date_fin)."</span>";
                                } else {
                                    echo "<span style='color:#c0392b; font-weight:bold;'>❌ Expirée</span><br><span style='color:#999;'>Depuis ".date('d/m/y', $date_fin)."</span>";
                                }
                            } else {
                                echo "<span style='color:#ccc;'>Aucune</span>";
                            }
                            ?>
                        </td>
                        
                        <td>
                            <span class="badge status-<?= $item['statut'] ?>"><?= strtoupper($item['statut']) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>
</div>

</body>
</html>