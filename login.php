<?php
// Fichier : login.php
session_start();
require 'db.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM utilisateur WHERE email = :email";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['mot_de_passe'])) {
        $_SESSION['user_id'] = $user['id_user'];
        $_SESSION['nom'] = $user['nom'];
        $_SESSION['role'] = $user['role'];
        
        if(function_exists('ajouterLog')) ajouterLog($pdo, "CONNEXION", "L'utilisateur s'est connecté.");
        
        header("Location: dashboard.php");
        exit;
    } else {
        $message = "Identifiants incorrects.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>Connexion - LogiFête</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* --- CORRECTIF SPÉCIFIQUE LOGIN --- */
        /* On annule l'espace réservé pour la navbar car il n'y en a pas ici */
        body {
            padding-top: 0 !important;
            margin: 0;
        }

        /* Ajustement spécifique pour centrer le lien retour */
        .login-footer {
            margin-top: 20px;
            border-top: 1px solid var(--border-color);
            padding-top: 15px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-box">
        <h1 style="border:none; font-size: 2.5em; margin-bottom: 10px;">LogiFête</h1>
        <p style="color:var(--text-muted); margin-bottom: 30px; letter-spacing: 1px; text-transform: uppercase; font-size: 0.8em;">
            Espace Sécurisé
        </p>

        <?php if($message): ?>
            <div style="background:var(--danger); color:white; padding:10px; margin-bottom:20px; border-radius:2px;">
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div style="text-align: left;">
                <label>Identifiant</label>
                <input type="email" name="email" required placeholder="email@logifete.com">
                
                <label>Mot de passe</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            
            <button type="submit" class="btn-add" style="width: 100%; margin-top: 10px;">Se connecter</button>
        </form>

        <div class="login-footer">
            <a href="index.php" class="btn-back" style="margin:0;">← Retour au site</a>
        </div>
    </div>
</div>

</body>
</html>