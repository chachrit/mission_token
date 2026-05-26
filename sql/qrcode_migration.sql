-- ============================================================
-- QR Code Token Claim System
-- Run this script in SSMS against the mission_token database
-- ============================================================

USE mission_token;
GO

-- Table: QR Code master records
CREATE TABLE dbo.token_qr_codes (
    qr_id          INT           IDENTITY(1,1) PRIMARY KEY,
    code           NVARCHAR(64)  NOT NULL UNIQUE,   -- random hex, used in URL
    label          NVARCHAR(200) NOT NULL,            -- event name / description
    token_amount   INT           NOT NULL CHECK (token_amount > 0),
    max_uses       INT           NULL,               -- NULL = unlimited
    per_user_limit INT           NOT NULL DEFAULT 1, -- max claims per employee
    used_count     INT           NOT NULL DEFAULT 0,
    expires_at     DATETIME      NULL,               -- NULL = never expires
    is_active      BIT           NOT NULL DEFAULT 1,
    created_by     INT           NOT NULL REFERENCES dbo.employees(employee_id),
    created_at     DATETIME      NOT NULL DEFAULT GETDATE()
);
GO

-- Table: individual claim records
CREATE TABLE dbo.token_qr_claims (
    claim_id    INT      IDENTITY(1,1) PRIMARY KEY,
    qr_id       INT      NOT NULL REFERENCES dbo.token_qr_codes(qr_id),
    employee_id INT      NOT NULL REFERENCES dbo.employees(employee_id),
    claimed_at  DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT uq_qr_employee UNIQUE (qr_id, employee_id)  -- enforce per_user_limit=1
);
GO

-- Index for fast lookup by code
CREATE INDEX idx_qr_code ON dbo.token_qr_codes(code);
GO
