<?php
// Fichier : admin_logs.php
session_start();
require 'db.php';
require 'navbar.php';

// Sécurité : ADMIN SEULEMENT
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

// --- NETTOYAGE AUTOMATIQUE DES LOGS (> 6 MOIS) ---
try {
    $date_limite = date('Y-m-d H:i:s', strtotime('-6 months'));
    $stmt_clean = $pdo->prepare("DELETE FROM historique_logs WHERE date_action < ?");
    $stmt_clean->execute([$date_limite]);
    $nb_deleted = $stmt_clean->rowCount();
    
    if ($nb_deleted > 0) {
        ajouterLog($pdo, "ADMIN AUDIT", "Nettoyage auto : $nb_deleted logs supprimés.");
    }
} catch (Exception $e) { }

// Récupération des logs
$sql = "SELECT l.*, u.nom, u.role 
        FROM historique_logs l
        JOIN utilisateur u ON l.id_user = u.id_user
        ORDER BY l.date_action DESC 
        LIMIT 100";
$logs = $pdo->query($sql)->fetchAll();
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>Journal d'Audit</title>
    <style>
        /* Styles BADGES LOGS - HAUT CONTRASTE */
        .log-badge {
            padding: 5px 10px;
            border-radius: 3px; /* Coins légèrement arrondis */
            font-size: 0.8em;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
            letter-spacing: 0.5px;
            border: 1px solid transparent; /* Bordure pour la définition */
            min-width: 100px;
            text-align: center;
        }

        /* VERT : Connexions & Créations */
        .log-connexion { 
            color: #0f5132; /* Vert très foncé */
            background-color: #d1e7dd; /* Vert d'eau */
            border-color: #badbcc;
        }

        /* ROUGE : Suppressions & Erreurs */
        .log-suppression { 
            color: #842029; /* Bordeaux */
            background-color: #f8d7da; /* Rose pâle */
            border-color: #f5c2c7;
        }

        /* BLEU : Validations & Modifications */
        .log-validation { 
            color: #055160; /* Bleu Canard Foncé */
            background-color: #cff4fc; /* Cyan pâle */
            border-color: #b6effb;
        }
        
        /* GRIS : Info par défaut */
        .log-info {
            color: #636464;
            background-color: #fefefe;
            border: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
    <?= renderNavbar($_SESSION['role'], $current_page) ?>

<div class="container">
    <h1>🕵️‍♂️ Journal d'Activité (Audit)</h1>
    <p style="color:var(--text-muted); margin-bottom:20px;">
        Traçabilité des 100 dernières actions critiques. Les archives de plus de 6 mois sont purgées automatiquement.
    </p>

    <table>
        <thead>
            <tr>
                <th>Date / Heure</th>
                <th>Utilisateur</th>
                <th>Type d'Action</th>
                <th>Détails de l'opération</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($logs as $l): 
                $action_code = strtoupper($l['action']);
                $class = "log-info"; // Défaut

                // Logique de couleur stricte
                if (strpos($action_code, 'CONNEXION') !== false || strpos($action_code, 'CREATION') !== false || strpos($action_code, 'AJOUT') !== false) {
                    $class = "log-connexion";
                }
                elseif (strpos($action_code, 'SUPPRESSION') !== false || strpos($action_code, 'ERREUR') !== false || strpos($action_code, 'PANNE') !== false) {
                    $class = "log-suppression";
                }
                elseif (strpos($action_code, 'VALIDATION') !== false || strpos($action_code, 'MODIF') !== false || strpos($action_code, 'RETOUR') !== false) {
                    $class = "log-validation";
                }
            ?>
            <tr>
                <td style="font-size:0.9em; color:var(--text-muted); width: 180px;">
                    <?= date('d/m/Y H:i:s', strtotime($l['date_action'])) ?>
                </td>
                
                <td style="width: 200px;">
                    <strong><?= htmlspecialchars($l['nom']) ?></strong>
                    <div style="font-size:0.75em; color:var(--text-muted);"><?= strtoupper($l['role']) ?></div>
                </td>
                
                <td style="width: 150px;">
                    <span class="log-badge <?= $class ?>">
                        <?= htmlspecialchars($l['action']) ?>
                    </span>
                </td>
                
                <td style="color:var(--text-main);">
                    <?= htmlspecialchars($l['details']) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>