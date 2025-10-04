# Changelog - Module Supplier Return

Toutes les modifications notables de ce module seront documentées dans ce fichier.

## [1.1.1] - 2025-09-06

### 🔧 Corrections techniques
- **Installation module** : Correction chemin absolu hardcodé dans check_pdf_model.php
- **Compatibilité** : Utilisation pattern standard Dolibarr pour inclusion main.inc.php
- **Portabilité** : Module maintenant installable sur toute instance Dolibarr
- **Documentation** : Ajout CHANGELOG pour traçabilité des versions

### 🐛 Bugs corrigés
- **Erreur installation** : "L'appel main.inc.php supplierreturn/check_pdf_model.php" résolue

## [1.1.0] - 2025-09-05

### 🔧 Améliorations majeures
- **Interface admin** : Configuration complète avec détection automatique des modules PDF et numérotation
- **Génération PDF** : Modèle PDF professionnel avec calcul TVA, références produits, watermarks
- **Affichage totaux** : Interface native Dolibarr avec layout deux colonnes (fichehalfleft/fichehalfright)
- **Calculs automatiques** : Mise à jour des totaux après chaque opération sur les lignes

### 🐛 Corrections
- **Sauvegarde référence fournisseur** : Ajout du champ `supplier_ref` dans les requêtes SQL UPDATE
- **Sauvegarde motif de retour** : Ajout du champ `return_reason` dans les requêtes SQL UPDATE
- **Déduplication modules** : Correction de l'affichage en double des modules PDF dans l'administration
- **Chemin fichier SPECIMEN** : Correction de la résolution des chemins pour les fichiers SPECIMEN.pdf

### ✨ Nouvelles fonctionnalités
- **Support watermarks** : Filigrane automatique sur les documents brouillon
- **Support extrafields** : Gestion des champs personnalisés dans les en-têtes et lignes
- **Affichage références** : Format "REF123 - Nom du produit" dans les PDF
- **Calculs TVA avancés** : Gestion complète des taux de TVA et totaux

### 🎨 Interface utilisateur
- **Style natif Dolibarr** : CSS classes natives (`liste_total`, `liste_titre`, etc.)
- **Affichage totaux** : Panel droit dédié aux totaux financiers
- **Formatage prix** : Utilisation de la fonction `price()` native avec devise

### 🔧 Technique  
- **Optimisation requêtes** : Amélioration des performances des requêtes SQL
- **Gestion erreurs** : Meilleure gestion des erreurs et messages utilisateur
- **Documentation** : Code entièrement documenté et commenté
- **Standards Dolibarr** : Respect complet des conventions de développement Dolibarr

## [1.0.0] - 2025-08-03

### 🎉 Version initiale
- **Fonctionnalité de base** : Gestion des retours fournisseurs
- **Intégration stock** : Mise à jour automatique des stocks
- **Interface administration** : Configuration de base du module
- **Génération PDF** : Modèle PDF standard
- **Gestion utilisateurs** : Permissions et droits d'accès