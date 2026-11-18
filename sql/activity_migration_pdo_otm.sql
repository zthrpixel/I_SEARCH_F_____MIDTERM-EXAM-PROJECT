-- DIGITAL PRESS: database migration for the existing pdo_otm database
-- BACK UP your database first. Run this once in phpMyAdmin > SQL.
USE pdo_otm;

ALTER TABLE publishers
  ADD COLUMN date_added TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN last_updated_by INT NULL;

ALTER TABLE articles
  ADD COLUMN date_added TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN last_updated_by INT NULL;

ALTER TABLE publishers
  ADD CONSTRAINT fk_publishers_last_updated_by
  FOREIGN KEY (last_updated_by) REFERENCES users(user_id)
  ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE articles
  ADD CONSTRAINT fk_articles_last_updated_by
  FOREIGN KEY (last_updated_by) REFERENCES users(user_id)
  ON DELETE SET NULL ON UPDATE CASCADE;

-- Copy existing audit-user values into the new required columns.
UPDATE publishers SET last_updated_by = updated_by WHERE last_updated_by IS NULL;
UPDATE articles SET last_updated_by = updated_by WHERE last_updated_by IS NULL;

-- For legacy records, use the original creation timestamp where available.
UPDATE publishers SET date_added = created_at;
UPDATE articles SET date_added = created_at;

-- Verification: these should show date_added, last_updated, and last_updated_by.
DESCRIBE publishers;
DESCRIBE articles;

SELECT publisher_id, publisher_name, date_added, last_updated, last_updated_by FROM publishers;
SELECT article_id, title, date_added, last_updated, last_updated_by FROM articles;
