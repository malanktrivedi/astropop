-- ASTROPOP chat, wallet and advisor foundation.
-- Coins are an application billing unit. Payment providers are integrated separately.

CREATE TABLE IF NOT EXISTS wallet_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    wallet_type VARCHAR(32) NOT NULL DEFAULT 'ASTRO_COIN',
    balance DECIMAL(18,4) NOT NULL DEFAULT 0,
    status ENUM('active','locked','closed') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_wallet_user_type (user_id, wallet_type),
    KEY idx_wallet_user (user_id),
    CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_ledger (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    wallet_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    entry_type ENUM('credit','debit','reversal','adjustment','refund') NOT NULL,
    amount DECIMAL(18,4) NOT NULL,
    balance_after DECIMAL(18,4) NOT NULL,
    reference_type VARCHAR(64) NULL,
    reference_id BIGINT UNSIGNED NULL,
    description VARCHAR(255) NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_wallet_ledger_wallet (wallet_id, id),
    KEY idx_wallet_ledger_user (user_id, id),
    KEY idx_wallet_ledger_reference (reference_type, reference_id),
    CONSTRAINT fk_wallet_ledger_wallet FOREIGN KEY (wallet_id) REFERENCES wallet_accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_wallet_ledger_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coin_packages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    coins DECIMAL(18,4) NOT NULL,
    price_inr DECIMAL(12,2) NOT NULL,
    bonus_coins DECIMAL(18,4) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_coin_packages_active (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    coin_package_id BIGINT UNSIGNED NULL,
    provider VARCHAR(40) NOT NULL,
    provider_order_id VARCHAR(160) NULL,
    provider_payment_id VARCHAR(160) NULL,
    amount_inr DECIMAL(12,2) NOT NULL,
    coins DECIMAL(18,4) NOT NULL,
    status ENUM('created','pending','paid','failed','cancelled','refunded') NOT NULL DEFAULT 'created',
    metadata JSON NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_provider_order (provider, provider_order_id),
    KEY idx_payment_user (user_id, id),
    CONSTRAINT fk_payment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_package FOREIGN KEY (coin_package_id) REFERENCES coin_packages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS advisor_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    display_name VARCHAR(160) NOT NULL,
    bio TEXT NULL,
    specialties JSON NULL,
    avatar_url VARCHAR(500) NULL,
    status ENUM('pending','approved','offline','online','suspended','rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_advisor_user (user_id),
    KEY idx_advisor_status (status),
    CONSTRAINT fk_advisor_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS advisor_rates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    advisor_id BIGINT UNSIGNED NOT NULL,
    billing_unit ENUM('minute','message') NOT NULL DEFAULT 'minute',
    coins_per_unit DECIMAL(18,4) NOT NULL,
    minimum_units DECIMAL(18,4) NOT NULL DEFAULT 1,
    active TINYINT(1) NOT NULL DEFAULT 1,
    effective_from TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    effective_to TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_advisor_rates_active (advisor_id, active, effective_from),
    CONSTRAINT fk_advisor_rate_advisor FOREIGN KEY (advisor_id) REFERENCES advisor_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_threads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    mode ENUM('ai','human') NOT NULL,
    advisor_id BIGINT UNSIGNED NULL,
    birth_profile_id BIGINT UNSIGNED NULL,
    kundli_calculation_id BIGINT UNSIGNED NULL,
    status ENUM('open','waiting','active','ended','cancelled','expired') NOT NULL DEFAULT 'open',
    language VARCHAR(32) NULL,
    context_version VARCHAR(32) NULL,
    started_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    last_message_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_chat_user (user_id, id),
    KEY idx_chat_advisor (advisor_id, status, id),
    KEY idx_chat_status (status, last_message_at),
    CONSTRAINT fk_chat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_advisor FOREIGN KEY (advisor_id) REFERENCES advisor_profiles(id) ON DELETE SET NULL,
    CONSTRAINT fk_chat_birth_profile FOREIGN KEY (birth_profile_id) REFERENCES birth_profiles(id) ON DELETE SET NULL,
    CONSTRAINT fk_chat_kundli FOREIGN KEY (kundli_calculation_id) REFERENCES kundli_calculations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id BIGINT UNSIGNED NOT NULL,
    sender_type ENUM('user','advisor','ai','system') NOT NULL,
    sender_user_id BIGINT UNSIGNED NULL,
    message_type ENUM('text','image','file','system') NOT NULL DEFAULT 'text',
    body TEXT NOT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_chat_messages_thread (thread_id, id),
    CONSTRAINT fk_chat_message_thread FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_message_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_usage (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id BIGINT UNSIGNED NOT NULL,
    advisor_id BIGINT UNSIGNED NULL,
    billing_unit ENUM('minute','message') NOT NULL,
    units DECIMAL(18,4) NOT NULL,
    coins_per_unit DECIMAL(18,4) NOT NULL,
    coins_charged DECIMAL(18,4) NOT NULL,
    wallet_ledger_id BIGINT UNSIGNED NULL,
    status ENUM('pending','charged','reversed','failed') NOT NULL DEFAULT 'pending',
    period_started_at TIMESTAMP NULL,
    period_ended_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_chat_usage_thread (thread_id, id),
    KEY idx_chat_usage_advisor (advisor_id, id),
    CONSTRAINT fk_chat_usage_thread FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_usage_advisor FOREIGN KEY (advisor_id) REFERENCES advisor_profiles(id) ON DELETE SET NULL,
    CONSTRAINT fk_chat_usage_ledger FOREIGN KEY (wallet_ledger_id) REFERENCES wallet_ledger(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_chat_usage (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(64) NOT NULL DEFAULT 'vedicastroapi',
    provider_request_id VARCHAR(160) NULL,
    question_id VARCHAR(120) NULL,
    provider_credits DECIMAL(18,4) NULL,
    user_coins_charged DECIMAL(18,4) NOT NULL DEFAULT 0,
    latency_ms INT UNSIGNED NULL,
    status ENUM('pending','completed','failed','reversed') NOT NULL DEFAULT 'pending',
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ai_usage_thread (thread_id, id),
    KEY idx_ai_usage_provider (provider, created_at),
    CONSTRAINT fk_ai_usage_thread FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
