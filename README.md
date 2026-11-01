# 🏢 ImmoGestion

**ImmoGestion** est une application web de gestion immobilière développée dans le cadre de mon parcours en Génie Logiciel et Administration Réseaux (GLAR).

L'application permet de centraliser et de simplifier la gestion des maisons, des appartements, des locataires, des locations, des paiements et des travaux immobiliers.

L'objectif est de faciliter le suivi administratif et financier d'un patrimoine immobilier à travers une interface simple, claire et accessible.

---

## 📌 Sommaire

- Présentation du projet
- Fonctionnalités principales
- Technologies utilisées
- Gestion des utilisateurs
- Structure du projet
- Installation
- Configuration de la base de données
- Sécurité
- Auteur

---

## 🎯 Présentation du projet

ImmoGestion est une application web conçue pour accompagner les propriétaires et les gestionnaires immobiliers dans leurs activités quotidiennes.

Elle permet notamment de :

- Gérer plusieurs maisons.
- Enregistrer les appartements associés à chaque maison.
- Gérer les informations des locataires.
- Enregistrer et suivre les contrats de location.
- Enregistrer les paiements des loyers.
- Consulter les soldes et les loyers restants.
- Suivre les travaux et les dépenses immobilières.
- Consulter un tableau de bord présentant les principales statistiques.

L'application utilise le MRU comme monnaie de référence.

---

## ✨ Fonctionnalités principales

### 1. Tableau de bord

- Affichage du nombre de maisons actives.
- Affichage du nombre d'appartements actifs.
- Suivi des appartements libres et occupés.
- Nombre de locataires actifs.
- Suivi des utilisateurs actifs pour le super-administrateur.
- Affichage des loyers encaissés.
- Calcul des loyers attendus et restants.
- Affichage des dépenses de travaux.
- Consultation des derniers paiements enregistrés.

### 2. Gestion immobilière

- Ajout de maisons.
- Modification des informations des maisons.
- Désactivation des maisons.
- Ajout d'appartements.
- Modification des appartements.
- Désactivation des appartements.
- Suivi de l'occupation des appartements.

### 3. Gestion des locataires

- Enregistrement des locataires.
- Consultation des informations des locataires.
- Modification des informations.
- Gestion de leur statut.

### 4. Gestion des locations

- Création d'une location.
- Association d'un locataire à un appartement.
- Définition du loyer mensuel.
- Enregistrement des dates de location.
- Modification des informations d'une location.
- Suivi du statut des locations.

### 5. Gestion des paiements

- Enregistrement des paiements.
- Association des paiements aux locations.
- Enregistrement du montant et de la période payée.
- Gestion des modes de paiement.
- Consultation de l'historique des paiements.
- Annulation d'un paiement.
- Suivi des loyers payés et restants.

### 6. Gestion des travaux et des dépenses

- Enregistrement des travaux.
- Association des travaux à une maison.
- Association facultative à un appartement.
- Enregistrement du montant des travaux.
- Définition de la date des travaux.
- Suivi de leur état d'avancement.
- Consultation des dépenses.

### 7. Gestion des utilisateurs

- Création des comptes utilisateurs.
- Modification des informations des utilisateurs.
- Activation et désactivation des comptes.
- Attribution de plusieurs maisons à un administrateur.
- Gestion des rôles et des accès.

---

## 👥 Gestion des utilisateurs et des rôles

L'application possède deux rôles principaux.

### Super-administrateur

Le super-administrateur dispose d'un accès global à l'application.

Il peut :

- Gérer les maisons.
- Gérer les appartements.
- Gérer les locataires.
- Gérer les locations.
- Gérer les paiements.
- Gérer les travaux et les dépenses.
- Créer et gérer les utilisateurs.
- Affecter plusieurs maisons à un administrateur.

### Administrateur

L'administrateur intervient uniquement sur les maisons qui lui sont affectées.

Il peut notamment :

- Consulter les informations de ses maisons.
- Gérer les locations autorisées.
- Enregistrer les paiements.
- Consulter les soldes.
- Enregistrer et suivre les travaux de ses maisons.

Les autorisations sont également vérifiées côté serveur.

---

## 🛠️ Technologies utilisées

