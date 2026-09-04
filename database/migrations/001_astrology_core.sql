ALTER TABLE birth_profiles
    ADD COLUMN location_name VARCHAR(255) NULL AFTER birth_place;

ALTER TABLE kundli_calculations
    ADD COLUMN calculation_hash CHAR(64) NULL AFTER api_response,
    ADD UNIQUE KEY uq_kundli_profile_hash (birth_profile_id, calculation_hash);
