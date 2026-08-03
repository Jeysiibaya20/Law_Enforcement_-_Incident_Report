ALTER TABLE blotters
    ADD COLUMN description_english TEXT NULL AFTER description,
    ADD COLUMN description_language VARCHAR(10) NULL AFTER description_english,
    ADD COLUMN description_translation_provider VARCHAR(30) NULL AFTER description_language;
