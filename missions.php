<?php
// Fichier : missions.php
session_start();
require 'db.php';
require 'navbar.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$message = "";

// 1. CRÉATION DE MISSION
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['creer_mission'])) {
    $type = $_POST['type'];
    $date = $_POST['date'];
    $desc = $_POST['description'];
    $id_tech = $_POST['id_technicien'];
    $id_cmd = $_POST['id_commande'] ?? NULL; 

    $sql = "INSERT INTO mission (type_mission, date_mission, description, statut, id_commande) VALUES (?, ?, ?, 'a_faire', ?)";
    $pdo->prepare($sql)->execute([$type, $date, $desc, $id_cmd]);
    $id_mission = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO affectation_tech (id_mission, id_technicien) VALUES (?, ?)")->execute([$id_mission, $id_tech]);
    $message = "✅ Mission planifiée avec succès !";
}

// 2. TERMINER UNE MISSION
if (isset($_GET['terminer_id'])) {
    $pdo->prepare("UPDATE mission SET statut = 'terminee' WHERE id_mission = ?")->execute([$_GET['terminer_id']]);
    header("Location: missions.php");
    exit;
}

// --- LOGIQUE DE PRÉ-REMPLISSAGE ---
$pre_type = "";
$pre_desc = "";
$pre_cmd_id = "";
$pre_date = date('Y-m-d'); 

if (isset($_GET['action']) && $_GET['action'] == 'reparer' && isset($_GET['ref'])) {
    $ref = htmlspecialchars($_GET['ref']);
    $motif = isset($_GET['motif']) ? htmlspecialchars($_GET['motif']) : "Panne signalée";
    
    // CAS 1: RÉPARATION
    $pre_type = "installation";
    $pre_desc = "RÉPARATION URGENTE : Équipement $ref.\nMotif : $motif.\nObjectif : Remettre en état pour le stock.";

} elseif (isset($_GET['action']) && $_GET['action'] == 'prefill' && isset($_GET['cmd_id'])) {
    // CAS 2: LIVRAISON/RÉCUPÉRATION
    
    $cmd_id = intval($_GET['cmd_id']);
    $type = htmlspecialchars($_GET['type']);
    $pre_cmd_id = $cmd_id;
    
    if (isset($_GET['date'])) {
        $pre_date = $_GET['date'];
    }
    
    // Récupération infos client (Nom + ADRESSE)
    $stmt_info = $pdo->prepare("SELECT cl.nom_societe, cl.adresse, c.date_debut, c.date_fin FROM commande c JOIN client cl ON c.id_client = cl.id_client WHERE id_commande = ?");
    $stmt_info->execute([$cmd_id]);
    $info = $stmt_info->fetch();
    
    if ($info) {
        $client_nom = htmlspecialchars($info['nom_societe']);
        $client_adresse = htmlspecialchars($info['adresse'] ?? 'Non renseignée'); // Gestion si adresse vide
        $date_ref = ($type == 'livraison') ? date('d/m/Y', strtotime($info['date_debut'])) : date('d/m/Y', strtotime($info['date_fin']));
        
        $pre_type = $type;
        $action_label = ($type == 'livraison') ? "Livraison" : "Récupération";
        
        $pre_desc = "MISSION $action_label pour Commande #$cmd_id (Client: $client_nom).\nDate Contractuelle: $date_ref.\nADRESSE: $client_adresse";
    }
}

// --- FILTRES DATE ---
$date_filter_clause = "";
if (isset($_GET['date_view'])) {
    $date_view = $_GET['date_view'];
    if ($date_view == 'today') {
        $date_filter_clause = " AND m.date_mission = CURDATE() ";
    } elseif ($date_view == 'tomorrow') {
        $date_filter_clause = " AND m.date_mission = DATE_ADD(CURDATE(), INTERVAL 1 DAY) ";
    } elseif ($date_view == 'week') {
        $date_filter_clause = " AND YEARWEEK(m.date_mission, 3) = YEARWEEK(CURDATE(), 3) ";
    } elseif ($date_view == 'next_week') {
        $date_filter_clause = " AND YEARWEEK(m.date_mission, 3) = YEARWEEK(DATE_ADD(CURDATE(), INTERVAL 7 DAY), 3) ";
    } elseif ($date_view == 'month') {
        $date_filter_clause = " AND MONTH(m.date_mission) = MONTH(CURDATE()) AND YEAR(m.date_mission) = YEAR(CURDATE()) ";
    }
}

// --- CALENDRIER PANNES ---
$sql_pannes = "SELECT date_mission, COUNT(*) as nb_pannes 
               FROM mission 
               WHERE description LIKE '%RÉPARATION URGENTE%' 
               AND statut = 'a_faire'
               AND date_mission BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
               GROUP BY date_mission 
               ORDER BY date_mission ASC";
