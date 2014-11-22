--
-- Wikimedia Video Editing Server
-- Copyright (C) 2014 Dan Dennedy <dan@dennedy.org>
-- Copyright (C) 2014 C.D.C. Leuphana University Lueneburg

-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see <http://www.gnu.org/licenses/>.
--
-- SCHEMA VERSION 0.1
--

CREATE DATABASE IF NOT EXISTS videoeditserver DEFAULT CHARACTER SET utf8
  COLLATE utf8_unicode_ci;
GRANT SELECT, INSERT, UPDATE, DELETE ON videoeditserver.* TO
  'videoeditserver' IDENTIFIED BY 'Tx2brXmhi5o3' WITH
  MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0;

USE videoeditserver;

DROP TABLE IF EXISTS user;
CREATE TABLE user (
  id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  name varchar(255) NOT NULL, -- may be mapped to an external auth account
  password char(41), -- if not using external auth, should be encrypted
  role tinyint unsigned NOT NULL default 0, -- see constants in PHP for values
  language char(3) DEFAULT 'en', -- ISO 639 code for user interface
  comment varchar(1000),
  access_token varchar(255), -- encrypted
  updated_at timestamp NOT NULL
);
CREATE UNIQUE INDEX user_name ON user (name);
-- If using OAuth, leave this commented out. With OAtuh, the first user to login
-- and register is automatically made a bureaucrat.
-- INSERT INTO user (name, password, role) VALUES ('admin', 'password', 3);

DROP TABLE IF EXISTS file;
CREATE TABLE file (
  id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  user_id int unsigned NOT NULL,
  title varchar(255) NOT NULL,
  author varchar(255) NOT NULL,
  description varchar(1000),
  keywords varchar(1000), -- denormalized, tab-delimited
  properties text, -- denormalized, JSON object
  recording_date date,
  language char(3),
  license varchar(255),
  mime_type varchar(255),
  size_bytes int unsigned,
  duration_ms int unsigned,
  source_path varchar(255), -- storage location of uploaded file
  source_hash char(32), -- md5 hash of source file based on the algorithm Kdenlive uses
  output_path varchar(255), -- storage location of transcode or render result
  output_hash char(32), -- md5 hash of output file based on the algorithm Kdenlive uses
  document_id varchar(32), -- kdenlive's documentid if needed
  publish_uri varchar(32), -- Wikimedia Commons URI
  status bit(64) default 0, -- see constants in PHP for values,
  updated_at timestamp NOT NULL
);
CREATE INDEX file_user_id ON file (user_id);
CREATE INDEX file_recording_date ON file (recording_date);
CREATE INDEX file_language ON file (language);
CREATE INDEX file_license ON file (license);

DROP TABLE IF EXISTS keyword;
CREATE TABLE keyword (
  id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  value varchar(255) NOT NULL,
  language char(3),
  updated_at timestamp NOT NULL
);
CREATE UNIQUE INDEX keyword_value ON keyword (value);
CREATE INDEX keyword_language ON keyword (language);

-- This is currently denormalized as file.keywords. Saving in case change mind.
-- DROP TABLE IF EXISTS file_keywords;
-- CREATE TABLE file_keywords (
--   file_id int unsigned NOT NULL,
--   keyword_id int unsigned NOT NULL,
--   PRIMARY KEY (file_id, keyword_id)
-- );

-- This is currently denormalized as file.properties. Saving in case change mind.
-- DROP TABLE IF EXISTS property;
-- CREATE TABLE property (
--   id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
--   file_id int unsigned NOT NULL,
--   name varchar(255) NOT NULL,
--   value varchar(255) NOT NULL,
--   updated_at timestamp NOT NULL
-- );

DROP TABLE IF EXISTS file_children;
CREATE TABLE file_children (
  file_id int unsigned NOT NULL,
  child_id int unsigned NOT NULL,
  PRIMARY KEY (file_id, child_id)
);
CREATE INDEX file_child_id ON file_children (child_id);

DROP TABLE IF EXISTS recent;
CREATE TABLE recent (
  id bigint unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  file_id int unsigned NOT NULL,
  updated_at timestamp NOT NULL
);
CREATE INDEX recent_file_id ON recent (file_id);

DROP TABLE IF EXISTS file_history;
CREATE TABLE file_history (
  id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  file_id int unsigned NOT NULL,
  revision smallint unsigned NOT NULL default 0,
  is_delete tinyint(1) unsigned NOT NULL default 0,
  deleted_at timestamp NOT NULL,
  comment varchar(255), -- comment about the change
  -- Following are a duplicate of columns from file table. Keep the two in sync.
  user_id int unsigned NOT NULL,
  title varchar(255) NOT NULL,
  author varchar(255) NOT NULL,
  description varchar(1000),
  keywords varchar(1000), -- denormalized, tab-delimited
  properties text, -- denormalized, JSON object
  recording_date date,
  language char(3),
  license varchar(255),
  mime_type varchar(255),
  size_bytes int unsigned,
  duration_ms int unsigned,
  source_path varchar(255), -- storage location of uploaded file
  source_hash char(32), -- md5 hash of source file based on the algorithm Kdenlive uses
  output_path varchar(255), -- storage location of transcode or render result
  output_hash char(32), -- md5 hash of output file based on the algorithm Kdenlive uses
  document_id varchar(32), -- kdenlive's documentid if needed
  publish_uri varchar(32), -- Wikimedia Commons URI
  status bit(64) default 0, -- see constants in PHP for values
  updated_at timestamp NOT NULL
);
CREATE INDEX file_history_file_id ON file_history (file_id);

DROP TABLE IF EXISTS searchindex;
CREATE TABLE searchindex (
  file_id int unsigned NOT NULL PRIMARY KEY,
  title varchar(255) NOT NULL,
  description varchar(1000),
  keywords varchar(1000),
  author varchar(255) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE FULLTEXT INDEX searchindex_title ON searchindex (title);
CREATE FULLTEXT INDEX searchindex_description ON searchindex (description);
CREATE FULLTEXT INDEX searchindex_keywords ON searchindex (keywords);
CREATE FULLTEXT INDEX searchindex_author ON searchindex (author);

DROP TABLE IF EXISTS job;
CREATE TABLE job (
  id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  file_id int unsigned NOT NULL,
  type tinyint unsigned NOT NULL default 0, -- validation, transcode, or render - see PHP constants
  progress tinyint unsigned NOT NULL default 0,
  result int, -- result code from worker process
  log text, -- worker output
  updated_at timestamp NOT NULL
);
CREATE INDEX job_file_id ON job (file_id);

DROP TABLE IF EXISTS session;
CREATE TABLE session (
  session_id varchar(40) DEFAULT '0' NOT NULL,
  ip_address varchar(45) DEFAULT '0' NOT NULL,
  user_agent varchar(120) NOT NULL,
  last_activity int(10) unsigned DEFAULT 0 NOT NULL,
  user_data text NOT NULL,
  PRIMARY KEY (session_id, ip_address, user_agent)
);
CREATE INDEX session_last_activity ON session (last_activity);
