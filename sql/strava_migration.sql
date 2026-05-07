-- ============================================================
-- Strava Integration Migration
-- Run this in SSMS against mission_token database
-- ============================================================

USE mission_token;
GO

-- Add Strava OAuth columns to employees (each statement separated by GO)
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.employees') AND name = 'strava_athlete_id')
BEGIN
    ALTER TABLE dbo.employees ADD strava_athlete_id BIGINT NULL;
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.employees') AND name = 'strava_access_token')
BEGIN
    ALTER TABLE dbo.employees ADD strava_access_token NVARCHAR(500) NULL;
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.employees') AND name = 'strava_refresh_token')
BEGIN
    ALTER TABLE dbo.employees ADD strava_refresh_token NVARCHAR(500) NULL;
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.employees') AND name = 'strava_scope')
BEGIN
    ALTER TABLE dbo.employees ADD strava_scope NVARCHAR(200) NULL;
END
GO

-- Add strava_token_expires_at as BIGINT (Unix timestamp)
-- If it was accidentally created as a date/time type, drop and recreate it
IF EXISTS (
    SELECT 1 FROM sys.columns c
    JOIN sys.types t ON c.user_type_id = t.user_type_id
    WHERE c.object_id = OBJECT_ID('dbo.employees')
      AND c.name = 'strava_token_expires_at'
      AND t.name <> 'bigint'
)
BEGIN
    ALTER TABLE dbo.employees DROP COLUMN strava_token_expires_at;
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.employees') AND name = 'strava_token_expires_at')
BEGIN
    ALTER TABLE dbo.employees ADD strava_token_expires_at BIGINT NULL;  -- Unix timestamp (seconds since epoch)
END
GO

-- Add strava_condition column to challenges
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.challenges') AND name = 'strava_condition')
BEGIN
    ALTER TABLE dbo.challenges ADD strava_condition NVARCHAR(1000) NULL;
END
GO

PRINT 'Strava migration completed successfully.';
