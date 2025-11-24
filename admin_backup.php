<?php
// Fichier : admin_backup.php
session_start();
require 'db.php';

// Sécurité : ADMIN SEULEMENT
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Configuration du téléchargement
$filename = 'backup_logifete_' . date('Y-m-d_H-i') . '.sql';
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Liste des tables à sauvegarder
$tables = ['utilisateur', 'client', 'fournisseur', 'reference_materiel', 'equipement_physique', 'commande', 'reservation_equipement', 'mission', 'affectation_tech', 'kit', 'kit_contenu', 'historique_logs'];

echo "-- SAUVEGARDE AUTOMATIQUE PGI LOGIFÊTE\n";
echo "-- Date : " . date('d/m/Y H:i:s') . "\n\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    // 1. Structure
    $row = $pdo->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM);
    echo "-- Structure de la table `$table`\n";
    echo $row[1] . ";\n\n";

    // 2. Données
    $rows = $pdo->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) > 0) {
        echo "-- Données de la table `$table`\n";
        foreach ($rows as $row) {
            $fields = array_map(function($val) use ($pdo) {
                if ($val === null) return "NULL";
                return $pdo->quote($val);
            }, array_values($row));
            
            echo "INSERT INTO `$table` VALUES (" . implode(", ", $fields) . ");\n";
        }
        echo "\n";
    }
}

echo "SET FOREIGN_KEY_CHECKS=1;\n";
exit;
?>