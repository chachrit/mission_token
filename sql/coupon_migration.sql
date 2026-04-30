-- ══════════════════════════════════════════════════════════════
-- coupon_migration.sql
-- Add coupon_code column to rewards table
-- Run once in SSMS or phpMyAdmin equivalent
-- ══════════════════════════════════════════════════════════════

ALTER TABLE dbo.rewards
ADD coupon_code NVARCHAR(200) NULL;
GO
