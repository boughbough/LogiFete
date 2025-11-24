<?php
// Fichier : nouvelle_commande.php
session_start();
require 'db.php';
require 'navbar.php';
// Sécurité
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'commercial' && $_SESSION['role'] != 'admin')) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_client = $_POST['id_client'];
    $date_debut = $_POST['date_debut'];
    $date_fin = $_POST['date_fin'];
    $id_commercial = $_SESSION['user_id'];

    if ($date_fin < $date_debut) {
        // Cette erreur est désormais gérée par le JS client
        $error = "La date de fin ne peut pas être avant la date de début !";
    } else {
        $sql = "INSERT INTO commande (id_client, id_commercial, date_debut, date_fin, etat) VALUES (:id_client, :id_commercial, :date_debut, :date_fin, 'devis')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id_client' => $id_client, 'id_commercial' => $id_commercial, 'date_debut' => $date_debut, 'date_fin' => $date_fin]);

        $nouvel_id = $pdo->lastInsertId();
        
        // LOG
        ajouterLog($pdo, "CREATION DEVIS", "Ouverture du dossier commercial #$nouvel_id");

        header("Location: commande_details.php?id=" . $nouvel_id);
        exit;
    }
}

$clients = $pdo->query("SELECT * FROM client ORDER BY nom_societe")->fetchAll();
$current_page = basename($_SERVER['PHP_SELF']);?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>Nouvelle Commande - LogiFête</title>
</head>
<body>
    <?= renderNavbar($_SESSION['role'], $current_page) ?>
<div class="container">
    <h1>📝 Créer un Devis</h1>
    
    <?php if(isset($error)) echo "<p class='error' style='color:red;'>$error</p>"; ?>

    <form method="POST">
        <label>Client :</label>
        <select name="id_client" required>
            <option value="">-- Sélectionner un client --</option>
            <?php foreach($clients as $c): ?>
                <option value="<?= $c['id_client'] ?>">
                    <?= htmlspecialchars($c['nom_societe']) ?> (<?= htmlspecialchars($c['contact_nom']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label>Date de Début (Livraison) :</label>
        <input type="date" name="date_debut" id="date_debut" required min="<?= date('Y-m-d') ?>">

        <label>Date de Fin (Retour) :</label>
        <input type="date" name="date_fin" id="date_fin" required min="<?= date('Y-m-d') ?>">

        <button type="submit">Suivant : Ajouter du matériel ➡</button>
    </form>

    <a href="dashboard.php" class="btn-back">Annuler</a>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const dateDebut = document.getElementById('date_debut');
        const dateFin = document.getElementById('date_fin');

        // Met à jour la date minimum de fin dès que la date de début change
        dateDebut.addEventListener('change', function() {
            if (this.value) {
                dateFin.min = this.value;
                
                // Si la date de fin est antérieure à la nouvelle date de début, on la réinitialise
                if (dateFin.value && dateFin.value < this.value) {
                    dateFin.value = this.value;
                }
            }
        });
        
        // Assurance que la date de fin est au moins la date de début
        dateFin.addEventListener('change', function() {
            if (dateDebut.value && this.value < dateDebut.value) {
                alert("La date de fin ne peut pas être avant la date de début.");
                this.value = dateDebut.value;
            }
        });
    });
</script>

</body>
</html>