-- =====================================================================
-- ReUse - Give Things a Second Life
-- Base de données : reuse_db  (MySQL / MariaDB, moteur InnoDB, utf8mb4)
--
-- Import : phpMyAdmin > onglet "Importer" > choisir ce fichier > Exécuter
--      ou : mysql -u root < database.sql
--
-- Contenu :
--   1. Tables          (users, categories, products, product_images, favorites,
--                       messages, remember_tokens, password_resets,
--                       reservations, price_history)
--   2. Vue             v_products        (produit + catégorie + vendeur + réservation)
--   3. Vue             v_category_stats  (statistiques pour le tableau de bord admin)
--   4. Procédures      reserver_produit  (SELECT ... FOR UPDATE + transaction)
--                      liberer_reservations_expirees
--   5. Trigger         trg_products_after_update (historique des prix + vente)
--   6. Événement       ev_liberer_reservations (toutes les 10 minutes)
--   7. Données de départ (catégories + compte admin)
-- =====================================================================

DROP DATABASE IF EXISTS reuse_db;
CREATE DATABASE reuse_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE reuse_db;

-- =====================================================================
-- 1. TABLES
-- =====================================================================

-- USERS : les membres. role = 'user' (membre) ou 'admin'
CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(100)  NOT NULL,
    email           VARCHAR(150)  NOT NULL UNIQUE,
    password        VARCHAR(255)  NOT NULL,               -- résultat de password_hash()
    phone           VARCHAR(20)   DEFAULT NULL,
    city            VARCHAR(60)   DEFAULT NULL,
    bio             VARCHAR(500)  DEFAULT NULL,
    profile_picture VARCHAR(255)  NOT NULL,               -- nom du fichier dans uploads/profiles/
    role            ENUM('user','admin') NOT NULL DEFAULT 'user',
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,      -- 0 = compte suspendu par l'admin
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- CATEGORIES : gérées par l'admin
CREATE TABLE categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80)  NOT NULL UNIQUE,
    slug        VARCHAR(80)  NOT NULL UNIQUE,
    icon        VARCHAR(50)  NOT NULL DEFAULT 'bi-box',   -- classe Bootstrap Icons
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- PRODUCTS : les annonces
--   user_id     -> users.id       (vendeur ; supprimer le vendeur supprime ses annonces)
--   category_id -> categories.id  (impossible de supprimer une catégorie utilisée)
CREATE TABLE products (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED  NOT NULL,
    category_id    INT UNSIGNED  NOT NULL,
    title          VARCHAR(120)  NOT NULL,
    description    TEXT          NOT NULL,
    price          DECIMAL(10,3) NOT NULL,                 -- le dinar a 3 décimales (millimes)
    item_condition ENUM('New','Like New','Good','Used') NOT NULL,
    location       VARCHAR(60)   NOT NULL,                 -- gouvernorat
    image          VARCHAR(255)  NOT NULL,                 -- photo PRINCIPALE (uploads/products/)
    status         ENUM('available','sold') NOT NULL DEFAULT 'available',
    views          INT UNSIGNED  NOT NULL DEFAULT 0,
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_user     FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    CONSTRAINT chk_products_price   CHECK (price >= 0),    -- un prix ne peut pas être négatif
    INDEX idx_products_status (status),
    INDEX idx_products_price (price)
) ENGINE=InnoDB;

