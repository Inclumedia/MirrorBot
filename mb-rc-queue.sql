CREATE TABLE mb_rc_queue(
-- Primary key
mbrcq_id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
-- mb_text.mbt_id of the row containing the revision text
mbrcq_text_id INT UNSIGNED NOT NULL DEFAULT 0,
-- anon (from api rc)
mbrcq_anon tinyint UNSIGNED NOT NULL DEFAULT 0,
-- contentmodel
mbrcq_content_model varbinary(32) DEFAULT NULL,
-- redirect (from api rc)
mbrcq_redirect tinyint UNSIGNED NOT NULL DEFAULT 0,
-- sha1 (from api rev)
mbrcq_sha1 varbinary(32) NOT NULL DEFAULT '',
-- tags (from api rc)
mbrcq_tags VARCHAR(255) BINARY NOT NULL DEFAULT '',
-- user (from api us). This is the user who is being created, not creating
mbrcq_user_text VARCHAR(255) BINARY NOT NULL,
-- userid (from api us). This is the user who is being created, not creating
mbrcq_user INT UNSIGNED NOT NULL DEFAULT 0,
-- bot
mbrcq_rc_bot tinyint UNSIGNED NOT NULL DEFAULT 0,
-- comment
mbrcq_rc_comment VARCHAR(255) BINARY NOT NULL DEFAULT '',
-- id
mbrcq_rc_id INT UNSIGNED NOT NULL DEFAULT 0,
-- logaction
mbrcq_rc_log_action varbinary(255) NULL DEFAULT NULL,
-- logid
mbrcq_rc_logid INT UNSIGNED NOT NULL,
-- logtype
mbrcq_rc_logtype varbinary(32) NOT NULL DEFAULT '',
-- minor
mbrcq_rc_minor tinyint UNSIGNED NOT NULL DEFAULT 0,
-- new
mbrcq_rc_new tinyint UNSIGNED NOT NULL DEFAULT 0,
-- newlen
mbrcq_rc_new_len INT,
-- ns
mbrcq_rc_namespace INT NOT NULL DEFAULT 0,
-- oldlen
mbrcq_rc_old_len INT,
-- pageid
mbrcq_rc_cur_id INT UNSIGNED NOT NULL DEFAULT 0,
-- patrolled
mbrcq_rc_patrolled tinyint UNSIGNED NOT NULL DEFAULT 0,
-- revid
mbrcq_rc_thisoldid INT UNSIGNED NOT NULL DEFAULT 0,
-- revoldid
mbrcq_rc_lastoldidid INT UNSIGNED NOT NULL DEFAULT 0,
-- timestamp
mbrcq_rc_timestamp varbinary(14) NOT NULL DEFAULT '',
-- title (512, because it is prefixed by the namespace)
mbrcq_rc_title VARCHAR(512) BINARY NOT NULL DEFAULT '',
-- type
mbrcq_rc_type varbinary(255) NULL DEFAULT NULL,
-- userid
mbrcq_rc_user INT UNSIGNED NOT NULL,
-- user
mbrcq_rc_user_text VARCHAR(255) BINARY NOT NULL,
-- result (from push)
mbrcq_push_result VARCHAR(255) BINARY NOT NULL DEFAULT ''
);
-- TODO: Optimize
