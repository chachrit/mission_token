-- ============================================================
-- Mission Token — JOURNAL Employee Gamification Platform
-- Database Schema for MS SQL Server
-- Version: 1.0.0  |  April 2026
-- ============================================================

USE master;
GO

-- Create database (Thai collation for proper sorting)
IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = N'mission_token')
    CREATE DATABASE mission_token COLLATE Thai_CI_AS;
GO

USE mission_token;
GO

-- ============================================================
-- DROP TABLES in reverse FK dependency order
-- ============================================================
IF OBJECT_ID('dbo.token_transactions',   'U') IS NOT NULL DROP TABLE dbo.token_transactions;
IF OBJECT_ID('dbo.challenge_submissions','U') IS NOT NULL DROP TABLE dbo.challenge_submissions;
IF OBJECT_ID('dbo.quiz_questions',       'U') IS NOT NULL DROP TABLE dbo.quiz_questions;
IF OBJECT_ID('dbo.token_wallets',        'U') IS NOT NULL DROP TABLE dbo.token_wallets;
IF OBJECT_ID('dbo.challenges',           'U') IS NOT NULL DROP TABLE dbo.challenges;
IF OBJECT_ID('dbo.employees',            'U') IS NOT NULL DROP TABLE dbo.employees;
GO

-- ============================================================
-- Table: employees
-- ============================================================
CREATE TABLE dbo.employees (
    employee_id   INT           IDENTITY(1,1) PRIMARY KEY,
    employee_code NVARCHAR(20)  NOT NULL,
    full_name     NVARCHAR(100) NOT NULL,
    department    NVARCHAR(100) NULL,
    position      NVARCHAR(100) NULL,
    email         NVARCHAR(150) NULL,
    password_hash NVARCHAR(255) NOT NULL,
    role          NVARCHAR(20)  NOT NULL DEFAULT 'employee', -- 'employee' | 'admin'
    avatar_url    NVARCHAR(255) NULL,
    is_active     BIT           NOT NULL DEFAULT 1,
    created_at    DATETIME      NOT NULL DEFAULT GETDATE(),
    CONSTRAINT uq_employee_code UNIQUE (employee_code)
);
GO

-- ============================================================
-- Table: challenges
-- ============================================================
CREATE TABLE dbo.challenges (
    challenge_id  INT           IDENTITY(1,1) PRIMARY KEY,
    title         NVARCHAR(200) NOT NULL,
    description   NVARCHAR(MAX) NULL,
    type          NVARCHAR(20)  NOT NULL,                    -- 'quiz' | 'photo'
    instructions  NVARCHAR(MAX) NULL,
    token_reward  INT           NOT NULL DEFAULT 10,
    start_date    DATE          NOT NULL,
    end_date      DATE          NOT NULL,
    is_active     BIT           NOT NULL DEFAULT 1,
    created_by    INT           NOT NULL,
    created_at    DATETIME      NOT NULL DEFAULT GETDATE(),
    CONSTRAINT fk_challenge_creator FOREIGN KEY (created_by) REFERENCES dbo.employees(employee_id)
);
GO

-- ============================================================
-- Table: quiz_questions
-- ============================================================
CREATE TABLE dbo.quiz_questions (
    question_id    INT           IDENTITY(1,1) PRIMARY KEY,
    challenge_id   INT           NOT NULL,
    question_text  NVARCHAR(MAX) NOT NULL,
    option_a       NVARCHAR(500) NOT NULL,
    option_b       NVARCHAR(500) NOT NULL,
    option_c       NVARCHAR(500) NULL,
    option_d       NVARCHAR(500) NULL,
    correct_option CHAR(1)       NOT NULL, -- 'A' | 'B' | 'C' | 'D'
    explanation    NVARCHAR(MAX) NULL,
    display_order  INT           NOT NULL DEFAULT 1,
    CONSTRAINT fk_question_challenge FOREIGN KEY (challenge_id)
        REFERENCES dbo.challenges(challenge_id) ON DELETE CASCADE
);
GO

