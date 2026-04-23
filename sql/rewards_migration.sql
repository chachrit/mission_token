-- ============================================================
-- Mission Token — Rewards System Migration
-- Run this script once on the mission_token database
-- ============================================================

USE mission_token;
GO

-- ============================================================
-- Table: rewards
-- Each record is a redeemable reward item managed by admin
-- ============================================================
IF OBJECT_ID('dbo.reward_redemptions', 'U') IS NOT NULL
    DROP TABLE dbo.reward_redemptions;
IF OBJECT_ID('dbo.rewards', 'U') IS NOT NULL
    DROP TABLE dbo.rewards;
GO

CREATE TABLE dbo.rewards (
    reward_id    INT           IDENTITY(1,1) PRIMARY KEY,
    title        NVARCHAR(200) NOT NULL,
    description  NVARCHAR(MAX) NULL,
    image_emoji  NVARCHAR(10)  NOT NULL DEFAULT N'🎁',
    category     NVARCHAR(50)  NOT NULL DEFAULT 'general',
    -- category values: 'voucher' | 'leave' | 'merch' | 'perk' | 'general'
    token_cost   INT           NOT NULL DEFAULT 50,
    stock        INT           NULL,     -- NULL = unlimited
    is_active    BIT           NOT NULL DEFAULT 1,
    created_by   INT           NOT NULL,
    created_at   DATETIME      NOT NULL DEFAULT GETDATE(),
    CONSTRAINT fk_reward_creator FOREIGN KEY (created_by)
        REFERENCES dbo.employees(employee_id)
);
GO

-- ============================================================
-- Table: reward_redemptions
-- Each record is one redemption request from an employee
-- ============================================================
CREATE TABLE dbo.reward_redemptions (
    redemption_id INT           IDENTITY(1,1) PRIMARY KEY,
    employee_id   INT           NOT NULL,
    reward_id     INT           NOT NULL,
    tokens_spent  INT           NOT NULL,
    status        NVARCHAR(20)  NOT NULL DEFAULT 'pending',
    -- status values: 'pending' | 'fulfilled' | 'cancelled'
    redeemed_at   DATETIME      NOT NULL DEFAULT GETDATE(),
    processed_at  DATETIME      NULL,
    processed_by  INT           NULL,
    admin_note    NVARCHAR(500) NULL,
    CONSTRAINT fk_redemption_employee FOREIGN KEY (employee_id)
        REFERENCES dbo.employees(employee_id),
    CONSTRAINT fk_redemption_reward   FOREIGN KEY (reward_id)
        REFERENCES dbo.rewards(reward_id),
    CONSTRAINT fk_redemption_admin    FOREIGN KEY (processed_by)
        REFERENCES dbo.employees(employee_id)
);
GO

-- ============================================================
-- Indexes
-- ============================================================
CREATE INDEX idx_rewards_active       ON dbo.rewards(is_active, category, created_at);
CREATE INDEX idx_redemptions_employee ON dbo.reward_redemptions(employee_id, redeemed_at DESC);
CREATE INDEX idx_redemptions_status   ON dbo.reward_redemptions(status, redeemed_at DESC);
CREATE INDEX idx_redemptions_reward   ON dbo.reward_redemptions(reward_id);
GO

-- ============================================================
-- Seed: sample rewards  (admin employee_id = 1 assumed)
-- Adjust created_by if needed
-- ============================================================
DECLARE @adminId INT;
SELECT TOP 1 @adminId = employee_id FROM dbo.employees WHERE role = 'admin';

IF @adminId IS NOT NULL
BEGIN
    INSERT INTO dbo.rewards (title, description, image_emoji, category, token_cost, stock, created_by) VALUES
    (N'คูปองกาแฟ',           N'คูปองซื้อเครื่องดื่มฟรี 1 แก้ว ร้านกาแฟในออฟฟิศ', N'☕', 'voucher', 30,  20,  @adminId),
    (N'คูปองอาหารกลางวัน',    N'คูปองมื้อเที่ยงฟรี ในโรงอาหาร', N'🍱', 'voucher', 60, 10, @adminId),
    (N'วันลาพิเศษ 1 วัน',     N'วันลาพักผ่อนเพิ่มพิเศษ 1 วัน (ใช้ได้ภายใน 3 เดือน)', N'🏖️', 'leave', 200, 5, @adminId),
    (N'ถุงผ้า JOURNAL',       N'ถุงผ้าแคนวาสลาย JOURNAL Limited Edition', N'👜', 'merch', 80, 15, @adminId),
    (N'สติ๊กเกอร์เซต',        N'สติ๊กเกอร์ JOURNAL แบบพิเศษ 1 เซต (12 แผ่น)', N'🎨', 'merch', 40, NULL, @adminId),
    (N'นั่งโต๊ะ Director 1 วัน', N'สิทธิ์นั่งโต๊ะห้อง Director พร้อมวิว 1 วัน', N'✨', 'perk', 150, 3, @adminId),
    (N'วันเกิดบน WFH',        N'ทำงานจากบ้านได้ในวันเกิดของคุณ', N'🎂', 'perk', 100, NULL, @adminId),
    (N'โน้ตบุ๊ก JOURNAL',     N'สมุดโน้ตปกแข็ง พร้อมปากกา JOURNAL', N'📒', 'merch', 70, 30, @adminId);
END
GO

PRINT 'Rewards migration completed successfully.';
GO
