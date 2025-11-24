<?php
require 'db.php'; 

// Vérification système discrète
$db_status = "Connexion établie";
try {
    $req = $pdo->query("SELECT count(*) as total FROM utilisateur");
    $resultat = $req->fetch();
    $user_count = $resultat['total'];
} catch (Exception $e) {
    $db_status = "Erreur Système";
    $user_count = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="img/logo.png">
    <meta charset="UTF-8">
    <title>LogiFête - Solution de Gestion Événementielle</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Styles spécifiques à la Landing Page */
        body {
            padding-top: 0; /* Pas de navbar fixe ici */
            display: block; /* Reset du flex si présent */
            background-color: var(--bg-page);
            overflow-x: hidden; /* Évite les scrollbars pendant les animations */
        }

        /* --- ANIMATIONS --- */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes floatIcon {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        /* HERO SECTION */
        .hero {
            text-align: center;
            padding: 120px 20px 140px 20px; /* Un peu plus d'espace en bas pour les cartes */
            background-color: var(--primary); /* Fond Sombre */
            color: white;
            position: relative;
            /* Animation d'entrée */
            animation: fadeInUp 1s ease-out;
        }
        
        .hero h1 {
            color: white;
            border-bottom: none;
            font-size: 3.8em;
            margin-bottom: 15px;
            font-family: 'Playfair Display', serif;
        }
        
        .hero p {
            font-size: 1.3em;
            color: #dcdcdc;
            max-width: 700px;
            margin: 0 auto 50px auto;
            font-family: 'Lato', sans-serif;
            font-weight: 300;
            line-height: 1.5;
        }

        .hero-btn {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .hero-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
        }

        /* FEATURES GRID */
        .features {
            display: flex;
            justify-content: center;
            gap: 30px;
            max-width: 1100px;
            margin: -70px auto 50px auto; /* Remonte sur le Hero */
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }

        .feature-card {
            background: var(--bg-card);
            padding: 50px 30px;
            flex: 1;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            text-align: center;
            border-top: 5px solid var(--accent); /* Touche dorée */
            border-radius: 2px;
            
            /* Animation & Transition */
            opacity: 0; /* Caché au départ */
            animation: fadeInUp 0.8s ease-out forwards; /* Forwards garde l'état final */
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.4s ease;
        }

        /* Décalage des animations pour l'effet cascade */
        .feature-card:nth-child(1) { animation-delay: 0.3s; }
        .feature-card:nth-child(2) { animation-delay: 0.5s; }
        .feature-card:nth-child(3) { animation-delay: 0.7s; }

        /* EFFET DE SURVOL (LE FLOTTEMENT) */
        .feature-card:hover {
            transform: translateY(-15px); /* Monte vers le haut */
            box-shadow: 0 25px 50px rgba(0,0,0,0.15); /* L'ombre s'éloigne */
        }

        .feature-icon {
            font-size: 3.5em;
            margin-bottom: 25px;
            display: block;
            /* Petite animation continue */
            animation: floatIcon 3s ease-in-out infinite;
        }
        
        /* On décale un peu le flottement des icônes pour pas qu'ils bougent tous en même temps */
        .feature-card:nth-child(2) .feature-icon { animation-delay: 1s; }
        .feature-card:nth-child(3) .feature-icon { animation-delay: 2s; }

        .feature-card h3 {
            font-size: 1.4em;
            margin-bottom: 15px;
            color: var(--primary);
        }

        /* FOOTER / STATUS */
        .footer {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
            font-size: 0.9em;
            border-top: 1px solid rgba(0,0,0,0.05);
            margin-top: 80px;
            animation: fadeInUp 1s ease-out 1s forwards;
            opacity: 0;
        }

        .system-badge {
            display: inline-block;
            padding: 6px 15px;
            background: #e0e0e0;
            border-radius: 20px;
            font-size: 0.85em;
            margin-top: 15px;
            font-weight: 600;
            color: var(--text-main);
        }
        .status-dot {
            height: 8px; width: 8px;
            background-color: var(--success);
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
    </style>
</head>
<body>

    <div class="hero">
        <img src="img/logo.png" alt="Logo LogiFête" style="height: 100px; margin-bottom: 20px;">

        <h1>LogiFête PGI</h1>
        <p>La solution intégrée pour la gestion de parc matériel et la planification événementielle.<br>Simple. Efficace. Élégante.</p>
        
        <a href="login.php" class="btn-add hero-btn" style="padding: 18px 50px; font-size: 1.1em; border-radius: 3px;">Accéder à l'Espace Pro</a>
    </div>

    <div class="features">
        <div class="feature-card">
            <span class="feature-icon">📦</span>
            <h3>Gestion de Stock</h3>
            <p style="color:var(--text-muted); font-size: 0.95em;">Suivi unitaire par QR Code, état des lieux en temps réel et historique de maintenance.</p>
        </div>
        <div class="feature-card">
            <span class="feature-icon">📑</span>
            <h3>Cycle Commercial</h3>
            <p style="color:var(--text-muted); font-size: 0.95em;">De la création du devis à la facturation finale, gérez vos clients et vos locations en toute fluidité.</p>
        </div>
        <div class="feature-card">
            <span class="feature-icon">🚚</span>
            <h3>Logistique</h3>
            <p style="color:var(--text-muted); font-size: 0.95em;">Planification intelligente des missions techniciens, livraisons, retours et réparations.</p>
        </div>
    </div>

    <div class="footer">
        <p>&copy; 2025 LogiFête Solutions. Design Bureaucratique & Minimaliste.</p>
        
        <div class="system-badge">
            <span class="status-dot"></span> 
            Système Opérationnel • <?= $user_count ?> Utilisateurs actifs
        </div>
    </div>

</body>
</html>