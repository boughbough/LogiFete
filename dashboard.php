<?php
// Fichier : dashboard.php
session_start();
require 'db.php';
require 'navbar.php'; // Inclusion de la fonction de menu factorisée

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

// Gestion des Messages Flash (Toast)
$flash_msg = "";
$flash_cmd_id = "";
if (isset($_SESSION['flash_message'])) {
    $flash_msg = $_SESSION['flash_message'];
    $flash_cmd_id = $_SESSION['flash_cmd_id'] ?? '';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_cmd_id']);
}

// --- REQUÊTES KPI (Indicateurs Clés) ---
$nb_stock = $pdo->query("SELECT COUNT(*) FROM equipement_physique")->fetchColumn();
$nb_commandes = $pdo->query("SELECT COUNT(*) FROM commande WHERE etat = 'validee'")->fetchColumn();
$nb_missions = $pdo->query("SELECT COUNT(*) FROM mission WHERE statut = 'a_faire'")->fetchColumn();
$nb_retards = $pdo->query("SELECT COUNT(*) FROM commande WHERE etat = 'validee' AND date_fin < CURDATE()")->fetchColumn();
$nb_garantie_expiree = $pdo->query("SELECT COUNT(*) FROM equipement_physique WHERE fin_garantie < CURDATE() AND fin_garantie IS NOT NULL")->fetchColumn();
$nb_pannes = $pdo->query("SELECT COUNT(*) FROM equipement_physique WHERE statut = 'panne'")->fetchColumn();

// Données pour le Graphique Camembert (Stock)
$sql_loue = "SELECT COUNT(DISTINCT re.num_serie) FROM reservation_equipement re JOIN commande c ON re.id_commande = c.id_commande JOIN equipement_physique e ON re.num_serie = e.num_serie WHERE c.etat = 'validee' AND CURDATE() BETWEEN c.date_debut AND c.date_fin AND e.statut = 'disponible'"; 
$nb_loue = $pdo->query($sql_loue)->fetchColumn();
$nb_dispo = $nb_stock - $nb_pannes - $nb_loue;
if ($nb_dispo < 0) $nb_dispo = 0;

// Données pour le Graphique Barres (Ventes 6 mois)
$ventes_data = []; $ventes_labels = [];
for ($i = 5; $i >= 0; $i--) {
    $ventes_data[] = $pdo->query("SELECT COUNT(*) FROM commande WHERE date_creation BETWEEN '" . date('Y-m-01', strtotime("-$i months")) . "' AND '" . date('Y-m-t', strtotime("-$i months")) . "'")->fetchColumn();
    $ventes_labels[] = date('M', strtotime("-$i months"));
}
$js_labels = json_encode($ventes_labels);
$js_data = json_encode($ventes_data);

// Requête Alertes Stock Bas
$sql_alert = "SELECT r.libelle, r.seuil_alerte, (SELECT COUNT(*) FROM equipement_physique e WHERE e.id_reference = r.id_reference AND e.statut = 'disponible') as dispo FROM reference_materiel r HAVING dispo <= r.seuil_alerte";
$alertes = $pdo->query($sql_alert)->fetchAll();

