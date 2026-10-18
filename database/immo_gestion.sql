-- =====================================================
-- BASE DE DONNÉES : IMMO_GESTION
-- Gestion des maisons, appartements et locations
-- =====================================================

USE immo_gestion;

-- 1. TABLE DES UTILISATEURS
-- Le rôle permet de distinguer le super-admin des admins.

CREATE TABLE utilisateurs (
id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
nom VARCHAR(100) NOT NULL,
email VARCHAR(150) NOT NULL UNIQUE,
mot_de_passe VARCHAR(255) NOT NULL,
role ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin',
actif TINYINT(1) NOT NULL DEFAULT 1,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABLE DES MAISONS

CREATE TABLE maisons (
id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
nom VARCHAR(150) NOT NULL,
adresse VARCHAR(255) NOT NULL,
ville VARCHAR(100) NOT NULL,
description TEXT NULL,
actif TINYINT(1) NOT NULL DEFAULT 1,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABLE DES AFFECTATIONS DES ADMINISTRATEURS
-- Un administrateur peut gérer plusieurs maisons.

CREATE TABLE affectations_admins (
utilisateur_id INT UNSIGNED NOT NULL,
maison_id INT UNSIGNED NOT NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

```
PRIMARY KEY (utilisateur_id, maison_id),

CONSTRAINT fk_affectation_utilisateur
    FOREIGN KEY (utilisateur_id)
    REFERENCES utilisateurs(id)
    ON DELETE RESTRICT,

CONSTRAINT fk_affectation_maison
    FOREIGN KEY (maison_id)
    REFERENCES maisons(id)
    ON DELETE RESTRICT
```

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TABLE DES APPARTEMENTS

CREATE TABLE appartements (
id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
maison_id INT UNSIGNED NOT NULL,
numero VARCHAR(50) NOT NULL,
description TEXT NULL,
loyer_mensuel DECIMAL(12,2) NOT NULL,
statut ENUM('libre', 'occupe', 'indisponible')
NOT NULL DEFAULT 'libre',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
ON UPDATE CURRENT_TIMESTAMP,

```
CONSTRAINT fk_appartement_maison
    FOREIGN KEY (maison_id)
    REFERENCES maisons(id)
    ON DELETE RESTRICT,

UNIQUE KEY unique_appartement_maison (maison_id, numero)
```

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. TABLE DES LOCATAIRES

CREATE TABLE locataires (
id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
nom VARCHAR(100) NOT NULL,
prenom VARCHAR(100) NOT NULL,
telephone VARCHAR(30) NOT NULL,
email VARCHAR(150) NULL,
adresse VARCHAR(255) NULL,
actif TINYINT(1) NOT NULL DEFAULT 1,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TABLE DES LOCATIONS
-- Un contrat correspond à un locataire et un appartement.

CREATE TABLE locations (
id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
appartement_id INT UNSIGNED NOT NULL,
locataire_id INT UNSIGNED NOT NULL,
date_debut DATE NOT NULL,
date_fin DATE NULL,
loyer_mensuel DECIMAL(12,2) NOT NULL,
jour_echeance TINYINT UNSIGNED NOT NULL DEFAULT 5,
statut ENUM('active', 'terminee', 'resiliee')
NOT NULL DEFAULT 'active',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
ON UPDATE CURRENT_TIMESTAMP,

```
CONSTRAINT fk_location_appartement
    FOREIGN KEY (appartement_id)
    REFERENCES appartements(id)
    ON DELETE RESTRICT,

CONSTRAINT fk_location_locataire
    FOREIGN KEY (locataire_id)
    REFERENCES locataires(id)
    ON DELETE RESTRICT,

CONSTRAINT chk_location_loyer
    CHECK (loyer_mensuel >= 0),

CONSTRAINT chk_location_echeance
    CHECK (jour_echeance BETWEEN 1 AND 28),

INDEX idx_location_appartement_statut (appartement_id, statut),
INDEX idx_location_locataire (locataire_id)
```

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. TABLE DES PAIEMENTS
-- Un paiement peut être total ou partiel.
-- La période concernée est enregistrée séparément de la date de paiement.

CREATE TABLE paiements (
id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
location_id INT UNSIGNED NOT NULL,
enregistre_par INT UNSIGNED NOT NULL,
montant DECIMAL(12,2) NOT NULL,
periode_mois DATE NOT NULL,
date_paiement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
mode_paiement ENUM('especes', 'virement', 'mobile_money', 'autre')
NOT NULL DEFAULT 'especes',
reference VARCHAR(100) NULL,
statut ENUM('valide', 'annule') NOT NULL DEFAULT 'valide',
commentaire TEXT NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

```
CONSTRAINT fk_paiement_location
    FOREIGN KEY (location_id)
    REFERENCES locations(id)
    ON DELETE RESTRICT,

CONSTRAINT fk_paiement_utilisateur
    FOREIGN KEY (enregistre_par)
    REFERENCES utilisateurs(id)
    ON DELETE RESTRICT,

CONSTRAINT chk_paiement_montant
    CHECK (montant > 0),

INDEX idx_paiement_location_periode (location_id, periode_mois),
INDEX idx_paiement_date (date_paiement)
```

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. TABLE DES TRAVAUX ET DÉPENSES

CREATE TABLE travaux (
id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
maison_id INT UNSIGNED NOT NULL,
appartement_id INT UNSIGNED NULL,
enregistre_par INT UNSIGNED NOT NULL,
titre VARCHAR(150) NOT NULL,
description TEXT NULL,
montant DECIMAL(12,2) NOT NULL DEFAULT 0,
date_travail DATE NOT NULL,
statut ENUM('prevu', 'en_cours', 'termine')
NOT NULL DEFAULT 'prevu',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

```
CONSTRAINT fk_travail_maison
    FOREIGN KEY (maison_id)
    REFERENCES maisons(id)
    ON DELETE RESTRICT,

CONSTRAINT fk_travail_appartement
    FOREIGN KEY (appartement_id)
    REFERENCES appartements(id)
    ON DELETE RESTRICT,

CONSTRAINT fk_travail_utilisateur
    FOREIGN KEY (enregistre_par)
    REFERENCES utilisateurs(id)
    ON DELETE RESTRICT,

CONSTRAINT chk_travail_montant
    CHECK (montant >= 0),

INDEX idx_travail_maison_date (maison_id, date_travail)
```

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- FIN DE LA CRÉATION DES TABLES
-- =====================================================
