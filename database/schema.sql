CREATE TABLE IF NOT EXISTS webhooks
(
    id              VARCHAR(128) PRIMARY KEY,
    target_url      VARCHAR(2048) NOT NULL,
    payload         JSON          NOT NULL,
    status          VARCHAR(32)   NOT NULL,
    attempts        INT UNSIGNED  NOT NULL DEFAULT 0,
    last_error      TEXT          NULL,
    next_attempt_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    claimed_by      VARCHAR(64)   NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
