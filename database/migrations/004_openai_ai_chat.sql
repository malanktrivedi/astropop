-- Switch AI chat accounting from the retired VedicAstroAPI AI provider to OpenAI.
-- Provider-specific token usage is stored for audit/cost analysis.

ALTER TABLE ai_chat_usage
    MODIFY provider VARCHAR(64) NOT NULL DEFAULT 'openai',
    ADD COLUMN provider_response_id VARCHAR(160) NULL AFTER provider_request_id,
    ADD COLUMN input_tokens INT UNSIGNED NULL AFTER provider_response_id,
    ADD COLUMN output_tokens INT UNSIGNED NULL AFTER input_tokens,
    ADD COLUMN wallet_ledger_id BIGINT UNSIGNED NULL AFTER user_coins_charged,
    ADD KEY idx_ai_usage_response (provider_response_id),
    ADD KEY idx_ai_usage_ledger (wallet_ledger_id),
    ADD CONSTRAINT fk_ai_usage_ledger FOREIGN KEY (wallet_ledger_id) REFERENCES wallet_ledger(id) ON DELETE SET NULL;
