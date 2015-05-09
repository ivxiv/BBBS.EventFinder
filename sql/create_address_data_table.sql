/*
create_address_data_table.sql
Tuesday September 09, 2014 11:51pm Stefan S.

*/

/* ---------- table creation */

CREATE TABLE IF NOT EXISTS address_data (
	id INT NOT NULL AUTO_INCREMENT,
	address TINYTEXT NOT NULL COMMENT 'address',
	address_hash VARCHAR(128) NOT NULL COMMENT 'address hash, up to 128 characters',
	geocode_score DOUBLE NOT NULL COMMENT 'geocode accuracy score',
	spatial_reference INT NOT NULL COMMENT 'spatial reference id',
	x DOUBLE NOT NULL COMMENT 'x coordinate value, based on spatial_reference',
	y DOUBLE NOT NULL COMMENT 'y coordinate value, based on spatial_reference',
	display_x DOUBLE NOT NULL COMMENT 'display x, based on spatial reference WKID 4326',
	display_y DOUBLE NOT NULL COMMENT 'display y, based on spatial reference WKID 4326',
	PRIMARY KEY (id)
) COMMENT='address data table';

/* ---------- index creation */

CREATE UNIQUE INDEX address_hash_index ON address_data (address_hash);
