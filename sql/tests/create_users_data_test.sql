CREATE TABLE IF NOT EXISTS `users_data_test` LIKE `users_data`;

DELIMITER $$
DROP PROCEDURE IF EXISTS `make_users_data_test_insert_friendly`$$
CREATE PROCEDURE `make_users_data_test_insert_friendly`()
BEGIN
    DECLARE finished INT DEFAULT 0;
    DECLARE col_name VARCHAR(255);
    DECLARE col_type TEXT;
    DECLARE col_default TEXT;
    DECLARE col_extra TEXT;
    DECLARE col_nullable VARCHAR(3);
    DECLARE col_key VARCHAR(3);
    DECLARE cur CURSOR FOR
        SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT, EXTRA, IS_NULLABLE, COLUMN_KEY
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_data_test';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET finished = 1;

    OPEN cur;
    alter_loop: LOOP
        FETCH cur INTO col_name, col_type, col_default, col_extra, col_nullable, col_key;
        IF finished = 1 THEN
            LEAVE alter_loop;
        END IF;
        IF col_nullable = 'NO' AND col_key <> 'PRI' AND col_default IS NULL AND col_extra NOT LIKE '%generated%'
           AND col_extra NOT LIKE '%auto_increment%' THEN
            SET @alter_sql = CONCAT('ALTER TABLE `users_data_test` MODIFY `', REPLACE(col_name, '`', '``'), '` ', col_type, ' NULL');
            PREPARE alter_stmt FROM @alter_sql;
            EXECUTE alter_stmt;
            DEALLOCATE PREPARE alter_stmt;
        END IF;
    END LOOP;
    CLOSE cur;
END$$
CALL `make_users_data_test_insert_friendly`()$$
DROP PROCEDURE `make_users_data_test_insert_friendly`$$
DELIMITER ;
