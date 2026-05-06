-- ══════════════════════════════════════════════════════════════
-- coupon_migration.sql
-- Add coupon_code + coupon_expires_at columns to rewards table
-- Run once in SSMS
-- ══════════════════════════════════════════════════════════════

-- Step 1: Add coupon_code (if not already added)
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('dbo.rewards') AND name = 'coupon_code'
)
    ALTER TABLE dbo.rewards ADD coupon_code NVARCHAR(200) NULL;
GO

-- Step 2: Add coupon_expires_at
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('dbo.rewards') AND name = 'coupon_expires_at'
)
    ALTER TABLE dbo.rewards ADD coupon_expires_at DATETIME NULL;
GO
