# ReUse – Give Things a Second Life

Marketplace tunisienne de seconde main en **PHP natif, MySQL (PDO), sessions PHP, HTML5, CSS3, JavaScript, Bootstrap 5**.
Mini-projet DSI2 · Atelier Développement Web · ISET Bizerte · 2026/2027.

---

## 1. Installation (XAMPP)

1. Démarrer **Apache** et **MySQL** dans XAMPP.
2. Copier le dossier `reuse` dans `C:\xampp\htdocs\`.
3. Ouvrir **http://localhost/phpmyadmin** → onglet **Importer** → choisir `database.sql` → **Exécuter**.
   Le fichier crée la base `reuse_db`, les tables, les vues, les procédures, le trigger, l'événement, les catégories et le compte admin.
4. Ouvrir **http://localhost/reuse/**.

Compte admin : `admin@reuse.tn` / `Admin@123`

> Attention : réimporter `database.sql` **efface** toutes les données (il commence par `DROP DATABASE`).

---

## 2. Organisation du code (où trouver quoi)

Chaque page = **un contrôleur** (`page.php` : PHP + SQL) + **une vue** (`views/page.phtml` : HTML).
Le contrôleur termine toujours par `render('page', [...variables...])`.

```
reuse/
├── config/
│   ├── config.php        réglages : base de données, email, cookies, photos
│   └── database.php      connexion PDO : db()
├── includes/             fonctions, rangées par thème
│   ├── functions.php     charge tous les fichiers ci-dessous
│   ├── view.php          render(), partial(), e(), url(), redirect(), flash()
│   ├── security.php      jeton CSRF des formulaires
│   ├── auth.php          connexion, require_login(), "se souvenir de moi", inactivité
│   ├── upload.php        envoi d'images (1 ou plusieurs)
│   ├── format.php        prix, dates, avatars, badges
│   ├── products.php      produits, photos, réservation, droits de messagerie
│   └── mail.php          envoi d'email SMTP en PHP natif
├── views/                HTML seulement (layout, pages, partials, admin)
├── admin/                tableau de bord, membres, annonces, catégories
├── assets/               CSS, JavaScript, images, Bootstrap
├── uploads/              photos envoyées (profils et produits)
├── index.php  login.php  register.php  logout.php
├── forgot_password.php  reset_password.php          ← BONUS mot de passe oublié
├── marketplace.php  product_details.php
├── add_product.php  edit_product.php  delete_product.php  toggle_status.php
├── reserve.php  my_reservations.php                  ← BONUS réservation 24 h
├── my_listings.php  favorites.php  toggle_favorite.php
├── messages.php  send_message.php  profile.php
└── database.sql
```

---

## 3. Fonctionnalités du cahier des charges

| Exigence | Où |
|---|---|
| CRUD complet | annonces : `add_product` (Create), `marketplace` / `product_details` (Read), `edit_product` (Update), `delete_product` (Delete) ; aussi catégories et membres dans `admin/` |
| Authentification | `register.php`, `login.php`, `logout.php`, `includes/auth.php` |
| Photo obligatoire à l'inscription | `register.php` (upload vérifié par type MIME et taille) |
| Annonces visibles seulement après connexion | `require_login()` en haut de chaque page privée |
| Design responsive | Bootstrap 5 + `assets/css/style.css` |
| Fichier .sql | `database.sql` |

---

## 4. BONUS

### 4.1 Mot de passe oublié avec code envoyé par email
1. `forgot_password.php` : l'utilisateur donne son email → un **code à 6 chiffres** est créé.
2. Le code est enregistré **haché** (`password_hash`) dans la table `password_resets`, avec `expires_at` = maintenant + 15 min.
3. L'email est envoyé par `includes/mail.php` (connexion SMTP directe, sans bibliothèque).
4. `reset_password.php` : l'utilisateur tape le code + son nouveau mot de passe.
   - 5 essais ratés maximum (`attempts`), code utilisable une seule fois (`used_at`), 1 envoi par minute.
   - Le message est le même que l'email existe ou non (on ne révèle pas les comptes inscrits).

**Configurer Gmail** (dans `config/config.php`) :
1. Compte Google → Sécurité → activer la **validation en 2 étapes**.
2. Aller sur https://myaccount.google.com/apppasswords → créer un **mot de passe d'application**.
3. Mettre votre adresse dans `MAIL_USERNAME` et `MAIL_FROM`, et les 16 lettres (sans espaces) dans `MAIL_PASSWORD`.

**Mode démo** : si `MAIL_PASSWORD` est vide, aucun email n'est envoyé et le code s'affiche sur la page.
Pratique pour la soutenance sans internet.

### 4.2 Plusieurs photos par annonce
- 1 photo principale (`products.image`) + jusqu'à **5 photos supplémentaires** (table `product_images`, relation 1 → N).
- Formulaire : champ `<input type="file" name="photos[]" multiple>` ; `uploaded_files_list()` remet les fichiers dans l'ordre.
- Modification : cocher une photo pour la supprimer, en ajouter de nouvelles, remplacer la principale.
- Page produit : galerie avec miniatures cliquables ; carte produit : badge "nombre de photos".

### 4.3 Réservation 24 h
Un acheteur réserve l'article : il est bloqué 24 h pour les autres, puis redevient libre tout seul.

- Table `reservations` avec `expires_at` = `NOW() + INTERVAL 24 HOUR`.
- Une réservation est **en cours** si `status = 'active' AND expires_at > NOW()` → dès que l'heure est passée, l'article est libre, même sans rien faire.
- **Procédure stockée `reserver_produit`** : transaction + **`SELECT ... FOR UPDATE`** (verrou de ligne sur le produit).
  Si deux acheteurs cliquent en même temps, le deuxième attend que le premier ait fini, puis reçoit `ALREADY_RESERVED`.
  Elle refuse aussi : son propre article, un article vendu, plus de 3 réservations actives par acheteur.
- **Procédure `liberer_reservations_expirees`** + **événement** MySQL toutes les 10 min (facultatif : `SET GLOBAL event_scheduler = ON;`).
- Le vendeur voit qui a réservé et peut lui écrire ; l'acheteur peut annuler (`my_reservations.php`).

**Démontrer le verrou en soutenance** (2 fenêtres "Shell" de XAMPP, commande `mysql -u root reuse_db`) :
```sql
-- Fenêtre 1 : on prend le verrou sans terminer la transaction
START TRANSACTION;
SELECT * FROM products WHERE id = 1 FOR UPDATE;

