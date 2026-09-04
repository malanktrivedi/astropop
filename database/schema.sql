CREATE DATABASE IF NOT EXISTS astropop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE astropop;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS birth_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    profile_name VARCHAR(120) NOT NULL,
    full_name VARCHAR(160) NOT NULL,
    date_of_birth DATE NOT NULL,
    time_of_birth TIME NULL,
    birth_place VARCHAR(255) NOT NULL,
    location_name VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    timezone VARCHAR(64) NULL,
    gender VARCHAR(40) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_birth_profiles_user (user_id),
    CONSTRAINT fk_birth_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kundli_calculations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    birth_profile_id BIGINT UNSIGNED NOT NULL,
    lagna VARCHAR(80) NULL,
    rashi VARCHAR(80) NULL,
    nakshatra VARCHAR(120) NULL,
    planetary_data JSON NULL,
    house_data JSON NULL,
    dasha_data JSON NULL,
    chart_data JSON NULL,
    api_response JSON NULL,
    calculation_hash CHAR(64) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_kundli_user (user_id),
    KEY idx_kundli_profile (birth_profile_id),
    UNIQUE KEY uq_kundli_profile_hash (birth_profile_id, calculation_hash),
    CONSTRAINT fk_kundli_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_kundli_profile FOREIGN KEY (birth_profile_id) REFERENCES birth_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