| Technologie | Utilisation |
|---|---|
| PHP | Développement de la logique côté serveur |
| MySQL / MariaDB | Gestion de la base de données |
| HTML5 | Structure des pages |
| CSS3 | Mise en forme et design |
| JavaScript | Interactions côté navigateur |
| PDO | Connexion et communication avec la base de données |
| XAMPP | Environnement de développement local |
| Visual Studio Code | Éditeur de code |
| Git et GitHub | Gestion des versions et hébergement du code |

---

## 📁 Structure du projet

ImmoGestion/
│
├── assets/
│   ├── css/
│   │   └── style.css
│   │
│   └── js/
│       └── script.js
│
├── config/
│   └── database.php
│
├── includes/
│   ├── auth.php
│   ├── header.php
│   ├── navbar.php
│   └── footer.php
│
├── maisons/
│
├── appartements/
│
├── locataires/
│
├── locations/
│
├── paiements/
│
├── travaux/
│
├── utilisateurs/
│
├── dashboard.php
├── login.php
├── logout.php
├── README.md
└── immogestion.sql

*Remarque : cette structure présente les principaux dossiers et fichiers. Certains fichiers peuvent varier selon la version du projet.*

---

## ⚙️ Installation du projet

### Prérequis

Avant de commencer, il faut installer :

* Windows 10 ou Windows 11.
* XAMPP.
* PHP.
* MySQL ou MariaDB.
* Un navigateur web.
* Visual Studio Code.

### Étape 1 : Récupérer le projet

Cloner le dépôt GitHub :


git clone https://github.com/banocamara/ImmoGestion.git


Ou télécharger directement le projet depuis GitHub.

### Étape 2 : Installer le projet dans XAMPP

Placer le dossier `ImmoGestion` dans :

C:\xampp\htdocs\

Le chemin final doit être :

C:\xampp\htdocs\ImmoGestion

### Étape 3 : Démarrer XAMPP

Ouvrir le panneau de contrôle XAMPP et démarrer :

* Apache
* MySQL

### Étape 4 : Créer la base de données

1. Ouvrir phpMyAdmin.
2. Créer une base de données nommée `immogestion`.
3. Sélectionner cette base de données.
4. Importer le fichier SQL du projet, s'il est présent.

### Étape 5 : Configurer la connexion

Ouvrir le fichier :

config/database.php

Vérifier les paramètres de connexion à la base de données :

$host = 'localhost';
$dbname = 'immogestion';
$username = 'root';
$password = '';

Ces valeurs sont données à titre d'exemple et doivent correspondre à la configuration de votre environnement.

### Étape 6 : Lancer l'application

Ouvrir un navigateur et accéder à :

http://localhost/ImmoGestion/

---

## 🔐 Sécurité

Plusieurs mécanismes de sécurité sont utilisés dans l'application :

* Authentification des utilisateurs.
* Gestion des rôles et des autorisations.
* Requêtes préparées avec PDO.
* Protection contre les injections SQL.
* Échappement des données affichées avec `htmlspecialchars()`.
* Protection CSRF sur les opérations concernées.
* Gestion des sessions PHP.
* Vérification des accès aux ressources selon les maisons affectées.

---

## 💻 Environnement de développement

Le projet a été développé dans un environnement local avec :

* Système d'exploitation : Windows 11.
* Serveur local : XAMPP.
* Langage serveur : PHP.
* Base de données : MySQL / MariaDB.
* Éditeur : Visual Studio Code.

---

## 👨‍💻 Développeur

**Bano Camara**

Étudiant en Licence 3  
Génie Logiciel et Administration Réseaux (GLAR)  
École Supérieure de Technologie et de Management (ESTM)

Développeur et concepteur de l'application ImmoGestion.

Ce projet est né d'un besoin réel exprimé par un client souhaitant disposer d'une solution numérique pour faciliter la gestion de ses activités immobilières.

Cette collaboration représente également une occasion de mettre en pratique mes compétences en développement web, d'approfondir mes connaissances et de développer une solution répondant à des besoins concrets.

---

## 📄 Licence et droits d'utilisation

ImmoGestion est une application développée dans le cadre d'une collaboration avec un client.

Les droits d'utilisation, de modification, de distribution et de propriété du code source sont soumis aux conditions convenues entre le développeur et le client.

Toute reproduction ou réutilisation du projet sans autorisation préalable est déconseillée.