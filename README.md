# 🏠 ImmoGestion

**ImmoGestion** est une application web de gestion immobilière conçue pour faciliter la gestion de plusieurs maisons, appartements et locataires. Elle permet de centraliser les informations, de suivre les loyers et les paiements, et d'enregistrer les travaux et les dépenses liés aux biens immobiliers.

Le projet vise à simplifier le travail des gestionnaires immobiliers grâce à une interface centralisée et à un système de gestion des accès.

## 📌 Sommaire

* [Présentation](#-présentation)
* [Fonctionnalités](#-fonctionnalités)
* [Technologies utilisées](#-technologies-utilisées)
* [Rôles des utilisateurs](#-rôles-des-utilisateurs)
* [Structure du projet](#-structure-du-projet)
* [Installation](#-installation)
* [Configuration de la base de données](#-configuration-de-la-base-de-données)
* [Lancement de l'application](#-lancement-de-lapplication)
* [Sécurité](#-sécurité)
* [Améliorations futures](#-améliorations-futures)
* [Auteur](#-auteur)
* [Licence](#-licence)

## 📖 Présentation

ImmoGestion est un projet de développement web destiné à la gestion locative.

L'application permet notamment de :

* Organiser les biens immobiliers par maison et par appartement.
* Enregistrer les locataires et leurs locations.
* Suivre les paiements des loyers.
* Consulter les soldes et l'état des loyers.
* Enregistrer les travaux réalisés ou prévus.
* Suivre les dépenses liées aux travaux.
* Gérer les utilisateurs et leurs autorisations.

L'objectif est de rendre la gestion immobilière plus simple, structurée et efficace.

## ✨ Fonctionnalités

### 🏢 Gestion immobilière

* Gestion des maisons.
* Gestion des appartements associés à chaque maison.
* Activation et désactivation des biens immobiliers.
* Organisation des biens par maison et par appartement.

### 👥 Gestion des locataires

* Enregistrement des locataires.
* Gestion des informations des locataires.
* Association des locataires aux locations.

### 📝 Gestion des locations

* Enregistrement des locations.
* Association des locataires aux appartements.
* Suivi des informations relatives aux locations.

### 💰 Gestion des paiements

* Enregistrement des paiements.
* Consultation de l'historique des paiements.
* Annulation des paiements selon les autorisations prévues par l'application.
* Suivi des loyers et des soldes.

### 🔧 Gestion des travaux et des dépenses

* Enregistrement des travaux.
* Association des travaux à une maison ou à un appartement.
* Suivi du montant, de la date et du statut des travaux.
* Gestion des dépenses liées aux travaux.

### 🔐 Gestion des utilisateurs

* Gestion des comptes utilisateurs.
* Différenciation entre super-administrateur et administrateur.
* Attribution de plusieurs maisons à un même administrateur.
* Restriction de l'accès aux maisons selon les affectations.

## 🛠️ Technologies utilisées

| Technologie     | Utilisation                                              |
| --------------- | -------------------------------------------------------- |
| PHP             | Logique serveur et traitement des formulaires            |
| MySQL / MariaDB | Stockage des données                                     |
| HTML5           | Structure des pages web                                  |
| CSS3            | Mise en forme et interface utilisateur                   |
| JavaScript      | Interactions et filtrage dynamique des formulaires       |
| PDO             | Connexion à la base de données et requêtes SQL préparées |
| Apache          | Serveur web local                                        |
| XAMPP           | Environnement de développement                           |
| Git             | Gestion des versions                                     |
| GitHub          | Hébergement du code source                               |

## 👤 Rôles des utilisateurs

### Super-administrateur

Le super-administrateur dispose d'un accès global à la gestion de l'application.

Selon les fonctionnalités disponibles, il peut :

* Gérer les maisons et les appartements.
* Gérer les locataires.
* Consulter les locations et les paiements.
* Consulter les travaux et les dépenses.
* Gérer les utilisateurs.
* Attribuer plusieurs maisons aux administrateurs.

### Administrateur

L'administrateur intervient uniquement sur les maisons qui lui sont attribuées.

Il peut notamment :

* Consulter les locations et les paiements autorisés.
* Enregistrer et suivre les travaux des maisons auxquelles il a accès.
* Consulter les informations permises par son rôle.

Les autorisations sont contrôlées côté serveur.

## 📁 Structure du projet

La structure principale du projet est organisée par modules :

```text
ImmoGestion/
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
├── appartements/
├── locataires/
├── locations/
├── paiements/
├── travaux/
├── utilisateurs/
│
├── dashboard.php
├── index.php
└── README.md
```

*Remarque : cette arborescence présente les principaux dossiers. Certains fichiers supplémentaires peuvent exister dans chaque module.*

## ⚙️ Installation

### Prérequis

Avant de lancer le projet, installez :

* [XAMPP](https://www.apachefriends.org/) pour Apache, PHP et MySQL/MariaDB.
* [Git](https://git-scm.com/) pour récupérer le code source.
* Un navigateur web récent.

### 1. Récupérer le projet

Ouvrez Git Bash ou un terminal, puis exécutez :

```bash
git clone URL_DE_VOTRE_DEPOT
```

Remplacez `URL_DE_VOTRE_DEPOT` par l'adresse HTTPS réelle de votre dépôt GitHub.

### 2. Placer le projet

Placez le dossier `ImmoGestion` dans le répertoire :

```text
C:\xampp\htdocs\
```

Le chemin final devrait être :

```text
C:\xampp\htdocs\ImmoGestion\
```

Si vous avez déjà le projet dans ce dossier, cette étape n'est pas nécessaire.

### 3. Démarrer XAMPP

Ouvrez le panneau de contrôle XAMPP et démarrez :

* **Apache**
* **MySQL**

### 4. Créer la base de données

Ouvrez phpMyAdmin :

```text
http://localhost/phpmyadmin/
```

Créez une base de données nommée :

```text
immogestion
```

Importez ensuite le fichier SQL du projet s'il est présent dans le dépôt.

Si aucun fichier SQL n'est fourni, vous devrez créer les tables nécessaires avant de lancer l'application.

### 5. Configurer la connexion

Ouvrez le fichier :

```text
config/database.php
```

Renseignez les paramètres correspondant à votre environnement local :

* Hôte de la base de données.
* Nom de la base de données.
* Nom d'utilisateur MySQL.
* Mot de passe MySQL.

**Important :** ne publiez jamais de mots de passe ou d'identifiants réels dans un dépôt public.

### 6. Lancer l'application

Dans votre navigateur, ouvrez :

```text
http://localhost/ImmoGestion/
```

Connectez-vous avec un compte utilisateur déjà créé dans la base de données.

## 🔐 Sécurité

Le projet met en œuvre ou prévoit plusieurs mécanismes de sécurité :

* Authentification des utilisateurs.
* Contrôle des autorisations selon les rôles.
* Vérification des affectations des administrateurs aux maisons.
* Requêtes SQL préparées avec PDO.
* Protection CSRF des formulaires concernés.
* Validation des données côté serveur.
* Échappement des données affichées dans les pages.

La sécurité doit être vérifiée sur chaque module avant toute mise en production.

## 🚀 Améliorations futures

Les évolutions envisagées peuvent inclure :

* Amélioration du tableau de bord et des statistiques.
* Génération de reçus de paiement en PDF.
* Génération de rapports financiers.
* Notifications pour les loyers impayés.
* Amélioration de l'affichage sur mobile.
* Renforcement des tests et de la sécurité.
* Déploiement sur un serveur de production.

## 👨‍💻 Auteur

**Bano Camara**

Étudiant en Génie Logiciel et Administration Réseaux (GLAR) à l'ESTM.

Centres d'intérêt :

* Développement web.
* Programmation.
* Administration réseaux.
* Cybersécurité.

## 📄 Licence

Ce projet est développé dans un cadre d'apprentissage et de gestion immobilière.

Aucune licence open source n'est actuellement spécifiée. Les conditions de réutilisation du code devront être définies avant sa redistribution.
