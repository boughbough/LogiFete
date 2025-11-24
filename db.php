<?php
// Fichier : db.php (Avec fonction de Log)

$host = 'sql100.infinityfree.com';
$db   = 'if0_40491947_logifete';
$user = 'if0_40491947';
$pass = 'Xw1GDQFHyjYD';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// --- FONCTION GLOBALE D'AUDIT ---
// À appeler à chaque action importante : ajouterLog($pdo, "TYPE", "Détails...");
function ajouterLog($pdo, $action, $details) {
    if (isset($_SESSION['user_id'])) {
        $sql = "INSERT INTO historique_logs (id_user, action, details) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['user_id'], $action, $details]);
    }
}
?>