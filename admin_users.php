<?php
// Fichier : admin_users.php
session_start();
require 'db.php';
require 'navbar.php';

// Sécurité : ADMIN SEULEMENT
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

$msg = "";

// AJOUTER UN UTILISATEUR
if (isset($_POST['add_user'])) {
    $nom = $_POST['nom'];
    $email = $_POST['email'];
    $hashed_pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    
    $sql = "INSERT INTO utilisateur (nom, email, mot_de_passe, role) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    try {
        $stmt->execute([$nom, $email, $hashed_pass, $role]);
        
        // LOG
        ajouterLog($pdo, "CREATION USER", "Création du compte $email (Rôle: $role)");
        
        $msg = "✅ Utilisateur $nom créé avec succès !";
    } catch (Exception $e) {
        $msg = "❌ Erreur : Cet email existe peut-être déjà.";
    }
}

// SUPPRIMER UN UTILISATEUR
if (isset($_GET['del'])) {
    $id_del = (int)$_GET['del'];
    if ($id_del != $_SESSION['user_id']) { 
        try {
            // Récupérer le nom avant suppression pour le log
            $stmt_nom = $pdo->prepare("SELECT email FROM utilisateur WHERE id_user = ?");
            $stmt_nom->execute([$id_del]);
            $nom_del = $stmt_nom->fetchColumn();
            
            // Vérifier si l'utilisateur a des commandes associées
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM commande WHERE id_commercial = ?");
            $stmt_check->execute([$id_del]);
            $nb_commandes = $stmt_check->fetchColumn();
            
            if ($nb_commandes > 0) {
                // L'utilisateur a des commandes, on ne peut pas le supprimer directement
                $msg = "⚠️ Impossible de supprimer cet utilisateur : il a $nb_commandes commande(s) associée(s). Vous devez d'abord réassigner ses commandes à un autre commercial.";
            } else {
                // Aucune commande, on peut supprimer en toute sécurité
                $stmt_del = $pdo->prepare("DELETE FROM utilisateur WHERE id_user = ?");
                $stmt_del->execute([$id_del]);
                
                // LOG (avec gestion d'erreur)
                try {
                    if (function_exists('ajouterLog')) {
                        ajouterLog($pdo, "SUPPRESSION USER", "Suppression du compte ID $id_del ($nom_del)");
                    }
                } catch (Exception $e) {
                    // Ignore les erreurs de log pour ne pas bloquer la suppression
                }
                
                // Redirection pour éviter re-soumission
                header("Location: admin_users.php?success=deleted");
                exit;
            }
        } catch (Exception $e) {
            $msg = "❌ Erreur lors de la suppression : " . $e->getMessage();
        }
    } else {
        $msg = "⚠️ Impossible de supprimer votre propre compte !";
    }
}

// Message de succès après redirection
if (isset($_GET['success']) && $_GET['success'] == 'deleted') {
    $msg = "🗑️ Utilisateur supprimé avec succès.";
}

$users = $pdo->query("SELECT * FROM utilisateur ORDER BY role, nom")->fetchAll();
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>Administration RH</title>
</head>
<body>
<?= renderNavbar($_SESSION['role'], $current_page) ?>

<div class="container">
    <h1>👨‍💼 Gestion du Personnel</h1>
    <?php if($msg) echo "<p style='font-weight:bold; color:blue;'>$msg</p>"; ?>

    <div style="background:#f1f1f1; padding:15px; border-radius:8px; margin-bottom:20px;">
        <h3>Nouveau Collaborateur</h3>
        <form method="POST" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            <div style="flex:1;">
                <label>Nom complet</label>
                <input type="text" name="nom" required style="margin:0;">
            </div>
            <div style="flex:1;">
                <label>Email (Login)</label>
                <input type="email" name="email" required style="margin:0;">
            </div>
            <div style="flex:1;">
                <label>Mot de passe</label>
                <input type="text" name="password" required style="margin:0;">
            </div>
            <div style="flex:1;">
                <label>Rôle</label>
                <select name="role" style="margin:0;">
                    <option value="commercial">Commercial</option>
                    <option value="technicien">Technicien</option>
                    <option value="admin">Administrateur</option>
                </select>
            </div>
            <button type="submit" name="add_user" class="btn-add">Ajouter</button>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Nom</th>
                <th>Email</th>
                <th>Rôle</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['nom']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <span class="role-badge" style="background:#7f8c8d; color:white;">
                        <?= strtoupper($u['role']) ?>
                    </span>
                </td>
                <td>
                    <?php if($u['id_user'] != $_SESSION['user_id']): ?>
                        <a href="?del=<?= $u['id_user'] ?>" class="btn-danger" style="padding:5px 10px; font-size:0.8em;" onclick="return confirm('Sûr ?');">Supprimer</a>
                    <?php else: ?>
                        <small>(Vous)</small>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>