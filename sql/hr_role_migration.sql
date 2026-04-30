-- ============================================================
-- hr_role_migration.sql
-- เพิ่ม division + level columns ใน employees table
-- และ update role constraint comment เพื่อรองรับ 'hr'
-- Run ใน SSMS ครั้งเดียว
-- ============================================================

USE mission_token;
GO

-- เพิ่ม division (รหัสแผนก เช่น JD011)
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('dbo.employees') AND name = 'division'
)
    ALTER TABLE dbo.employees ADD division NVARCHAR(20) NULL;
GO

-- เพิ่ม level (ระดับพนักงาน เช่น JL002)
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('dbo.employees') AND name = 'level'
)
    ALTER TABLE dbo.employees ADD level NVARCHAR(10) NULL;
GO

-- role column เดิมรองรับ VARCHAR ได้อยู่แล้ว ไม่ต้อง ALTER
-- ค่าที่รองรับ: 'employee' | 'hr' | 'admin'

-- (Optional) ถ้าต้องการ set role ให้พนักงานที่มีอยู่แล้วใน DB ทันที
-- (ไม่จำเป็นถ้ายังไม่มีใคร login เลย — role จะถูก set อัตโนมัติตอน login)

-- HR dept (JD011, JL002+) → role = 'hr'
-- UPDATE dbo.employees
-- SET role = 'hr'
-- WHERE division = 'JD011' AND level >= 'JL002' AND role <> 'admin';
-- GO

-- IT dept (JD001, ทุกคน) → role = 'it'
-- UPDATE dbo.employees
-- SET role = 'it'
-- WHERE division = 'JD001' AND role <> 'admin';
-- GO