-- PRODUCT_IMAGES : photos SUPPLÉMENTAIRES d'une annonce (relation 1 produit -> N photos)
CREATE TABLE product_images (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  INT UNSIGNED NOT NULL,
    filename    VARCHAR(255) NOT NULL,                     -- nom du fichier dans uploads/products/
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- FAVORITES : relation N-N entre users et products
CREATE TABLE favorites (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    product_id  INT UNSIGNED NOT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fav_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_fav_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uq_fav (user_id, product_id)                -- un produit une seule fois par membre
) ENGINE=InnoDB;

-- MESSAGES : conversations acheteur <-> vendeur à propos d'un produit
CREATE TABLE messages (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id    INT UNSIGNED NOT NULL,
    receiver_id  INT UNSIGNED NOT NULL,
    product_id   INT UNSIGNED NOT NULL,
    body         TEXT         NOT NULL,
    is_read      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_msg_sender   FOREIGN KEY (sender_id)   REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_msg_receiver FOREIGN KEY (receiver_id) REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_msg_product  FOREIGN KEY (product_id)  REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_msg_receiver (receiver_id, is_read)
) ENGINE=InnoDB;

-- REMEMBER_TOKENS : cookie "Se souvenir de moi" (30 jours).
-- On stocke seulement le hash SHA-256 du secret, jamais le secret lui-même.
CREATE TABLE remember_tokens (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    selector    CHAR(18)     NOT NULL UNIQUE,
    token_hash  CHAR(64)     NOT NULL,
    expires_at  DATETIME     NOT NULL,
    CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_remember_expires (expires_at)
) ENGINE=InnoDB;

-- PASSWORD_RESETS : codes à 6 chiffres envoyés par email ("mot de passe oublié").
-- Le code est haché (comme un mot de passe), expire après 15 minutes,
-- et on compte les essais ratés (max 5).
CREATE TABLE password_resets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    code_hash   VARCHAR(255) NOT NULL,
    attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME     DEFAULT NULL,                  -- rempli quand le code a servi
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- RESERVATIONS : un acheteur bloque un article pendant 24 h.
--   Une réservation est "en cours" si status = 'active' ET expires_at > NOW().
--   Après expires_at, l'article redevient libre automatiquement.
CREATE TABLE reservations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  INT UNSIGNED NOT NULL,
    buyer_id    INT UNSIGNED NOT NULL,
    status      ENUM('active','cancelled','completed','expired') NOT NULL DEFAULT 'active',
    expires_at  DATETIME     NOT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_resa_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_resa_buyer   FOREIGN KEY (buyer_id)   REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_resa_active (product_id, status, expires_at)
) ENGINE=InnoDB;

-- PRICE_HISTORY : rempli AUTOMATIQUEMENT par le trigger quand un vendeur change un prix
CREATE TABLE price_history (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  INT UNSIGNED  NOT NULL,
    old_price   DECIMAL(10,3) NOT NULL,
    new_price   DECIMAL(10,3) NOT NULL,
    changed_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_price_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 2. VUE v_products
-- Une seule requête pour avoir un produit avec sa catégorie, son vendeur
-- et sa réservation en cours. Le PHP fait simplement : SELECT * FROM v_products
-- =====================================================================
CREATE VIEW v_products AS
SELECT p.*,
       c.name            AS category_name,
       c.icon            AS category_icon,
       u.full_name       AS seller_name,
       u.profile_picture AS seller_picture,
       u.city            AS seller_city,
       u.phone           AS seller_phone,
       u.created_at      AS seller_since,
       u.is_active       AS seller_active,
       r.buyer_id        AS reserved_by,       -- NULL si personne n'a réservé
       r.expires_at      AS reserved_until,
       (SELECT COUNT(*) FROM product_images i WHERE i.product_id = p.id) + 1 AS photo_count
FROM products p
JOIN categories c ON c.id = p.category_id
JOIN users u      ON u.id = p.user_id
LEFT JOIN reservations r
       ON r.product_id = p.id
      AND r.status = 'active'
      AND r.expires_at > NOW();

-- =====================================================================
-- 3. VUE v_category_stats : nombre d'annonces par catégorie (dashboard admin)
-- =====================================================================
CREATE VIEW v_category_stats AS
SELECT c.id, c.name, c.icon,
       COUNT(p.id)                                  AS total,
       COALESCE(SUM(p.status = 'available'), 0)     AS available,
       COALESCE(SUM(p.status = 'sold'), 0)          AS sold
FROM categories c
LEFT JOIN products p ON p.category_id = c.id
GROUP BY c.id, c.name, c.icon;

-- =====================================================================
-- 4. PROCÉDURES STOCKÉES
-- =====================================================================
DELIMITER $$

-- ---------------------------------------------------------------------
-- reserver_produit(produit, acheteur)
-- Réserve un article pendant 24 h. Retourne une ligne avec la colonne
-- "result" : OK, NOT_FOUND, OWN_PRODUCT, SOLD, ALREADY_RESERVED, LIMIT_REACHED
--
-- Pourquoi SELECT ... FOR UPDATE ?
--   Si deux acheteurs cliquent "Réserver" exactement en même temps, le
--   premier verrouille la ligne du produit. Le deuxième ATTEND la fin de
--   la transaction du premier, puis voit que l'article est déjà réservé.
--   => impossible de réserver le même article à deux.
-- ---------------------------------------------------------------------
CREATE PROCEDURE reserver_produit(IN p_product_id INT UNSIGNED, IN p_buyer_id INT UNSIGNED)
BEGIN
    DECLARE v_seller_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_status    VARCHAR(20)  DEFAULT NULL;
    DECLARE v_result    VARCHAR(30)  DEFAULT 'OK';

    -- En cas d'erreur SQL : on annule tout et on renvoie l'erreur
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    -- 1) Verrou de ligne sur le produit (les autres transactions attendent ici)
    SELECT user_id, status INTO v_seller_id, v_status
    FROM products
    WHERE id = p_product_id
    FOR UPDATE;

    -- 2) Les réservations dépassées passent en 'expired'
    UPDATE reservations
    SET status = 'expired'
    WHERE product_id = p_product_id AND status = 'active' AND expires_at <= NOW();

    -- 3) Vérifications
    IF v_seller_id IS NULL THEN
        SET v_result = 'NOT_FOUND';
    ELSEIF v_seller_id = p_buyer_id THEN
        SET v_result = 'OWN_PRODUCT';
    ELSEIF v_status = 'sold' THEN
        SET v_result = 'SOLD';
    ELSEIF EXISTS (SELECT 1 FROM reservations
                   WHERE product_id = p_product_id AND status = 'active') THEN
        SET v_result = 'ALREADY_RESERVED';
    ELSEIF (SELECT COUNT(*) FROM reservations
            WHERE buyer_id = p_buyer_id AND status = 'active' AND expires_at > NOW()) >= 3 THEN
        SET v_result = 'LIMIT_REACHED';                        -- max 3 réservations par acheteur
    END IF;

    -- 4) Tout est bon : on crée la réservation pour 24 h
    IF v_result = 'OK' THEN
        INSERT INTO reservations (product_id, buyer_id, expires_at)
        VALUES (p_product_id, p_buyer_id, NOW() + INTERVAL 24 HOUR);
        COMMIT;
    ELSE
        ROLLBACK;
    END IF;

    SELECT v_result AS result;