// Définition de la page courante pour le menu actif
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="img/logo.png">

    <meta charset="UTF-8">
    <title>Tableau de Bord - LogiFête</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script> 
    <style>
        /* --- STYLES SPÉCIFIQUES DASHBOARD --- */

        /* 1. KPI ULTRA MINIMALISTE (Texte pur) */
        .kpi-grid { 
            display: flex; 
            gap: 20px; 
            margin-bottom: 40px; 
            flex-wrap: wrap; 
            border-bottom: 1px solid var(--border-color); /* Séparation avec le reste */
            padding-bottom: 30px;
        }
        
        .kpi-card { 
            background: transparent;
            padding: 0;
            flex: 1; 
            min-width: 120px; 
            border: none;
            display: flex;
            flex-direction: column; 
            border-right: 1px solid #E0E0E0; /* Séparateur vertical fin */
            padding-left: 20px;
        }
        
        .kpi-card:first-child { padding-left: 0; }
        .kpi-card:last-child { border-right: none; }
        
        .kpi-value { 
            font-family: 'Lato', sans-serif;
            font-size: 3em; 
            font-weight: 300; /* Light */
            color: var(--primary); 
            margin-bottom: 5px;
            line-height: 1;
        }
        
        .kpi-label { 
            color: var(--text-muted); 
            font-size: 0.8em; 
            text-transform: uppercase; 
            letter-spacing: 2px;
            font-weight: 700;
        }

        /* 2. GRAPHIQUES */
        .charts-wrapper { display: flex; gap: 30px; margin-bottom: 30px; flex-wrap: wrap; }
        .chart-container { 
            background: var(--bg-card); 
            padding: 30px; 
            border-radius: 2px; 
            flex: 1; 
            min-width: 300px; 
            box-shadow: var(--shadow); 
            border: 1px solid var(--border-color);
        }
        
        /* 3. NOUVELLES ALERTES (Minimal & Chic) */
        .alert-wrapper {
            margin-bottom: 40px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .alert-card {
            background-color: var(--bg-card);
            border-radius: 2px;
            padding: 20px;
            box-shadow: var(--shadow);
            border-left-width: 4px;
            border-left-style: solid;
            display: flex;
            align-items: flex-start;
        }

        .alert-card.danger { border-left-color: var(--danger); }
        .alert-card.warning { border-left-color: var(--warning); }

        .alert-icon {
            font-size: 1.5em;
            margin-right: 20px;
            line-height: 1;
            opacity: 0.8;
        }

        .alert-content h4 {
            margin: 0 0 10px 0;
            font-size: 1em;
            color: var(--primary);
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: none;
        }

        /* Étiquettes Matériel (Chips) */
        .stock-tag {
            display: inline-block;
            background-color: #F5F5F5;
            color: var(--text-main);
            padding: 5px 10px;
            border-radius: 2px;
            font-size: 0.85em;
            margin-right: 8px;
            margin-bottom: 6px;
            border: 1px solid var(--border-color);
            font-family: 'Lato', sans-serif;
        }

        .stock-tag strong { color: var(--danger); }
    </style>
</head>
<body>

    <?= renderNavbar($_SESSION['role'], $current_page) ?>

    <div class="container">
        
        <?php if(count($alertes) > 0 || $nb_garantie_expiree > 0): ?>
        <div class="alert-wrapper">
            
            <?php if(count($alertes) > 0): ?>
            <div class="alert-card danger">
                <div class="alert-icon">⚠️</div>
                <div class="alert-content">
                    <h4>Stock Critique</h4>
                    <div>
                        <?php foreach($alertes as $a): ?>
                            <span class="stock-tag">
                                <?= htmlspecialchars($a['libelle']) ?> 
                                <strong>(Reste : <?= $a['dispo'] ?>)</strong>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if($nb_garantie_expiree > 0): ?>
            <div class="alert-card warning">
                <div class="alert-icon">🛡️</div>
                <div class="alert-content">
                    <h4>Fin de Garantie</h4>
                    <p style="margin: 0; font-size: 0.95em; color: var(--text-muted);">
                        Attention, <strong><?= $nb_garantie_expiree ?></strong> équipements ne sont plus couverts par la garantie fournisseur.
                    </p>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <?php endif; ?>
        
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-value"><?= $nb_stock ?></div>
                <div class="kpi-label">Stock</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value"><?= $nb_commandes ?></div>
                <div class="kpi-label">Commandes</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" style="<?= $nb_pannes > 0 ? 'color:var(--danger);' : '' ?>"><?= $nb_pannes ?></div>
                <div class="kpi-label">Pannes</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value"><?= $nb_missions ?></div>
                <div class="kpi-label">Missions</div>
            </div>
        </div>

        <div class="charts-wrapper">
            <div class="chart-container">
                <h3>État du Parc</h3>
                <div style="height:250px;"><canvas id="stockChart"></canvas></div>
            </div>
            <div class="chart-container">
                <h3>Activité Commerciale</h3>
                <div style="height:250px;"><canvas id="salesChart"></canvas></div>
            </div>
        </div>
        
    </div>

    <div id="toast-container" class="toast-container">
        <?php if($flash_msg): ?>
            <div id="flash-toast" class="toast">
                <?= $flash_msg ?>
                <?php if($flash_cmd_id): ?>
                    <a href="commande_details.php?id=<?= $flash_cmd_id ?>" class="btn" style="background:white; color:#2ecc71; padding:2px 8px; margin-left:10px; text-decoration:none; font-size:0.8em;">Voir</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Graphique 1 : Camembert Stock
        new Chart(document.getElementById('stockChart'), {
            type: 'doughnut',
            data: { 
                labels: ['Dispo', 'Loué', 'HS'], 
                datasets: [{ 
                    data: [<?= $nb_dispo ?>, <?= $nb_loue ?>, <?= $nb_pannes ?>], 
                    // PALETTE DE COULEURS CHIC :
                    backgroundColor: [
                        '#556B2F', // Vert Olive (Dispo)
                        '#A68B5B', // Bronze Doré (Loué)
                        '#8B3A3A'  // Rouge Brique (HS)
                    ], 
                    borderWidth: 0, 
                    hoverOffset: 4 
                }] 
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { 
                    legend: { 
                        position: 'right',
                        labels: {
                            font: { family: "'Lato', sans-serif", size: 12 },
                            color: '#2C3E50',
                            usePointStyle: true, 
                            padding: 20
                        }
                    } 
                },
                cutout: '75%' // Anneau très fin
            }
        });

        // Graphique 2 : Histogramme Ventes
        new Chart(document.getElementById('salesChart'), {
            type: 'bar',
            data: { 
                labels: <?= $js_labels ?>, 
                datasets: [{ 
                    label: 'Ventes', 
                    data: <?= $js_data ?>, 
                    backgroundColor: '#2C3E50', // Gris Anthracite
                    borderRadius: 2, 
                    barPercentage: 0.5 
                }] 
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                scales: { 
                    y: { 
                        beginAtZero: true, 
                        ticks: { stepSize: 1, font: { family: "'Lato'" } },
                        grid: { color: '#f0f0f0' } 
                    },
                    x: {
                        grid: { display: false }, 
                        ticks: { font: { family: "'Lato'" } }
                    }
                }, 
                plugins: { legend: { display: false } } 
            }
        });
        
        // Animation Toast
        const toast = document.getElementById('flash-toast');
        if (toast) {
            setTimeout(() => { toast.classList.add('show'); }, 100);
            setTimeout(() => { 
                toast.classList.remove('show');
                setTimeout(() => { toast.remove(); }, 500); 
            }, 4000); 
        }
    </script>
</body>
</html>