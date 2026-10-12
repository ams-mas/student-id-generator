-- Run this file in phpMyAdmin or the MySQL command line.
CREATE DATABASE IF NOT EXISTS student_id_generator
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE student_id_generator;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
