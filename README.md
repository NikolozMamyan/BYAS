# BYAS — Documentation BDD Phase 1

## 1. Objectif de la base

La base de données de la phase 1 doit permettre de gérer :

- l’authentification utilisateur
- la connexion à plusieurs providers externes
- le calcul d’XP global
- le calcul d’XP par artiste / fandom
- les badges
- la collection déclarative
- le profil public/privé “Passport”
- l’historique d’attribution des points

L’idée centrale est simple :

> un utilisateur peut suivre plusieurs artistes, avec un niveau différent pour chacun.

C’est pour ça que la relation `User ↔ Artist` passe par une table métier dédiée : `user_fandom`.

---

## 2. Vue d’ensemble des tables

### `app_user`

Table principale des comptes utilisateurs.

#### Rôle
Stocke l’identité technique du compte et les stats globales.

#### Champs clés
- `id` : identifiant
- `email` : email unique
- `roles` : rôles Symfony
- `password` : mot de passe, nullable si full OAuth
- `display_name` : nom affiché
- `avatar_url` : avatar
- `global_xp` : XP total
- `global_level` : niveau global
- `is_active` : statut du compte
- `created_at`, `updated_at`

#### Use cases
- création de compte
- login
- affichage du niveau global
- affichage du profil utilisateur

---

### `user_profile`

Profil fonctionnel du “Passport”.

#### Rôle
Sépare les données publiques/privées du compte principal.

#### Champs clés
- `id`
- `user_id` : relation 1–1 avec `app_user`
- `username` : pseudo unique
- `bio`
- `country_code`
- `is_profile_public`
- `show_global_rank`
- `show_collection`
- `show_badges`
- `show_fandom_levels`
- `share_slug`
- `created_at`, `updated_at`

#### Use cases
- page profil publique
- réglages de confidentialité
- lien de partage du Passport

---

### `oauth_account`

Comptes OAuth de connexion.

#### Rôle
Associe un utilisateur à Google, Apple, etc.

#### Champs clés
- `id`
- `user_id`
- `provider` : `google`, `apple`
- `provider_user_id`
- `email`
- `access_token`
- `refresh_token`
- `expires_at`
- `scopes`
- `created_at`, `updated_at`

#### Contraintes
- unique sur (`provider`, `provider_user_id`)

#### Use cases
- login Google
- login Apple
- rattachement d’un compte externe existant

---

### `streaming_account`

Connexions aux plateformes de streaming.

#### Rôle
Permet de récupérer les données Spotify / Apple Music servant au calcul d’XP.

#### Champs clés
- `id`
- `user_id`
- `provider` : `spotify`, `apple_music`
- `provider_user_id`
- `display_name`
- `access_token`
- `refresh_token`
- `expires_at`
- `scopes`
- `last_sync_at`
- `sync_status`
- `created_at`, `updated_at`

#### Contraintes
- unique sur (`provider`, `provider_user_id`)

#### Use cases
- connexion Spotify
- synchronisation de streams
- vérification de l’état de sync

---

### `artist`

Référentiel des artistes / groupes.

#### Rôle
Catalogue des artistes utilisés dans les fandoms, badges, collections et XP.

#### Champs clés
- `id`
- `name`
- `slug`
- `type` : `solo`, `group`
- `country_code`
- `cover_image_url`
- `is_active`
- `created_at`, `updated_at`

#### Contraintes
- `slug` unique

#### Use cases
- rattacher un fandom à un artiste
- afficher un classement par artiste
- rattacher un objet de collection à un artiste

---

### `user_fandom`

Table pivot métier entre utilisateur et artiste.

#### Rôle
Stocke la relation entre un user et un artiste avec ses données de progression.

#### Pourquoi cette table est essentielle
Ce n’est pas un simple many-to-many.  
Elle contient les informations propres au couple `user + artiste`.

#### Champs clés
- `id`
- `user_id`
- `artist_id`
- `xp`
- `level`
- `rank_position`
- `rank_percentile`
- `progress_percent`
- `first_engaged_at`
- `last_xp_at`
- `created_at`, `updated_at`

#### Contraintes
- unique sur (`user_id`, `artist_id`)

#### Use cases
- un utilisateur suit plusieurs artistes
- chaque artiste a un niveau distinct
- affichage du top fan d’un artiste
- affichage des barres de progression par fandom

---

### `xp_transaction`

Historique des gains/pertes d’XP.

#### Rôle
Trace chaque opération de score, global ou liée à un artiste.

#### C’est la table d’audit
Elle permet de savoir :
- pourquoi un user a gagné des points
- quand
- depuis quelle source
- pour quel artiste

#### Champs clés
- `id`
- `user_id`
- `artist_id` nullable
- `user_fandom_id` nullable
- `source_type`
- `source_reference`
- `xp_amount`
- `direction` : `credit`, `debit`
- `reason`
- `metadata` json
- `occurred_at`
- `created_at`

#### Exemples de `source_type`
- `stream_sync`
- `manual_adjustment`
- `bonus`
- `fanclub_declared`
- `physical_album_declared`

#### Use cases
- ajout d’XP après sync Spotify
- audit d’un score
- recalcul d’un niveau
- future analytics

---