-- ============================================================
-- Table: token_wallets  (1:1 with employees)
-- ============================================================
CREATE TABLE dbo.token_wallets (
    wallet_id    INT      IDENTITY(1,1) PRIMARY KEY,
    employee_id  INT      NOT NULL,
    balance      INT      NOT NULL DEFAULT 0,
    total_earned INT      NOT NULL DEFAULT 0,
    total_spent  INT      NOT NULL DEFAULT 0,
    updated_at   DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT uq_wallet_employee UNIQUE (employee_id),
    CONSTRAINT fk_wallet_employee FOREIGN KEY (employee_id)
        REFERENCES dbo.employees(employee_id)
);
GO

-- ============================================================
-- Table: challenge_submissions
-- ============================================================
CREATE TABLE dbo.challenge_submissions (
    submission_id   INT           IDENTITY(1,1) PRIMARY KEY,
    employee_id     INT           NOT NULL,
    challenge_id    INT           NOT NULL,
    submission_type NVARCHAR(20)  NOT NULL,                  -- 'quiz' | 'photo'
    photo_path      NVARCHAR(500) NULL,
    quiz_answer     CHAR(1)       NULL,                      -- submitted answer A/B/C/D
    is_correct      BIT           NULL,
    status          NVARCHAR(20)  NOT NULL DEFAULT 'pending', -- 'auto_approved' | 'pending' | 'approved' | 'rejected'
    token_awarded   INT           NOT NULL DEFAULT 0,
    submitted_at    DATETIME      NOT NULL DEFAULT GETDATE(),
    reviewed_at     DATETIME      NULL,
    reviewed_by     INT           NULL,
    review_note     NVARCHAR(500) NULL,
    CONSTRAINT fk_submission_employee  FOREIGN KEY (employee_id)  REFERENCES dbo.employees(employee_id),
    CONSTRAINT fk_submission_challenge FOREIGN KEY (challenge_id) REFERENCES dbo.challenges(challenge_id),
    CONSTRAINT fk_submission_reviewer  FOREIGN KEY (reviewed_by)  REFERENCES dbo.employees(employee_id)
);
GO

-- ============================================================
-- Table: token_transactions
-- ============================================================
CREATE TABLE dbo.token_transactions (
    tx_id        INT           IDENTITY(1,1) PRIMARY KEY,
    employee_id  INT           NOT NULL,
    amount       INT           NOT NULL,                     -- positive = earned, negative = spent
    tx_type      NVARCHAR(30)  NOT NULL,                    -- 'quiz_reward' | 'photo_reward' | 'admin_adjust' | 'bonus'
    reference_id INT           NULL,                        -- submission_id if applicable
    note         NVARCHAR(300) NULL,
    created_at   DATETIME      NOT NULL DEFAULT GETDATE(),
    CONSTRAINT fk_tx_employee FOREIGN KEY (employee_id) REFERENCES dbo.employees(employee_id)
);
GO

-- ============================================================
-- INDEXES for performance (100-500 users scale)
-- ============================================================
CREATE INDEX idx_submissions_employee   ON dbo.challenge_submissions(employee_id, submitted_at DESC);
CREATE INDEX idx_submissions_status     ON dbo.challenge_submissions(status, submitted_at DESC);
CREATE INDEX idx_submissions_challenge  ON dbo.challenge_submissions(challenge_id);
CREATE INDEX idx_tx_employee            ON dbo.token_transactions(employee_id, created_at DESC);
CREATE INDEX idx_challenges_active      ON dbo.challenges(is_active, start_date, end_date);
CREATE INDEX idx_quiz_challenge         ON dbo.quiz_questions(challenge_id, display_order);
GO

PRINT 'Schema created successfully.';
PRINT 'Next step: Run sql/seed.php in browser to populate initial data.';
GO
