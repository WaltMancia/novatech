CREATE TABLE products (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sku          VARCHAR(32)     NOT NULL,
  name         VARCHAR(120)    NOT NULL,
  description  TEXT            NOT NULL,
  price_cents  INT UNSIGNED    NOT NULL,
  stock        INT UNSIGNED    NOT NULL DEFAULT 0,
  image_path   VARCHAR(255)    NULL,
  is_active    TINYINT(1)      NOT NULL DEFAULT 1,
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_products_sku UNIQUE (sku),
  CONSTRAINT ck_products_price CHECK (price_cents > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
