-- Purpose-specific channel permissions for incoming collection routes.
-- This prevents an account-level cash permission from making cash valid for fees.
CREATE TABLE IF NOT EXISTS payment_collection_route_channels (
    route_id BIGINT UNSIGNED NOT NULL,
    channel_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (route_id, channel_id),
    CONSTRAINT fk_collection_route_channel_route FOREIGN KEY (route_id) REFERENCES payment_collection_routes(id) ON DELETE CASCADE,
    CONSTRAINT fk_collection_route_channel_channel FOREIGN KEY (channel_id) REFERENCES financial_channels(id),
    KEY idx_collection_route_channel_channel (channel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO payment_collection_route_channels (route_id, channel_id)
SELECT r.id, ac.channel_id
FROM payment_collection_routes r
JOIN school_financial_account_channels ac ON ac.financial_account_id = r.financial_account_id
JOIN financial_channels c ON c.id = ac.channel_id
WHERE r.purpose <> 'fees' OR c.code <> 'cash';
