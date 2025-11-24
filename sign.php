<?php
// Fichier : sign.php
session_start();
require 'db.php';

if (!isset($_GET['id'])) die("ID manquant");
$id = $_GET['id'];

// Enregistrement
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['signature_data'])) {
    $sig = $_POST['signature_data'];
    $pdo->prepare("UPDATE commande SET signature_client = ? WHERE id_commande = ?")->execute([$sig, $id]);
    header("Location: commande_details.php?id=$id");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signature Client - Cmd #<?= $id ?></title>
    <style>
        body { font-family: sans-serif; background: #eee; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        canvas { background: white; border: 2px solid #333; border-radius: 8px; cursor: crosshair; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .btn-group { margin-top: 20px; display: flex; gap: 20px; }
        button { padding: 15px 30px; font-size: 1.2em; border: none; border-radius: 5px; cursor: pointer; }
        .btn-save { background: #27ae60; color: white; }
        .btn-clear { background: #c0392b; color: white; }
        h2 { color: #333; margin-bottom: 10px; }
    </style>
</head>
<body>

    <h2>✍️ Signature Client - Commande #<?= $id ?></h2>
    <p>Veuillez signer dans la case ci-dessous :</p>

    <canvas id="sig-canvas" width="500" height="300"></canvas>

    <form method="POST" id="sig-form">
        <input type="hidden" name="signature_data" id="sig-data">
        <div class="btn-group">
            <button type="button" class="btn-clear" id="sig-clear">Effacer</button>
            <button type="submit" class="btn-save" id="sig-submit">✅ Valider la signature</button>
        </div>
    </form>
    <br>
    <a href="commande_details.php?id=<?= $id ?>" style="color:#777;">Annuler</a>

    <script>
        var canvas = document.getElementById("sig-canvas");
        var ctx = canvas.getContext("2d");
        var drawing = false;

        // Souris
        canvas.addEventListener("mousedown", function(e) { drawing = true; ctx.moveTo(getPos(e).x, getPos(e).y); ctx.beginPath(); });
        canvas.addEventListener("mouseup", function() { drawing = false; });
        canvas.addEventListener("mousemove", function(e) { if (!drawing) return; ctx.lineWidth = 3; ctx.lineCap = "round"; ctx.lineTo(getPos(e).x, getPos(e).y); ctx.stroke(); });

        // Tactile (Tablette/Mobile)
        canvas.addEventListener("touchstart", function(e) { e.preventDefault(); drawing = true; var touch = e.touches[0]; ctx.moveTo(getPos(touch).x, getPos(touch).y); ctx.beginPath(); }, {passive: false});
        canvas.addEventListener("touchend", function(e) { e.preventDefault(); drawing = false; }, {passive: false});
        canvas.addEventListener("touchmove", function(e) { e.preventDefault(); if (!drawing) return; var touch = e.touches[0]; ctx.lineWidth = 3; ctx.lineCap = "round"; ctx.lineTo(getPos(touch).x, getPos(touch).y); ctx.stroke(); }, {passive: false});

        function getPos(evt) {
            var rect = canvas.getBoundingClientRect();
            return { x: evt.clientX - rect.left, y: evt.clientY - rect.top };
        }

        document.getElementById("sig-clear").addEventListener("click", function() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        });

        document.getElementById("sig-submit").addEventListener("click", function(e) {
            var dataUrl = canvas.toDataURL();
            document.getElementById("sig-data").value = dataUrl;
        });
    </script>

</body>
</html>