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

CREATE DATABASE IF NOT EXISTS videoeditserver DEFAULT CHARACTER SET utf8
  COLLATE utf8_unicode_ci;
GRANT SELECT, INSERT, UPDATE, DELETE ON videoeditserver.* TO
  'videoeditserver' IDENTIFIED BY 'Tx2brXmhi5o3' WITH
  MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0;

USE videoeditserver;

DROP TABLE IF EXISTS user;
CREATE TABLE user (
  id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  name varchar(255),     -- may be mapped to an external auth account
  password char(41),     -- if not using external auth
  role tinyint unsigned, -- see PHP enumeration for values
  updated_at timestamp,
  comment varchar(1000)
);
INSERT INTO user (name, password, role) VALUES ('admin', 'password', 3);