-- Fenêtre 2 : cette commande ATTEND (elle est bloquée par la fenêtre 1)
CALL reserver_produit(1, 3);

-- Fenêtre 1 : on libère le verrou -> la fenêtre 2 continue tout de suite
COMMIT;
```
(phpMyAdmin ne convient pas pour cette démo : il ferme la transaction à la fin de chaque requête.)

### 4.4 Autres bonus base de données
| Objet SQL | Rôle |
|---|---|
| Vue `v_products` | produit + catégorie + vendeur + réservation en cours en une seule requête (`SELECT * FROM v_products`) |
| Vue `v_category_stats` | nombre d'annonces par catégorie (accueil, marketplace, admin) |
| Trigger `trg_products_after_update` | enregistre l'ancien prix dans `price_history` quand le prix change (affiché "Price dropped") et termine la réservation quand l'article est vendu |
| Contrainte `CHECK (price >= 0)` | un prix négatif est refusé par MySQL lui-même |
| Clés étrangères `ON DELETE CASCADE` | supprimer un produit supprime ses photos, favoris, messages, réservations, historique |
| Transaction + `FOR UPDATE` | dans `reserver_produit` |

---

## 5. Sécurité

| Risque | Protection |
|---|---|
| Injection SQL | requêtes préparées PDO partout (`?`) |
| XSS | tout affichage passe par `e()` (`htmlspecialchars`) |
| CSRF | jeton secret dans chaque formulaire POST |
| Mots de passe / codes | `password_hash()` / `password_verify()` |
| Force brute | 5 échecs de connexion / 5 min ; 5 essais par code de réinitialisation |
| Images | type MIME réel + taille ≤ 2 Mo + nom aléatoire ; PHP bloqué dans `uploads/` |
| Droits | seul le propriétaire (ou l'admin) modifie / supprime une annonce |

## 6. Cookies avec délai d'expiration
| Cookie | Rôle | Durée |
|---|---|---|
| `remember_me` | "Se souvenir de moi" (jeton haché en base, renouvelé à chaque usage) | 30 jours |
| session PHP | fermée après 30 min d'inactivité | 30 min |
| `pref_sort` | dernier tri choisi dans le marketplace | 30 jours |

## 7. Scénario de test pour la soutenance
1. Créer 2 comptes (2 navigateurs). A publie une annonce avec 3 photos.
2. B réserve l'article → A voit "Reserved by B" et lui écrit ; un 3e compte ne peut plus réserver.
3. A baisse le prix → la page affiche l'ancien prix barré (trigger).
4. A marque l'article vendu → la réservation passe en "completed" (trigger).
5. Se déconnecter → "Forgot password?" → code (email ou mode démo) → nouveau mot de passe.
6. Admin : statistiques (réservations actives, changements de prix), supprimer une annonce, suspendre un membre.

## 8. Hébergement gratuit (InfinityFree...)
Utiliser `database_hosting.sql` (sans `CREATE DATABASE`). Certains hébergeurs gratuits refusent
les procédures stockées, triggers et événements : dans ce cas, la réservation ne fonctionnera pas en ligne ;
tout fonctionne en local avec XAMPP.
