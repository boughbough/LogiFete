<?php
// Fichier : planning.php
session_start();
require 'db.php';
require 'navbar.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

// On récupère les commandes pour le calendrier (Format JSON)
$sql = "SELECT c.id_commande, c.date_debut, c.date_fin, c.etat, cl.nom_societe 
        FROM commande c 
        JOIN client cl ON c.id_client = cl.id_client 
        WHERE c.etat != 'annulee'";
$commandes = $pdo->query($sql)->fetchAll();

$events = [];
foreach($commandes as $c) {
    // HARMONISATION DES COULEURS AVEC LE THÈME "CHIC"
    $color = '#D4AC0D'; // Devis (Moutarde/Doré) - var(--warning)
    $textColor = '#FFFFFF';

    if ($c['etat'] == 'validee') {
        $color = '#556B2F'; // Validée (Vert Olive) - var(--success)
    }
    if ($c['etat'] == 'terminee') {
        $color = '#7F8C8D'; // Terminée (Gris Souris) - var(--text-muted)
    }
    
    // On ajoute 1 jour à la date de fin car FullCalendar s'arrête à minuit le jour même
    $end_date = date('Y-m-d', strtotime($c['date_fin'] . ' +1 day'));

    $events[] = [
        'title' => "CMD #" . $c['id_commande'] . " - " . $c['nom_societe'],
        'start' => $c['date_debut'],
        'end'   => $end_date,
        'color' => $color,
        'textColor' => $textColor,
        'url'   => "commande_details.php?id=" . $c['id_commande']
    ];
}
$json_events = json_encode($events);
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>Planning des Locations</title>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    <style>
        /* Style adapté au thème Chic */
        #calendar { 
            max-width: 1100px; 
            margin: 0 auto; 
            padding: 10px; 
        }
        
        /* Personnalisation FullCalendar pour le style "Papier" */
        .fc-toolbar-title {
            font-family: 'Playfair Display', serif !important;
            color: var(--primary);
        }
        .fc-button-primary {
            background-color: var(--primary) !important;
            border-color: var(--primary) !important;
            font-family: 'Lato', sans-serif !important;
            text-transform: uppercase;
            font-size: 0.8em !important;
            letter-spacing: 1px;
        }
        .fc-button-primary:hover {
            background-color: #000 !important;
            border-color: #000 !important;
        }
        .fc-event { 
            cursor: pointer; 
            border: none; 
            border-radius: 2px; 
            font-family: 'Lato', sans-serif;
            font-size: 0.85em;
            padding: 2px 4px;
        }
    </style>
</head>
<body>

<?= renderNavbar($_SESSION['role'], $current_page) ?>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
        <h1 style="margin:0; border:none;">📅 Planning des Réservations</h1>
        
        <div style="display:flex; gap: 10px;">
            <span class="badge" style="background:#D4AC0D; color:white;">Devis</span>
            <span class="badge" style="background:#556B2F; color:white;">Validée</span>
            <span class="badge" style="background:#7F8C8D; color:white;">Terminée</span>
        </div>
    </div>

    <div id='calendar'></div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'fr',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek'
            },
            buttonText: {
                today: "Aujourd'hui",
                month: 'Mois',
                week: 'Semaine'
            },
            events: <?= $json_events ?>,
            eventClick: function(info) {
                // Ouverture dans un nouvel onglet si souhaité, sinon comportement par défaut
            }
        });
        calendar.render();
    });
</script>

</body>
</html>