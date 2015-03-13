ALTER TABLE user ADD s3_secret_key varchar(255) AFTER access_token;
ALTER TABLE user ADD s3_access_key varchar(64) AFTER access_token;
