<?php
// Fichier : profil.php (Version Finale : Profil + Signature Interne)
session_start();
require 'db.php';
require 'navbar.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$msg = "";
$user_id = $_SESSION['user_id'];

// TRAITEMENT : MISE À JOUR
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_nom = $_POST['nom'];
    $new_pass = $_POST['password'];
    $sig_data = $_POST['signature_data']; // Nouvelle donnée
    
    // Construction de la requête UPDATE
    $sql = "UPDATE utilisateur SET nom = ?";
    $params = [$new_nom];

    if (!empty($new_pass)) {
        $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT); // Hachage du nouveau mot de passe
        $sql .= ", mot_de_passe = ?";
        $params[] = $hashed_pass; // Utiliser le hachage
    }
    if (!empty($sig_data)) {
        $sql .= ", signature_data = ?";
        $params[] = $sig_data;
    }
    
    $sql .= " WHERE id_user = ?";
    $params[] = $user_id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Mise à jour session & Log
    $_SESSION['nom'] = $new_nom;
    ajouterLog($pdo, "MODIFICATION PROFIL", "L'utilisateur a modifié ses infos et/ou sa signature.");

    $msg = "✅ Profil et signature mis à jour !";
}

// Récupération infos actuelles
$user = $pdo->query("SELECT * FROM utilisateur WHERE id_user = $user_id")->fetch();
$current_page = basename($_SERVER['PHP_SELF']);?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>Mon Profil</title>
    <style>
        /* Styles pour le Pad de Signature */
        #sig-canvas { background: white; border: 1px solid #ccc; border-radius: 4px; cursor: crosshair; }
        .sig-block { margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 8px; }
        .btn-sig-group { display: flex; gap: 10px; margin-top: 10px; }
    </style>
</head>
<body>

    <?= renderNavbar($_SESSION['role'], $current_page) ?>
<div class="container" style="max-width:700px;">
    <h1>👤 Mon Profil</h1>
    
    <?php if($msg) echo "<div style='background:#d4edda; color:#155724; padding:10px; border-radius:5px; margin-bottom:20px;'>$msg</div>"; ?>

    <div style="background:#fff; padding:30px; border-radius:8px; border:1px solid #ddd;">
        <form method="POST">
            <label>Votre Rôle :</label>
            <input type="text" value="<?= strtoupper($user['role']) ?>" disabled style="background:#eee; color:#555;">
            
            <label>Email (Login) :</label>
            <input type="text" value="<?= $user['email'] ?>" disabled style="background:#eee; color:#555;">
            
            <label>Nom complet :</label>
            <input type="text" name="nom" value="<?= htmlspecialchars($user['nom']) ?>" required>
            
            <label>Nouveau mot de passe (Laisser vide si inchangé) :</label>
            <input type="password" name="password" placeholder="******">

            <div class="sig-block">
                <label>Signature pour validation des documents :</label>
                <p style="margin-top:5px; font-size:0.9em; color:#777;">Dessinez votre signature ci-dessous. Elle sera utilisée sur les Bons de Livraison/Retour.</p>

                <canvas id="sig-canvas" width="400" height="150"></canvas>
                <div class="btn-sig-group">
                    <button type="button" class="btn" id="sig-clear" style="background:#c0392b; font-size:0.9em;">Effacer</button>
                    
                    <?php if ($user['signature_data']): ?>
                        <div style="margin-top:10px;">
                            <img src="<?= $user['signature_data'] ?>" style="max-width:100px; max-height:30px; border:1px dashed #777;">
                            <small style="color:green;">(Signature actuelle)</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <input type="hidden" name="signature_data" id="sig-data">
            <button type="submit" class="btn-add" style="width:100%; margin-top:20px;" id="final-submit">💾 Enregistrer tout</button>
        </form>
    </div>
</div>

<script>
// --- LOGIQUE CANVAS REPRISE DE sign.php ---
var canvas = document.getElementById("sig-canvas");
var ctx = canvas.getContext("2d");
var drawing = false;

// Configurer le contexte
ctx.strokeStyle = '#000000'; 
ctx.lineWidth = 3; 
ctx.lineCap = "round";

// Initialisation si signature existante
if ("<?= $user['signature_data'] ?>" && !canvas.getAttribute('data-clear')) {
    var img = new Image();
    img.onload = function() {
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
    };
    img.src = "<?= $user['signature_data'] ?>";
}

canvas.addEventListener("mousedown", startDrawing);
canvas.addEventListener("mouseup", stopDrawing);
canvas.addEventListener("mousemove", draw);
canvas.addEventListener("touchstart", startDrawing, {passive: false});
canvas.addEventListener("touchend", stopDrawing, {passive: false});
canvas.addEventListener("touchmove", draw, {passive: false});

function startDrawing(e) { 
    e.preventDefault(); 
    drawing = true; 
    ctx.beginPath(); 
    ctx.moveTo(getPos(e).x, getPos(e).y); 
}
function stopDrawing() { drawing = false; }
function draw(e) { 
    e.preventDefault(); 
    if (!drawing) return; 
    ctx.lineTo(getPos(e).x, getPos(e).y); 
    ctx.stroke(); 
}
function getPos(evt) {
    var rect = canvas.getBoundingClientRect();
    var clientX = evt.clientX || evt.touches[0].clientX;
    var clientY = evt.clientY || evt.touches[0].clientY;
    return { x: clientX - rect.left, y: clientY - rect.top };
}

document.getElementById("sig-clear").addEventListener("click", function() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    // On efface la signature existante lors de la soumission si le pad est vidé
    canvas.setAttribute('data-clear', 'true');
});

document.getElementById("final-submit").addEventListener("click", function(e) {
    // Si le pad n'est pas vide (ou s'il a été dessiné), on prend la nouvelle signature
    if (canvas.getAttribute('data-clear') === 'true' || isCanvasClear(canvas)) {
        // Si vide et qu'il y avait une signature avant, on n'envoie rien pour garder l'ancienne
        // Sauf si l'utilisateur a vraiment effacé pour forcer la suppression (trop complexe)
        // Simplification: On envoie la DataURL vide s'il a cliqué Effacer
        document.getElementById("sig-data").value = ''; // Envoie chaîne vide si effacé
    } else {
        // Sinon, on prend ce qui est dessiné (ou l'ancienne si non touché)
        document.getElementById("sig-data").value = canvas.toDataURL();
    }
});

// Helper pour vérifier si le canvas est vide (simple check)
function isCanvasClear(canvas) {
    return !ctx.getImageData(0, 0, canvas.width, canvas.height).data.some(channel => channel !== 0);
}
</script>

</body>
</html>