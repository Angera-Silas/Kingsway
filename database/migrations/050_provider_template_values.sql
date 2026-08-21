CREATE TABLE IF NOT EXISTS communication_template_values (
    communication_id INT UNSIGNED NOT NULL,
    ordinal_no INT UNSIGNED NOT NULL,
    variable_name VARCHAR(100) NULL,
    variable_value TEXT NULL,
    PRIMARY KEY (communication_id, ordinal_no),
    CONSTRAINT fk_comm_template_value_communication
        FOREIGN KEY (communication_id) REFERENCES communications(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
