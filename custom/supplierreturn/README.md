# Module Retours Fournisseurs

Module de gestion des retours de marchandises aux fournisseurs pour Dolibarr ERP/CRM.

## Fonctionnalités

- Création de retours fournisseurs depuis les réceptions
- Gestion complète du workflow : Brouillon → Validé → Traité
- Contrôle intelligent des quantités retournées
- Support des produits avec variations (attributs, lots, séries)
- Génération automatique d'avoirs fournisseurs
- Intégration native dans les fiches de réception

## Installation

1. **Activer le module** :
   - Aller dans `Configuration → Modules/Applications`
   - Rechercher "SupplierReturns" 
   - Cliquer sur `Activer`

2. **Configurer les permissions** :
   - Aller dans `Utilisateurs & Groupes → Groupes`
   - Modifier les groupes concernés
   - Attribuer les permissions "Retours Fournisseurs"

3. **Configuration du module** :
   - Aller dans `Configuration → Modules → SupplierReturns → Configurer`
   - Définir le masque de numérotation (défaut : SR{yy}{mm}-{####})

## Utilisation

### Créer un retour depuis une réception

1. Aller dans `Fournisseurs → Retours → Créer depuis réception`
2. Sélectionner le fournisseur
3. Choisir la réception dans la liste
4. Sélectionner les produits et quantités à retourner
5. Enregistrer le retour

### Valider et traiter un retour

1. **Statut Brouillon** : Modifier les lignes si nécessaire
2. **Valider** : Verrouiller le retour et attribuer le numéro définitif
3. **Traiter** : Mettre à jour le stock et marquer comme traité
4. **Créer l'avoir** : Générer automatiquement l'avoir fournisseur

### Accès depuis les réceptions

- Un bouton "Créer retour fournisseur" apparaît sur les fiches de réception validées
- La liste des retours liés s'affiche dans l'onglet "Objets liés"

## Prérequis

- **Dolibarr** : Version 12.0 minimum
- **Modules requis** : Stock, Fournisseurs (achats)
- **Permissions** : Gestion des stocks et factures fournisseurs

## Configuration

### Paramètres disponibles

- **Masque de numérotation** : Format des références (SR{yy}{mm}-{####})
- **Validation automatique des avoirs** : Valider automatiquement les avoirs créés

### Permissions

| Permission | Description |
|------------|-------------|
| Lire | Consulter les retours fournisseurs |
| Créer | Créer et modifier les retours |
| Supprimer | Supprimer les retours (statut brouillon uniquement) |

## Workflow

```
1. Création depuis réception
   ↓
2. Statut Brouillon (modification possible)
   ↓
3. Validation (verrouillage)
   ↓
4. Traitement (mise à jour stock)
   ↓
5. Création avoir (optionnel)
```

## Support

- **Configuration** : Via l'interface d'administration standard de Dolibarr
- **Permissions** : Via le système de groupes d'utilisateurs
- **Multi-entité** : Compatible avec les environnements multi-sociétés

## Dépannage

**Quantités non disponibles pour retour :**
- Vérifier que les produits ont été effectivement réceptionnés
- Les quantités déjà retournées sont automatiquement déduites
