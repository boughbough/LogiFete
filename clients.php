<?php
// Fichier : clients.php
session_start();
require 'db.php';
require 'navbar.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'commercial' && $_SESSION['role'] != 'admin')) {
    header("Location: dashboard.php");
    exit;
}

$msg = "";

// SUPPRESSION CLIENT
if (isset($_GET['del'])) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM commande WHERE id_client = ?");
    $check->execute([$_GET['del']]);
    if ($check->fetchColumn() > 0) {
        $msg = "❌ Impossible de supprimer : Ce client a des commandes en cours !";
    } else {
        $pdo->prepare("DELETE FROM client WHERE id_client = ?")->execute([$_GET['del']]);
        if(function_exists('ajouterLog')) ajouterLog($pdo, "SUPPRESSION CLIENT", "Suppression du client ID " . $_GET['del']);
        $msg = "🗑️ Client supprimé.";
    }
}

// AJOUT CLIENT
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_POST['societe']) && !empty($_POST['email'])) {
        // AJOUT DE L'ADRESSE DANS LA REQUÊTE D'INSERTION
        $sql = "INSERT INTO client (nom_societe, contact_nom, email, telephone, adresse) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_POST['societe'], $_POST['contact'], $_POST['email'], $_POST['tel'], $_POST['adresse']]);
        $msg = "✅ Client ajouté avec succès !";
    }
}

$clients = $pdo->query("SELECT * FROM client ORDER BY id_client DESC")->fetchAll();
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>Gestion Clients - LogiFête</title>
</head>
<body>

    <?= renderNavbar($_SESSION['role'], $current_page) ?>

    <div class="container">
        <h1>👥 Gestion des Clients</h1>
        <?php if($msg) echo "<p style='background:#eee; padding:10px; border-left:4px solid #333;'>$msg</p>"; ?>
        
        <div style="background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ddd;">
            <h3>Nouveau Client</h3>
            <form method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <input type="text" name="societe" placeholder="Nom Société" required style="margin:0">
                <input type="text" name="contact" placeholder="Nom Contact" required style="margin:0">
                <input type="email" name="email" placeholder="Email" required style="margin:0">
                <input type="text" name="tel" placeholder="Téléphone" style="margin:0">
                
                <input type="text" name="adresse" placeholder="Adresse complète (Livraison/Récupération)" required style="margin:0; grid-column: span 2;">
                
                <button type="submit" class="btn-add" style="grid-column: span 2;">+ Créer Fiche Client</button>
            </form>
        </div>

        <h3>Annuaire Clients & Scoring</h3>
        <div style="margin-bottom: 15px;">
            <input type="text" id="live_search_client" placeholder="🔍 Chercher un client (Nom, Email, Tel)..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        <table>
            <thead>
                <tr>
                    <th>Société</th>
                    <th>Contact</th>
                    <th>Email</th>
                    <th>Historique</th>
                    <th>Pénalités Cumulées (Historique)</th> <th>Fiabilité</th> 
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($clients as $c): 
                    // Compte commandes total
                    $nb_cmd = $pdo->prepare("SELECT COUNT(*) FROM commande WHERE id_client = ?");
                    $nb_cmd->execute([$c['id_client']]);
                    $count = $nb_cmd->fetchColumn();

                    // NOUVEAU : Montant total des pénalités
                    $stmt_penalites = $pdo->prepare("SELECT SUM(penalite) FROM commande WHERE id_client = ? AND penalite > 0");
                    $stmt_penalites->execute([$c['id_client']]);
                    $total_penalites = $stmt_penalites->fetchColumn() ?? 0;

                    // On compte les commandes avec pénalités
                    $stmt_bad = $pdo->prepare("SELECT COUNT(*) FROM commande WHERE id_client = ? AND penalite > 0");
                    $stmt_bad->execute([$c['id_client']]);
                    $bad_cmd = $stmt_bad->fetchColumn();

                    // Score de base 5, on enlève 1 étoile par problème
                    $score = 5 - $bad_cmd;
                    if ($score < 1) $score = 1; // Minimum 1 étoile
                    $stars = str_repeat("⭐", $score);
                    // Si score faible, on affiche un warning
                    if ($score <= 3) $stars .= " <span style='font-size:0.8em; color:red;'>(Risque)</span>";
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($c['nom_societe']) ?></strong></td>
                    <td><?= htmlspecialchars($c['contact_nom']) ?></td>
                    <td><?= htmlspecialchars($c['email']) ?></td>
                    <td>
                        <?php if($count > 0): ?>
                            <a href="commandes.php?client=<?= $c['id_client'] ?>" style="font-weight:bold; color:#2980b9; text-decoration:underline;">
                                <?= $count ?> Cmds
                            </a>
                        <?php else: ?>
                            <span style="color:#ccc;">Aucune</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($total_penalites > 0): ?>
                            <strong style="color:#c0392b;"><?= number_format($total_penalites, 2) ?> €</strong>
                            <br><small style="color:#c0392b;">(Total Historique)</small>
                        <?php else: ?>
                            <span style="color:#2ecc71;">0.00 €</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $stars ?></td>
                    <td>
                        <a href="?del=<?= $c['id_client'] ?>" class="btn-danger" style="padding:5px 10px; font-size:0.8em;" onclick="return confirm('Confirmer la suppression ?');">Supprimer</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script>
    document.getElementById('live_search_client').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('tbody tr');

        rows.forEach(row => {
            let text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
    </script>
</body>
</html>