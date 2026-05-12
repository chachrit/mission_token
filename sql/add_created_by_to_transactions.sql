-- Migration: add created_by to token_transactions
-- Run once in SSMS

IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'token_transactions' AND COLUMN_NAME = 'created_by'
)
BEGIN
    ALTER TABLE dbo.token_transactions
        ADD created_by INT NULL
            CONSTRAINT fk_tx_created_by FOREIGN KEY REFERENCES dbo.employees(employee_id);
END
GO