END $$

-- ---------------------------------------------------------------------
-- liberer_reservations_expirees()
-- Passe en 'expired' toutes les réservations dont les 24 h sont terminées.
-- Appelée par l'événement ci-dessous ET par le PHP (au cas où le
-- planificateur d'événements de MySQL serait désactivé).
-- ---------------------------------------------------------------------
CREATE PROCEDURE liberer_reservations_expirees()
BEGIN
    UPDATE reservations
    SET status = 'expired'
    WHERE status = 'active' AND expires_at <= NOW();
END $$

-- =====================================================================
-- 5. TRIGGER sur products (après chaque UPDATE)
--   a) le prix change  -> on garde l'ancien prix dans price_history
--   b) l'article est vendu -> sa réservation en cours devient 'completed'
-- =====================================================================
CREATE TRIGGER trg_products_after_update
AFTER UPDATE ON products
FOR EACH ROW
BEGIN
    IF NEW.price <> OLD.price THEN
        INSERT INTO price_history (product_id, old_price, new_price)
        VALUES (NEW.id, OLD.price, NEW.price);
    END IF;

    IF NEW.status = 'sold' AND OLD.status <> 'sold' THEN
        UPDATE reservations
        SET status = 'completed'
        WHERE product_id = NEW.id AND status = 'active';
    END IF;
END $$

DELIMITER ;

-- =====================================================================
-- 6. ÉVÉNEMENT : libère les réservations expirées toutes les 10 minutes.
-- Pour l'activer dans XAMPP, exécuter une fois (onglet SQL) :
--     SET GLOBAL event_scheduler = ON;
-- Ce n'est pas obligatoire : le site vérifie déjà expires_at > NOW().
-- =====================================================================
CREATE EVENT ev_liberer_reservations
ON SCHEDULE EVERY 10 MINUTE
DO CALL liberer_reservations_expirees();

-- =====================================================================
-- 7. DONNÉES DE DÉPART
-- =====================================================================
INSERT INTO categories (name, slug, icon) VALUES
('Clothing & Shoes',       'clothing-shoes',  'bi-bag-heart'),
('Electronics',            'electronics',     'bi-phone'),
('Books & Education',      'books-education', 'bi-book'),
('Furniture & Decoration', 'furniture-decor', 'bi-lamp'),
('Sports Equipment',       'sports',          'bi-bicycle'),
('Other',                  'other',           'bi-grid');

-- Administrateur par défaut
--   email        : admin@reuse.tn
--   mot de passe : Admin@123   (à changer après la première connexion)
INSERT INTO users (full_name, email, password, city, profile_picture, role) VALUES
('ReUse Admin', 'admin@reuse.tn',
 '$2y$10$wBltrKi2FmSUC7ScR/UoNeYi3sCrThbMDpTeQvH671umzwPaOxILO',
 'Tunis', 'default-avatar.png', 'admin');