$pannes_du_mois = $pdo->query($sql_pannes)->fetchAll(PDO::FETCH_KEY_PAIR);

$start_date = new DateTime('today');
$calendar_data = [];
for ($i = 0; $i < 30; $i++) {
    $date_str = $start_date->format('Y-m-d');
    $nb_pannes = $pannes_du_mois[$date_str] ?? 0;
    $calendar_data[] = ['day' => $start_date->format('j'), 'day_name' => $start_date->format('D'), 'date_full' => $date_str, 'count' => $nb_pannes];
    $start_date->modify('+1 day');
}

// --- REQUÊTE PRINCIPALE ---
$voir_tout = (isset($_GET['view']) && $_GET['view'] == 'all');
$filtre_statut = $voir_tout ? "" : " AND m.statut = 'a_faire' ";
$techniciens = $pdo->query("SELECT t.id_technicien, u.nom, t.specialite FROM technicien t JOIN utilisateur u ON t.id_user = u.id_user")->fetchAll();
$commandes_list = $pdo->query("SELECT id_commande, cl.nom_societe FROM commande c JOIN client cl ON c.id_client = cl.id_client WHERE c.etat IN ('validee', 'devis') ORDER BY c.id_commande DESC")->fetchAll();

if ($_SESSION['role'] == 'technicien') {
    $sql_missions = "SELECT m.*, c.id_commande, cl.nom_societe as client_nom FROM mission m
                     JOIN affectation_tech at ON m.id_mission = at.id_mission
                     JOIN technicien t ON at.id_technicien = t.id_technicien
                     LEFT JOIN commande c ON m.id_commande = c.id_commande
                     LEFT JOIN client cl ON c.id_client = cl.id_client
                     WHERE t.id_user = ? $filtre_statut $date_filter_clause 
                     ORDER BY m.date_mission ASC";
    $stmt = $pdo->prepare($sql_missions);
    $stmt->execute([$_SESSION['user_id']]);
} else {
    $sql_missions = "SELECT m.*, u.nom as nom_tech, cl.nom_societe as client_nom 
                     FROM mission m
                     LEFT JOIN affectation_tech at ON m.id_mission = at.id_mission
                     LEFT JOIN technicien t ON at.id_technicien = t.id_technicien
                     LEFT JOIN utilisateur u ON t.id_user = u.id_user
                     LEFT JOIN commande c ON m.id_commande = c.id_commande
                     LEFT JOIN client cl ON c.id_client = cl.id_client
                     WHERE 1=1 $filtre_statut $date_filter_clause 
                     ORDER BY m.date_mission ASC";
    $stmt = $pdo->prepare($sql_missions);
    $stmt->execute();
}
$missions = $stmt->fetchAll();
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>Missions Logistiques</title>
</head>
<body>