### `badge`

Catalogue des badges existants.

#### Rôle
Définit les badges attribuables aux utilisateurs.

#### Champs clés
- `id`
- `code`
- `name`
- `description`
- `icon_url`
- `scope` : `global`, `artist_specific`
- `rule_type`
- `rule_config` json
- `artist_id` nullable
- `is_active`
- `created_at`, `updated_at`

#### Use cases
- badge “Niveau 10”
- badge “Fan depuis 2020”
- badge spécifique à un artiste

---

### `user_badge`

Badges obtenus par les utilisateurs.

#### Rôle
Associe un badge à un utilisateur.

#### Champs clés
- `id`
- `user_id`
- `badge_id`
- `awarded_at`
- `context_data` json

#### Contraintes
- unique sur (`user_id`, `badge_id`)

#### Use cases
- affichage des badges sur le Passport
- historique des récompenses
- attribution automatique après milestone

---

### `collection_item_type`

Types d’objets de collection.

#### Rôle
Normalise les catégories d’objets.

#### Champs clés
- `id`
- `code`
- `label`

#### Exemples
- `physical_album`
- `lightstick`
- `fanclub_membership`
- `vinyl`
- `photocard`

#### Use cases
- catégorisation de la collection utilisateur
- affichage propre par type

---

### `collection_item`

Inventaire déclaratif utilisateur.

#### Rôle
Permet à l’utilisateur de déclarer ce qu’il possède.

#### Champs clés
- `id`
- `user_id`
- `type_id`
- `artist_id` nullable
- `title`
- `description`
- `quantity`
- `is_public`
- `declared_at`
- `metadata` json
- `created_at`, `updated_at`

#### Use cases
- déclarer un album physique
- déclarer un lightstick
- afficher la collection sur le profil
- préparer une future vérification plus avancée

---

## 3. Relations principales

- `app_user` 1–1 `user_profile`
- `app_user` 1–N `oauth_account`
- `app_user` 1–N `streaming_account`
- `app_user` 1–N `user_fandom`
- `artist` 1–N `user_fandom`
- `app_user` 1–N `xp_transaction`
- `artist` 1–N `xp_transaction`
- `user_fandom` 1–N `xp_transaction`
- `app_user` 1–N `user_badge`
- `badge` 1–N `user_badge`
- `app_user` 1–N `collection_item`
- `collection_item_type` 1–N `collection_item`
- `artist` 1–N `collection_item`

---

## 4. Use cases métiers

### UC1 — Inscription / connexion
Un utilisateur crée un compte ou se connecte via Google / Apple.

**Tables impliquées :**
- `app_user`
- `oauth_account`
- `user_profile`

### UC2 — Connexion à Spotify / Apple Music
L’utilisateur relie son compte streaming à BYAS.

**Tables impliquées :**
- `streaming_account`

### UC3 — Synchronisation des streams
Les streams récupérés depuis Spotify / Apple Music sont convertis en XP.

**Tables impliquées :**
- `streaming_account`
- `xp_transaction`
- `app_user`
- `user_fandom`
- `artist`

**Résultat :**
- l’XP global augmente
- l’XP d’un ou plusieurs fandoms augmente
- les niveaux sont recalculés

### UC4 — Affichage du Passport
Le profil affiche :
- niveau global
- rang
- badges
- progression par artiste
- collection

**Tables impliquées :**
- `app_user`
- `user_profile`
- `user_fandom`
- `user_badge`
- `badge`
- `collection_item`

### UC5 — Attribution de badge
Un utilisateur atteint une condition et reçoit une médaille numérique.

**Tables impliquées :**
- `badge`
- `user_badge`
- `app_user` ou `user_fandom` selon la règle

### UC6 — Déclaration d’un objet de collection
L’utilisateur ajoute un album ou un fanclub membership à son inventaire.

**Tables impliquées :**
- `collection_item`
- `collection_item_type`
- `artist`

### UC7 — Audit d’un score
Le système doit être capable d’expliquer un score.

**Tables impliquées :**
- `xp_transaction`

**Exemple de réponse attendue :**  
“+120 XP ajoutés le 20 mars via Spotify pour l’artiste BLACKPINK”.

---

## 5. Règles de conception importantes

### 1. Ne jamais coder “1 user = 1 fandom”
Un utilisateur peut avoir un nombre illimité d’artistes suivis.  
La table `user_fandom` rend cela possible.

### 2. L’historique d’XP est obligatoire
`xp_transaction` est indispensable pour :
- audit
- debug
- recalcul
- analyse future

### 3. Le profil doit anticiper la confidentialité
Les champs publics/privés sont gérés dans `user_profile`.

### 4. Les sources d’XP doivent être extensibles
Le champ `source_type` + `metadata` dans `xp_transaction` permet d’ajouter plus tard :
- tickets scannés
- événements
- perks
- validations partenaires

sans refonte lourde de la base.

---

## 6. Conclusion

La base BYAS phase 1 repose sur deux piliers :

- `user_fandom` pour gérer le niveau par artiste
- `xp_transaction` pour tracer chaque gain de points

Ce modèle est suffisamment simple pour un MVP, mais déjà prêt pour les phases 2, 3 et 4.
