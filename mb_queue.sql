CREATE TABLE mb_queue(
-- Primary key
mbq_id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,

-- mb_text.mbt_id of the row containing the revision text, or null revision text
mbq_text_id INT UNSIGNED NOT NULL DEFAULT 0,
-- mb_text.mbt_id of the row containing the redirect revision text
mbq_text_id2 INT UNSIGNED NOT NULL DEFAULT 0,

-- action to take
mbq_action VARCHAR(255) BINARY NOT NULL DEFAULT '',
-- push timestamp
mbq_push_timestamp varbinary(14) NOT NULL DEFAULT '',
-- status
mbq_status VARCHAR(255) BINARY NOT NULL DEFAULT '',
-- second set of params
mbq_params2 blob NULL,

-- log_comment, rc_comment, rev_comment; comment (from api rc)
mbq_comment VARCHAR(255) BINARY NOT NULL DEFAULT '',
-- rev_comment of null and redirect revisions; applicable to page moves
mbq_comment2 VARCHAR(255) BINARY NOT NULL DEFAULT '',
-- log_deleted, rc_deleted, rev_deleted
mbq_deleted tinyint unsigned NOT NULL default 0,
-- rev_len, rc_new_len; newlen (from api rc)
mbq_len INT,
-- log_action, rc_logaction; logaction (from api rc)
mbq_log_action varbinary(255) NULL DEFAULT NULL,
-- log_id, rc_logid; logid (from api rc)
mbq_log_id INT UNSIGNED NOT NULL,
-- log_params, rc_params (from api rc)
mbq_log_params blob NULL,
-- log_type, rc_logtype; logtype (from api rc)
mbq_log_type varbinary(32) NOT NULL DEFAULT '',
-- rc_minor, rev_minor; minor (from api rc)
mbq_minor tinyint UNSIGNED NOT NULL DEFAULT 0,
-- log_namespace, page_namespace, rc_namespace, rev_namespace; ns (from api rc)
mbq_namespace INT NOT NULL DEFAULT 0,
-- page_id, rc_cur_id, rev_page; pageid (from api rc)
mbq_page_id int unsigned NOT NULL,
-- page ID of redirect
mbq_page_id2 int unsigned DEFAULT 0,
-- rc_this_oldid, rev_id; revid (from api rc)
mbq_rev_id INT UNSIGNED NOT NULL DEFAULT 0,
-- redirect rev id (from api rev)
mbq_rev_id2 INT UNSIGNED NOT NULL DEFAULT 0,
-- log_timestamp, rc_timestamp, rev_timestamp; timestamp
mbq_timestamp varbinary(14) NOT NULL DEFAULT '',
-- log_title, page_title, rc_title, title (512, because it is prefixed by the namespace)
mbq_title VARCHAR(512) BINARY NOT NULL DEFAULT '',
-- log_user, rc_user, rev_user
mbq_user INT UNSIGNED NOT NULL DEFAULT 0,
-- log_user_text, rc_user_text, rev_user_text, user_text
mbq_user_text VARCHAR(255) BINARY NOT NULL,

-- rc_anon (from api rc)
mbq_rc_anon tinyint UNSIGNED NOT NULL DEFAULT 0,
-- bot
mbq_rc_bot tinyint UNSIGNED NOT NULL DEFAULT 0,
-- id
mbq_rc_id INT UNSIGNED NOT NULL DEFAULT 0,
-- ip
mbq_rc_ip varbinary(40) NOT NULL default '',
-- revoldid
mbq_rc_last_oldid INT UNSIGNED NOT NULL DEFAULT 0,
-- new
mbq_rc_new tinyint UNSIGNED NOT NULL DEFAULT 0,
-- oldlen
mbq_rc_old_len INT,
-- patrolled
mbq_rc_patrolled tinyint UNSIGNED NOT NULL DEFAULT 0,
-- source
mbq_rc_source varchar(16) binary not null default '',
-- type
mbq_rc_type varbinary(255) NULL DEFAULT NULL,

-- rev_content_model; contentmodel (from api rc)
mbq_rev_content_model varbinary(32) DEFAULT NULL,
-- rev_content_format; contentformat (from api rc)
mbq_rev_content_format varbinary(64) DEFAULT NULL,
-- rev_sha1; sha1 (from api rev)
mbq_rev_sha1 varbinary(32) NOT NULL DEFAULT '',

-- tags (from api rc); not used for anything
mbq_tags VARCHAR(255) BINARY NOT NULL DEFAULT ''
);