# Changelog

All notable changes to `ai-cad` will be documented in this file.

## v1.3.4 - 2026-03-24

### What's Changed

* perf: téléchargement async des fichiers CAO + material_choice + dual-key API by @croustibat in https://github.com/Tolery-Dev/tolery-ai-cad-package/pull/111

**Full Changelog**: https://github.com/Tolery-Dev/tolery-ai-cad-package/compare/v1.3.3...v1.3.4

## v1.2.0 - CGV ToleryCAD - 2026-01-21

### Nouveautés

#### Conditions Générales du Service

- **Checkbox CGV obligatoire** avant achat one-shot dans le modal d'achat
- Lien vers la page CGV ToleryCAD complète

### Notes

La page CGV et la route sont implémentées dans l'application principale (`client/tolerycad/cgv`).
Le checkbox d'acceptation pour les abonnements est également ajouté dans la page subscription.

## v1.1.0 - Admin UI Redesign & DFM Improvements - 2026-01-21

### Nouveautés

#### Interface Admin ToleryCad

- **Refonte complète du tableau des conversations** avec design moderne style Linear/Notion
- **Miniatures screenshots** dans le tableau pour visualiser rapidement les pièces
- **Session ID** avec bouton copier intégré
- **Badges de statut** avec icônes (Générée/En cours/Supprimée)
- **Page de détail redesignée** avec grille d'informations et avatars pour les messages

#### Configuration DFM

- **Rayon extérieur** affiché pour les pliages (en plus du rayon intérieur)
- **Prompts simplifiés** lors des modifications : "Changer X à Y [Face ID: Z]"

### Corrections

- Optimisation des requêtes avec eager loading pour éviter N+1

### Note

La fonctionnalité "poids net" sera ajoutée dans une prochaine version une fois la "surface nette" disponible dans l'API.

## v1.0.0 - First Stable Release 🎉 - 2026-01-19

### 🎉 ToleryCAD v1.0.0 - Première version stable !

#### Fonctionnalités principales

- **Chatbot IA** pour génération de fichiers CAO
  
- **Viewer 3D** avec panneau de configuration interactif
  
- **DFM (Design for Manufacturing)** - Détection et édition des features :
  
  - Perçages, taraudages, oblongs
  - Pliages avec rayon intérieur
  - Fraisures, filets
  
- **Streaming en temps réel** de la génération CAO
  
- **Export STEP et PDF technique**
  
- **Système d'abonnement** intégré (Stripe)
  
- **Admin panel** pour gestion des conversations et prompts
  

#### Améliorations récentes

- Labels simplifiés dans la modal de progression
- Affichage correct des diamètres de perçage (M3 = Ø2.5mm)
- Sélection multi-faces pour oblongs et pliages
- Section taraudage avec sélection M1-M20


---

🤖 Generated with [Claude Code](https://claude.com/claude-code)

## v0.9.0 - DFM Features Support - 2026-01-05

### What's New

#### DFM API Features Integration

Support for semantic features from the DFM/FreeCad JSON API:

| Feature | JSON Type | UI Display |
|---------|-----------|------------|
| Trou lisse | `hole` + `through` | Perçage |
| Trou taraudé | `hole` + `threaded` | Taraudage |
| Fraisurage | `countersink` | Fraisage |
| Arrondi (congé) | `fillet` | Congé |
| Face | `box` | Face |

#### Bug Fixes

- Fixed `threaded` vs `tapped` subtype mapping for threaded holes
- Fixed fillet detection by searching `edge_ids` (not just `face_ids`)
- Added angular span detection as geometric fallback

#### UI Improvements

- Added colored badges for each feature type
- New UI templates for countersink, fillet, and box features
- Updated simple panel with feature type display

## v0.8.6 - 2026-01-04

### What's New

#### Face Context for Edit Panel Regeneration

When modifying hole parameters (diameter, depth) from the config panel, the regeneration request now includes a `[FACE_CONTEXT: ...]` string with full face identification:

- Face ID
- Position (centroid coordinates)
- Bounding box dimensions
- Area
- Feature type (Perçage, Taraudage, etc.)
- Specific metrics (diameter, depth, dimensions)

This enables the chatbot to properly identify which specific feature is being modified, displaying the same "pastille" (chip) as manual face selection.

#### Changes

- New `buildFaceContext()` method in config panel
- Updated `saveEdits()` to include face context in message
- Consistent format with `FaceSelectionManager`

#### Full Changelog

https://github.com/Tolery-Dev/tolery-ai-cad-package/compare/v0.8.5...v0.8.6

## 0.6.1 - 2025-11-06

### What's Changed

* Screenshot coté frontend by @croustibat in https://github.com/Tolery-Dev/tolery-ai-cad-package/pull/31
* Pricing Flexible by @croustibat in https://github.com/Tolery-Dev/tolery-ai-cad-package/pull/32
* Improve UI by @croustibat in https://github.com/Tolery-Dev/tolery-ai-cad-package/pull/33

**Full Changelog**: https://github.com/Tolery-Dev/tolery-ai-cad-package/compare/0.6.0...0.6.1
