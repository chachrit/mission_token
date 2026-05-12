-- Migration: Expand photo_path to NVARCHAR(MAX) for JSON array storage
-- Run this in SSMS before deploying multi-photo upload feature.
--
-- photo_path will now store either:
--   - legacy single filename: "sub_1_2_abc123.jpg"
--   - new JSON array:         ["sub_1_2_aaa.jpg","sub_1_2_bbb.jpg"]

ALTER TABLE dbo.challenge_submissions
    ALTER COLUMN photo_path NVARCHAR(MAX) NULL;
GO