<?= renderNavbar($_SESSION['role'], $current_page) ?>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <a href="dashboard.php" class="btn-back" style="margin:0;">⬅ Retour</a>
        <h1>🔧 Planning des Interventions</h1>
    </div>
    
    <div style="margin-bottom: 30px;">
        <h2 style="font-size:1.2em; color:var(--text-muted); border-bottom:1px solid var(--border-color); padding-bottom:5px;">📅 Pannes Urgentes (30 jours)</h2>
        <div style="display:flex; overflow-x:auto; gap:5px; padding:10px 0;">
            <?php foreach($calendar_data as $day): 
                $style = $day['count'] > 0 ? "background:var(--danger); color:white;" : "background:var(--input-bg); color:var(--text-muted); border:1px solid var(--border-color);";
            ?>
            <div style="flex: 0 0 auto; width: 50px; text-align:center; padding:8px 0; border-radius:2px; <?= $style ?>" title="<?= $day['date_full'] ?>">
                <div style="font-size:0.7em; text-transform:uppercase;"><?= $day['day_name'] ?></div>
                <div style="font-size:1.2em; font-weight:bold;"><?= $day['day'] ?></div>
                <?php if($day['count'] > 0): ?>
                    <div style="font-size:0.7em; margin-top:2px;">⚠️ <?= $day['count'] ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <form method="GET" style="margin-bottom:20px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <label style="font-weight:bold; margin-right:10px;">Filtrer :</label>
        <a href="missions.php?date_view=today" class="btn" style="padding:5px 10px; font-size:0.8em;">Aujourd'hui</a>
        <a href="missions.php?date_view=tomorrow" class="btn" style="padding:5px 10px; font-size:0.8em;">Demain</a>
        <a href="missions.php?date_view=week" class="btn" style="padding:5px 10px; font-size:0.8em;">Cette Semaine</a>
        <a href="missions.php?date_view=next_week" class="btn" style="padding:5px 10px; font-size:0.8em;">Semaine Pro.</a>
        <a href="missions.php?date_view=month" class="btn" style="padding:5px 10px; font-size:0.8em;">Ce Mois</a>
        <a href="missions.php" style="margin-left:auto; color:var(--text-muted); text-decoration:underline; font-size:0.9em;">Tout afficher</a>
    </form>

    <?php if($message) echo "<div style='background:var(--success); color:white; padding:10px; margin-bottom:15px;'>$message</div>"; ?>

    <?php if($_SESSION['role'] != 'technicien'): ?>
        <div class="box-creation" style="background:var(--input-bg); padding:20px; border:1px solid var(--border-color); margin-bottom:30px;">
            <h3 style="margin-top:0;">Assigner une nouvelle mission</h3>
            <form method="POST" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <div style="grid-column:span 2;">
                    <label>Type de mission :</label>
                    <select name="type" style="margin:0;">
                        <option value="livraison" <?= $pre_type == 'livraison' ? 'selected' : '' ?>>🚚 Livraison</option>
                        <option value="installation" <?= $pre_type == 'installation' ? 'selected' : '' ?>>🛠 Installation / Réparation</option>
                        <option value="recuperation" <?= $pre_type == 'recuperation' ? 'selected' : '' ?>>↩ Récupération</option>
                    </select>
                </div>

                <div>
                    <label>Lier à une Commande :</label>
                    <select name="id_commande" style="margin:0;">
                        <option value="">-- Non lié --</option>
                        <?php foreach($commandes_list as $cmd): ?>
                            <option value="<?= $cmd['id_commande'] ?>" <?= ($pre_cmd_id == $cmd['id_commande']) ? 'selected' : '' ?>>
                                #<?= $cmd['id_commande'] ?> - <?= htmlspecialchars($cmd['nom_societe']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Technicien :</label>
                    <select name="id_technicien" style="margin:0;">
                        <?php foreach($techniciens as $t): ?>
                            <option value="<?= $t['id_technicien'] ?>"><?= $t['nom'] ?> (<?= $t['specialite'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Date :</label>
                    <input type="date" name="date" required min="<?= date('Y-m-d') ?>" value="<?= $pre_date ?>" style="margin:0;">
                </div>

                <div style="grid-column:span 2;">
                    <label>Description :</label>
                    <textarea name="description" placeholder="Détails..." required style="height:80px; margin:0;"><?= $pre_desc ?></textarea>
                </div>

                <button type="submit" name="creer_mission" class="btn-add" style="grid-column:span 2;">Planifier la Mission</button>
            </form>
        </div>
    <?php endif; ?>

    <h3>Liste des missions</h3>
    
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th style="width:40%;">Description</th>
                <th>Client/Commande</th>
                <?php if($_SESSION['role'] != 'technicien') echo "<th>Technicien</th>"; ?>
                <th>Statut</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody> 
            <?php foreach($missions as $m): ?>
            <tr> 
                <td><strong><?= date('d/m/Y', strtotime($m['date_mission'])) ?></strong></td>
                <td><?= htmlspecialchars(ucfirst($m['type_mission'])) ?></td>
                <td><?= nl2br(htmlspecialchars($m['description'])) ?></td>
                
                <td>
                    <?php if($m['client_nom']): ?>
                        <strong><?= htmlspecialchars($m['client_nom']) ?></strong><br>
                        <a href="commande_details.php?id=<?= $m['id_commande'] ?>" style="font-size:0.8em;">Cmd #<?= $m['id_commande'] ?></a>
                    <?php else: ?>
                        <span style="color:var(--text-muted);">(Interne)</span>
                    <?php endif; ?>
                </td>
                
                <?php if($_SESSION['role'] != 'technicien') echo "<td>". ($m['nom_tech'] ?? 'Non Assigné') ."</td>"; ?>
                <td>
                    <?php if($m['statut'] == 'a_faire'): ?>
                        <span style="background:#E0E0E0; padding:2px 6px; border-radius:2px; font-size:0.8em;">À FAIRE</span>
                    <?php else: ?>
                        <span style="color:var(--success); font-weight:bold;">TERMINÉE</span>
                    <?php endif; ?>
                </td>

                <td>
                    <?php if($m['statut'] == 'a_faire'): ?>
                        <?php if(strpos($m['description'], 'RÉPARATION') !== false): ?>
                            <a href="maintenance.php" class="btn" style="background:var(--warning); padding:5px 10px; font-size:0.7em;">Atelier</a>
                        <?php else: ?>
                            <a href="?terminer_id=<?= $m['id_mission'] ?>" class="btn-validate" style="padding:5px 10px; font-size:0.7em; text-decoration:none; display:inline-block;">✔ Terminer</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody> 
    </table>
</div>

</body>
</html>