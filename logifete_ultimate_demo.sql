-- BASE DE DONNÉES ULTIMATE DÉMO - LOGIFÊTE PGI
-- Date : 23/11/2025
-- Auteur : Gemini & Étudiant SI
-- Objectif : Peupler graphiques, alertes et listes pour une démo client.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ========================================================
-- 1. NETTOYAGE ET STRUCTURE (Mise à jour complète)
-- ========================================================

DROP TABLE IF EXISTS `affectation_tech`;
DROP TABLE IF EXISTS `kit_contenu`;
DROP TABLE IF EXISTS `reservation_equipement`;
DROP TABLE IF EXISTS `mission`;
DROP TABLE IF EXISTS `commande`;
DROP TABLE IF EXISTS `equipement_physique`;
DROP TABLE IF EXISTS `kit`;
DROP TABLE IF EXISTS `reference_materiel`;
DROP TABLE IF EXISTS `technicien`;
DROP TABLE IF EXISTS `historique_logs`;
DROP TABLE IF EXISTS `client`;
DROP TABLE IF EXISTS `fournisseur`;
DROP TABLE IF EXISTS `utilisateur`;

-- Table Utilisateur
CREATE TABLE `utilisateur` (
  `id_user` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` enum('admin','commercial','technicien') NOT NULL,
  `signature_data` text DEFAULT NULL,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table Client
CREATE TABLE `client` (
  `id_client` int(11) NOT NULL AUTO_INCREMENT,
  `nom_societe` varchar(100) NOT NULL,
  `contact_nom` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_client`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table Fournisseur
CREATE TABLE `fournisseur` (
  `id_fournisseur` int(11) NOT NULL AUTO_INCREMENT,
  `nom_societe` varchar(100) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `site_web` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_fournisseur`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table Référence Matériel
CREATE TABLE `reference_materiel` (
  `id_reference` int(11) NOT NULL AUTO_INCREMENT,
  `libelle` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `categorie` enum('Son','Lumière','Vidéo','Structure','Autre') NOT NULL,
  `prix_jour` decimal(10,2) NOT NULL,
  `prix_achat` decimal(10,2) DEFAULT 0.00,
  `image_url` varchar(255) DEFAULT NULL,
  `seuil_alerte` int(11) DEFAULT 2,
  PRIMARY KEY (`id_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table Équipement Physique
CREATE TABLE `equipement_physique` (
  `num_serie` varchar(50) NOT NULL,
  `id_reference` int(11) NOT NULL,
  `id_fournisseur` int(11) DEFAULT NULL,
  `date_ajout` date DEFAULT NULL,
  `fin_garantie` date DEFAULT NULL,
  `statut` enum('disponible','loue','maintenance','panne') DEFAULT 'disponible',
  `cout_reparations` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`num_serie`),
  KEY `id_reference` (`id_reference`),
  KEY `id_fournisseur` (`id_fournisseur`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table Commande
CREATE TABLE `commande` (
  `id_commande` int(11) NOT NULL AUTO_INCREMENT,
  `id_client` int(11) NOT NULL,
  `id_commercial` int(11) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `etat` enum('devis','validee','terminee','annulee') DEFAULT 'devis',
  `remise_percent` int(11) DEFAULT 0,
  `penalite` decimal(10,2) DEFAULT 0.00,
  `note_interne` text DEFAULT NULL,
  `signature_client` text DEFAULT NULL,
  `date_paiement` date DEFAULT NULL,
  `date_relance` datetime DEFAULT NULL,
  `date_relance_devis` date DEFAULT NULL,
  PRIMARY KEY (`id_commande`),
  KEY `id_client` (`id_client`),
  KEY `id_commercial` (`id_commercial`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table Réservation Équipement
CREATE TABLE `reservation_equipement` (
  `id_reservation` int(11) NOT NULL AUTO_INCREMENT,
  `id_commande` int(11) NOT NULL,
  `num_serie` varchar(50) NOT NULL,
  `check_depart` tinyint(1) DEFAULT 0,
  `date_depart_check` datetime DEFAULT NULL,
  `check_retour` tinyint(1) DEFAULT 0,
  `date_retour_check` datetime DEFAULT NULL,
  `etat_materiel` varchar(50) DEFAULT 'Bon état',
  `observations` text DEFAULT NULL,
  PRIMARY KEY (`id_reservation`),
  KEY `id_commande` (`id_commande`),
  KEY `num_serie` (`num_serie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table Mission
CREATE TABLE `mission` (
  `id_mission` int(11) NOT NULL AUTO_INCREMENT,
  `type_mission` enum('livraison','recuperation','installation','reparation') NOT NULL,
  `date_mission` date NOT NULL,
  `description` text NOT NULL,
  `statut` enum('a_faire','en_cours','terminee') DEFAULT 'a_faire',
  `id_commande` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_mission`),
  KEY `id_commande` (`id_commande`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table Technicien
CREATE TABLE `technicien` (
  `id_technicien` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `specialite` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_technicien`),
  KEY `id_user` (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table Affectation Tech
CREATE TABLE `affectation_tech` (
  `id_mission` int(11) NOT NULL,
  `id_technicien` int(11) NOT NULL,
  PRIMARY KEY (`id_mission`,`id_technicien`),
  KEY `id_technicien` (`id_technicien`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table Kit
CREATE TABLE `kit` (
  `id_kit` int(11) NOT NULL AUTO_INCREMENT,
  `libelle` varchar(100) NOT NULL,
  PRIMARY KEY (`id_kit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table Kit Contenu
CREATE TABLE `kit_contenu` (
  `id_kit` int(11) NOT NULL,
  `id_reference` int(11) NOT NULL,
  `quantite` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_kit`,`id_reference`),
  KEY `id_reference` (`id_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table Logs
CREATE TABLE `historique_logs` (
  `id_log` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `date_action` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_log`),
  KEY `id_user` (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ========================================================
-- 2. JEU DE DONNÉES MASSIF (Ultimate)
-- ========================================================

-- 2.1 UTILISATEURS
-- Mot de passe unique pour tous : "charlie123"
INSERT INTO `utilisateur` (`id_user`, `nom`, `email`, `mot_de_passe`, `role`) VALUES
(1, 'Alice Admin', 'admin@logifete.com', '$2y$10$Es6/LuQgkwu0uhRANocC1.H7Nufo43yT5lB2WYZGzmOKz2.NWPBDG', 'admin'),
(2, 'Bob Commercial', 'bob@logifete.com', '$2y$10$Es6/LuQgkwu0uhRANocC1.H7Nufo43yT5lB2WYZGzmOKz2.NWPBDG', 'commercial'),
(3, 'Sarah Ventes', 'sarah@logifete.com', '$2y$10$Es6/LuQgkwu0uhRANocC1.H7Nufo43yT5lB2WYZGzmOKz2.NWPBDG', 'commercial'),
(4, 'Charlie Tech', 'charlie@logifete.com', '$2y$10$Es6/LuQgkwu0uhRANocC1.H7Nufo43yT5lB2WYZGzmOKz2.NWPBDG', 'technicien'),
(5, 'David Tech', 'david@logifete.com', '$2y$10$Es6/LuQgkwu0uhRANocC1.H7Nufo43yT5lB2WYZGzmOKz2.NWPBDG', 'technicien'),
(6, 'Eva Tech', 'eva@logifete.com', '$2y$10$Es6/LuQgkwu0uhRANocC1.H7Nufo43yT5lB2WYZGzmOKz2.NWPBDG', 'technicien');

-- 2.2 TECHNICIENS
INSERT INTO `technicien` (`id_technicien`, `id_user`, `specialite`) VALUES
(1, 4, 'Son & Lumière'),
(2, 5, 'Structure & Rigging'),
(3, 6, 'Vidéo & Réseaux');

-- 2.3 CLIENTS
INSERT INTO `client` (`id_client`, `nom_societe`, `contact_nom`, `email`, `telephone`, `adresse`) VALUES
(1, 'Festival Rock en Seine', 'Julie Girard', 'prod@rockenseine.com', '0144556677', 'Domaine National de Saint-Cloud, 92210'),
(2, 'Mariage Prestige', 'Pierre Durand', 'contact@prestige-wedding.com', '0612345678', 'Château de Versailles, 78000'),
(3, 'Tech Corp Solutions', 'Marc Zuckerberg', 'admin@techcorp.com', '0199887766', 'Station F, Paris 13'),
(4, 'Mairie de Paris', 'Service Culture', 'culture@paris.fr', '0140000000', 'Hôtel de Ville, 75004 Paris'),
(5, 'Asso des Etudiants', 'Léo B.', 'bde@ecole.fr', '0600000000', 'Campus Universitaire, Bâtiment B'),
(6, 'Agence Event Pro', 'Sophie L.', 'sophie@eventpro.fr', '0700000000', '12 Rue de la Paix, Paris'),
(7, 'Le Zénith', 'Direction Technique', 'tech@zenith.com', '0142000000', '211 Avenue Jean Jaurès, 75019 Paris');

-- 2.4 FOURNISSEURS
INSERT INTO `fournisseur` (`id_fournisseur`, `nom_societe`, `telephone`, `email`, `site_web`) VALUES
(1, 'Thomann Pro', '+4995469223', 'pro@thomann.de', 'https://thomann.de'),
(2, 'SonoVente', '0160000000', 'pro@sonovente.com', 'https://sonovente.com'),
(3, 'Boulanger Pro', '0800000000', 'sav@boulanger.fr', 'https://boulanger.com'),
(4, 'Cameo Light', '+3312345678', 'info@cameo.com', 'https://cameolight.com');

-- 2.5 RÉFÉRENCES MATÉRIEL
INSERT INTO `reference_materiel` (`id_reference`, `libelle`, `description`, `categorie`, `prix_jour`, `prix_achat`, `image_url`, `seuil_alerte`) VALUES
(1, 'Enceinte Yamaha DXR12', 'Enceinte active 1100W, son clair et puissant.', 'Son', 45.00, 600.00, 'https://placehold.co/100x100/png?text=Yamaha', 4),
(2, 'Caisson de Basse 18"', 'Subwoofer actif 1500W pour gros événements.', 'Son', 70.00, 1200.00, 'https://placehold.co/100x100/png?text=Sub', 2),
(3, 'Micro Shure SM58', 'Le standard mondial pour la voix.', 'Son', 10.00, 110.00, 'https://placehold.co/100x100/png?text=SM58', 5),
(4, 'Console Mixage 16 Pistes', 'Table de mixage numérique compacte.', 'Son', 80.00, 1500.00, 'https://placehold.co/100x100/png?text=Mixer', 1),
(5, 'Projecteur PAR LED', 'RGBW 12x10W, idéal pour la déco.', 'Lumière', 15.00, 90.00, 'https://placehold.co/100x100/png?text=LED', 10),
(6, 'Lyre Beam 7R', 'Tête mobile puissante pour show dynamique.', 'Lumière', 55.00, 450.00, 'https://placehold.co/100x100/png?text=Beam', 4),
(7, 'Machine à Fumée Lourde', 'Effet nuage au sol (eau + liquide).', 'Autre', 120.00, 2000.00, 'https://placehold.co/100x100/png?text=Fumee', 1),
(8, 'Structure Alu 2m', 'Pont carré 290mm Global Truss.', 'Structure', 12.00, 150.00, 'https://placehold.co/100x100/png?text=Truss', 8),
(9, 'Vidéoprojecteur 5000Lm', 'Full HD, haute luminosité.', 'Vidéo', 90.00, 1200.00, 'https://placehold.co/100x100/png?text=Video', 2),
(10, 'Ecran de Projection 3m', 'Toile sur cadre pliable.', 'Vidéo', 40.00, 300.00, 'https://placehold.co/100x100/png?text=Ecran', 2);

-- 2.6 STOCK (ÉQUIPEMENTS PHYSIQUES)
-- On génère du stock varié pour les stats
INSERT INTO `equipement_physique` (`num_serie`, `id_reference`, `id_fournisseur`, `date_ajout`, `fin_garantie`, `statut`, `cout_reparations`) VALUES
-- Enceintes (Dispo et Louées)
('DXR-001', 1, 1, '2024-01-10', '2026-01-10', 'disponible', 0.00),
('DXR-002', 1, 1, '2024-01-10', '2026-01-10', 'disponible', 0.00),
('DXR-003', 1, 1, '2024-01-10', '2026-01-10', 'loue', 0.00),
('DXR-004', 1, 1, '2024-01-10', '2026-01-10', 'loue', 0.00),
('DXR-005', 1, 1, '2022-01-01', '2024-01-01', 'panne', 150.00), -- Hors Garantie + Panne
('DXR-006', 1, 1, '2022-01-01', '2024-01-01', 'disponible', 50.00), -- Hors Garantie

-- Micros (Beaucoup de stock)
('MIC-001', 3, 1, '2024-05-01', '2026-05-01', 'disponible', 0.00),
('MIC-002', 3, 1, '2024-05-01', '2026-05-01', 'disponible', 0.00),
('MIC-003', 3, 1, '2024-05-01', '2026-05-01', 'loue', 0.00),
('MIC-004', 3, 1, '2024-05-01', '2026-05-01', 'maintenance', 0.00),

-- Lumières (Alertes Stock Bas)
('LED-001', 5, 2, '2024-02-01', '2025-02-01', 'disponible', 0.00),
('LED-002', 5, 2, '2024-02-01', '2025-02-01', 'disponible', 0.00),
('LED-003', 5, 2, '2024-02-01', '2025-02-01', 'disponible', 0.00),
('BEAM-001', 6, 4, '2024-08-01', '2026-08-01', 'loue', 0.00),
('BEAM-002', 6, 4, '2024-08-01', '2026-08-01', 'loue', 0.00),

-- Vidéo & Fumée
('PROJ-001', 9, 3, '2023-01-01', '2024-01-01', 'disponible', 0.00), -- Hors Garantie
('SMK-001', 7, 2, '2024-01-01', '2025-01-01', 'panne', 200.00);

-- 2.7 COMMANDES (Scénarios Temporels)

-- #100: Ancienne commande payée (Il y a 5 mois)
INSERT INTO `commande` (`id_commande`, `id_client`, `id_commercial`, `date_creation`, `date_debut`, `date_fin`, `etat`, `date_paiement`) VALUES
(100, 1, 2, DATE_SUB(NOW(), INTERVAL 5 MONTH), DATE_SUB(NOW(), INTERVAL 5 MONTH), DATE_SUB(NOW(), INTERVAL 148 DAY), 'terminee', DATE_SUB(NOW(), INTERVAL 145 DAY));

-- #101: Commande mois dernier (Payée)
INSERT INTO `commande` (`id_commande`, `id_client`, `id_commercial`, `date_creation`, `date_debut`, `date_fin`, `etat`, `date_paiement`) VALUES
(101, 4, 3, DATE_SUB(NOW(), INTERVAL 1 MONTH), DATE_SUB(NOW(), INTERVAL 25 DAY), DATE_SUB(NOW(), INTERVAL 24 DAY), 'terminee', DATE_SUB(NOW(), INTERVAL 20 DAY));

-- #102: Commande EN COURS (Validée) - Mariage Prestige
INSERT INTO `commande` (`id_commande`, `id_client`, `id_commercial`, `date_creation`, `date_debut`, `date_fin`, `etat`) VALUES
(102, 2, 2, DATE_SUB(NOW(), INTERVAL 2 DAY), CURDATE(), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'validee');

-- #103: Commande EN RETARD (Validée, date fin passée) - Asso Etudiants
INSERT INTO `commande` (`id_commande`, `id_client`, `id_commercial`, `date_creation`, `date_debut`, `date_fin`, `etat`) VALUES
(103, 5, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), 'validee');

-- #104: Devis récent
INSERT INTO `commande` (`id_commande`, `id_client`, `id_commercial`, `date_creation`, `date_debut`, `date_fin`, `etat`) VALUES
(104, 3, 2, NOW(), DATE_ADD(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 12 DAY), 'devis');

-- #105: Devis Oublié (Pour Alerte Relance)
INSERT INTO `commande` (`id_commande`, `id_client`, `id_commercial`, `date_creation`, `date_debut`, `date_fin`, `etat`) VALUES
(105, 6, 2, DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_ADD(CURDATE(), INTERVAL 1 MONTH), DATE_ADD(CURDATE(), INTERVAL 32 DAY), 'devis');


-- 2.8 RÉSERVATIONS
-- Matériel pour commande en cours (#102)
INSERT INTO `reservation_equipement` (`id_commande`, `num_serie`, `check_depart`, `date_depart_check`) VALUES
(102, 'DXR-003', 1, NOW()),
(102, 'DXR-004', 1, NOW()),
(102, 'MIC-003', 1, NOW());

-- Matériel pour commande en retard (#103)
INSERT INTO `reservation_equipement` (`id_commande`, `num_serie`, `check_depart`, `date_depart_check`) VALUES
(103, 'BEAM-001', 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(103, 'BEAM-002', 1, DATE_SUB(NOW(), INTERVAL 5 DAY));


-- 2.9 MISSIONS LOGISTIQUES

-- Mission Passée (Livraison #102)
INSERT INTO `mission` (`id_mission`, `type_mission`, `date_mission`, `description`, `statut`, `id_commande`) VALUES
(1, 'livraison', CURDATE(), 'Livraison Château de Versailles', 'terminee', 102);
INSERT INTO `affectation_tech` VALUES (1, 1); -- Charlie

-- Mission Future (Récupération #102)
INSERT INTO `mission` (`id_mission`, `type_mission`, `date_mission`, `description`, `statut`, `id_commande`) VALUES
(2, 'recuperation', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'Récupération Mariage', 'a_faire', 102);
INSERT INTO `affectation_tech` VALUES (2, 2); -- David

-- Mission Urgente (Récupération Retard #103)
INSERT INTO `mission` (`id_mission`, `type_mission`, `date_mission`, `description`, `statut`, `id_commande`) VALUES
(3, 'recuperation', CURDATE(), 'URGENT : Récupération Asso Etudiants (Retard)', 'a_faire', 103);
INSERT INTO `affectation_tech` VALUES (3, 3); -- Eva

-- Mission Atelier (Réparation SMK-001)
INSERT INTO `mission` (`id_mission`, `type_mission`, `date_mission`, `description`, `statut`) VALUES
(4, 'reparation', CURDATE(), 'Réparation Machine à fumée SMK-001 (Pompe HS)', 'a_faire');
INSERT INTO `affectation_tech` VALUES (4, 1); -- Charlie


-- 2.10 LOGS (Historique)
INSERT INTO `historique_logs` (`id_user`, `action`, `details`, `date_action`) VALUES
(1, 'CONNEXION', 'Alice Admin s\'est connecté', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 'CREATION DEVIS', 'Nouveau devis #104', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(2, 'VALIDATION COMMANDE', 'Validation de la commande #102', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 'PANNE', 'Signalement panne sur DXR-005', DATE_SUB(NOW(), INTERVAL 1 WEEK));


-- ========================================================
-- 3. CONTRAINTES (Foreign Keys)
-- ========================================================

ALTER TABLE `equipement_physique`
  ADD CONSTRAINT `fk_eq_ref` FOREIGN KEY (`id_reference`) REFERENCES `reference_materiel` (`id_reference`),
  ADD CONSTRAINT `fk_eq_four` FOREIGN KEY (`id_fournisseur`) REFERENCES `fournisseur` (`id_fournisseur`);

ALTER TABLE `commande`
  ADD CONSTRAINT `fk_cmd_cli` FOREIGN KEY (`id_client`) REFERENCES `client` (`id_client`),
  ADD CONSTRAINT `fk_cmd_com` FOREIGN KEY (`id_commercial`) REFERENCES `utilisateur` (`id_user`);

ALTER TABLE `reservation_equipement`
  ADD CONSTRAINT `fk_res_cmd` FOREIGN KEY (`id_commande`) REFERENCES `commande` (`id_commande`),
  ADD CONSTRAINT `fk_res_eq` FOREIGN KEY (`num_serie`) REFERENCES `equipement_physique` (`num_serie`) ON DELETE CASCADE;

ALTER TABLE `mission`
  ADD CONSTRAINT `fk_mis_cmd` FOREIGN KEY (`id_commande`) REFERENCES `commande` (`id_commande`);

ALTER TABLE `technicien`
  ADD CONSTRAINT `fk_tech_user` FOREIGN KEY (`id_user`) REFERENCES `utilisateur` (`id_user`) ON DELETE CASCADE;

ALTER TABLE `affectation_tech`
  ADD CONSTRAINT `fk_aff_mis` FOREIGN KEY (`id_mission`) REFERENCES `mission` (`id_mission`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_aff_tech` FOREIGN KEY (`id_technicien`) REFERENCES `technicien` (`id_technicien`) ON DELETE CASCADE;

ALTER TABLE `kit_contenu`
  ADD CONSTRAINT `fk_kit_main` FOREIGN KEY (`id_kit`) REFERENCES `kit` (`id_kit`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_kit_ref` FOREIGN KEY (`id_reference`) REFERENCES `reference_materiel` (`id_reference`);

COMMIT;