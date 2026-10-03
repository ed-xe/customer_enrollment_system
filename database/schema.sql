CREATE DATABASE IF NOT EXISTS serverdata
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE serverdata;

CREATE TABLE IF NOT EXISTS customerlist (
    Code VARCHAR(255) NOT NULL,
    Firstname VARCHAR(255) NOT NULL,
    Lastname VARCHAR(255) NOT NULL,
    PRIMARY KEY (Code)
) CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
