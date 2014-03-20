CREATE TABLE mb_cursor(
-- Primary key
mbc_id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
-- Key
mbc_key varchar(255) binary NOT NULL default '',
-- Value
mbc_value varchar(255) binary NOT NULL default ''
);
