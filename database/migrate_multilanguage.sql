-- Jalankan pada database yang sudah ada. Query ini aman diulang.
SET @db_name = DATABASE();

SET @add_lang_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pages' AND COLUMN_NAME = 'lang') = 0,
    'ALTER TABLE pages ADD COLUMN lang VARCHAR(10) NOT NULL DEFAULT ''id''',
    'SELECT 1'
);
PREPARE add_lang_stmt FROM @add_lang_sql;
EXECUTE add_lang_stmt;
DEALLOCATE PREPARE add_lang_stmt;

SET @add_translation_group_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pages' AND COLUMN_NAME = 'translation_group') = 0,
    'ALTER TABLE pages ADD COLUMN translation_group VARCHAR(100) NULL',
    'SELECT 1'
);
PREPARE add_translation_group_stmt FROM @add_translation_group_sql;
EXECUTE add_translation_group_stmt;
DEALLOCATE PREPARE add_translation_group_stmt;
