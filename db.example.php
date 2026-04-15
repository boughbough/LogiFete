<?php
// Fichier : db.example.php
// COPIEZ ce fichier en db.php et remplissez avec vos vraies informations

$host = 'sql300.infinityfree.com';        // Votre MySQL Hostname
$db   = 'VOTRE_BASE_DE_DONNEES';          // Nom de votre base
$user = 'VOTRE_UTILISATEUR_MYSQL';        // Votre utilisateur MySQL
$pass = 'VOTRE_MOT_DE_PASSE';             // Votre mot de passe MySQL

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

function ajouterLog($pdo, $action, $details) {
    if (isset($_SESSION['user_id'])) {
        $sql = "INSERT INTO historique_logs (id_user, action, details) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['user_id'], $action, $details]);
    }
}
?>