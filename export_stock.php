<?php
// Fichier : export_stock.php
require 'db.php';
session_start();

// Sécurité
if (!isset($_SESSION['user_id'])) { exit; }

// 1. On configure les headers pour dire au navigateur "C'est un fichier CSV à télécharger"
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=inventaire_logifete_' . date('Y-m-d') . '.csv');

// 2. On ouvre la "sortie" (output) du fichier
$output = fopen('php://output', 'w');

// 3. On ajoute l'en-tête des colonnes (pour Excel)
// Astuce : chr(0xEF).chr(0xBB).chr(0xBF) permet à Excel de bien lire les accents (UTF-8 BOM)
fputs($output, chr(0xEF).chr(0xBB).chr(0xBF));
fputcsv($output, array('Numéro de Série', 'Matériel', 'Description', 'Prix Jour (€)', 'Statut Actuel'), ';');

// 4. On récupère les données
$sql = "SELECT e.num_serie, r.libelle, r.description, r.prix_jour, e.statut 
        FROM equipement_physique e
        JOIN reference_materiel r ON e.id_reference = r.id_reference
        ORDER BY r.libelle";
$stmt = $pdo->query($sql);

// 5. On boucle pour écrire chaque ligne
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, array(
        $row['num_serie'], 
        $row['libelle'], 
        $row['description'], 
        str_replace('.', ',', $row['prix_jour']), // Format français pour le prix
        strtoupper($row['statut'])
    ), ';');
}

fclose($output);
exit;
?>