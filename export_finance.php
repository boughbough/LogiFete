<?php
// Fichier : export_finance.php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'technicien') { exit; }

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=livre_ventes_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
fputs($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM Excel

// En-têtes
fputcsv($output, array('Facture', 'Date Facture', 'Client', 'Total HT', 'TVA (20%)', 'Pénalités', 'Total TTC', 'Statut Paiement', 'Date Paiement'), ';');

// Requête Globale
$sql = "SELECT c.*, cl.nom_societe, 
        (SELECT SUM(r.prix_jour) FROM reservation_equipement re JOIN reference_materiel r ON re.num_serie = (SELECT num_serie FROM equipement_physique WHERE num_serie = re.num_serie LIMIT 1) WHERE re.id_commande = c.id_commande) as valeur_jour
        FROM commande c 
        JOIN client cl ON c.id_client = cl.id_client 
        WHERE c.etat IN ('validee', 'terminee')
        ORDER BY c.date_creation DESC";

$commandes = $pdo->query($sql)->fetchAll();

foreach ($commandes as $c) {
    // Calculs (identiques à facture.php)
    $ts_debut = strtotime($c['date_debut']);
    $ts_fin = strtotime($c['date_fin']);
    $duree = max(1, ($ts_fin - $ts_debut) / 86400 + 1);
    
    $total_ht = $c['valeur_jour'] * $duree;
    // Remise
    $total_ht = $total_ht * (1 - ($c['remise_percent']/100));
    
    $tva = $total_ht * 0.20;
    $penalite = $c['penalite'];
    $ttc = $total_ht + $tva + $penalite;
    
    $statut = $c['date_paiement'] ? 'PAYÉ' : 'EN ATTENTE';
    
    // Écriture ligne CSV
    fputcsv($output, array(
        'F-2025-' . str_pad($c['id_commande'], 4, '0', STR_PAD_LEFT),
        date('d/m/Y', strtotime($c['date_creation'])),
        $c['nom_societe'],
        str_replace('.', ',', number_format($total_ht, 2)),
        str_replace('.', ',', number_format($tva, 2)),
        str_replace('.', ',', number_format($penalite, 2)),
        str_replace('.', ',', number_format($ttc, 2)),
        $statut,
        $c['date_paiement'] ? date('d/m/Y', strtotime($c['date_paiement'])) : '-'
    ), ';');
}

fclose($output);
exit;
?>