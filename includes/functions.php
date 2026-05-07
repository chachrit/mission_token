<?php
/**
 * includes/functions.php
 * Core business logic: token economy, wallet, challenges
 */

require_once __DIR__ . '/../config/database.php';

// ============================================================
// Token Economy
// ============================================================

/**
 * Award (or deduct) tokens for an employee.
 * Wraps INSERT token_transactions + UPDATE token_wallets in a DB transaction.
 *
 * @param int    $employeeId
 * @param int    $amount       Positive = earn, negative = spend
 * @param string $txType       'quiz_reward' | 'photo_reward' | 'admin_adjust' | 'bonus'
 * @param int|null $referenceId submission_id if applicable
 * @param string $note
 * @return bool
 */
function awardTokens(int $employeeId, int $amount, string $txType, ?int $referenceId = null, string $note = ''): bool
{
    $pdo = getDB();
    try {
        $pdo->beginTransaction();

        // Record transaction
        $stmt = $pdo->prepare("
            INSERT INTO token_transactions (employee_id, amount, tx_type, reference_id, note)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$employeeId, $amount, $txType, $referenceId, $note]);

        // Update wallet
        if ($txType === 'admin_adjust') {
            // Admin adjustments only affect current balance — not earned/spent stats
            $stmt = $pdo->prepare("
                UPDATE token_wallets
                SET balance    = balance + ?,
                    updated_at = GETDATE()
                WHERE employee_id = ?
            ");
            $stmt->execute([$amount, $employeeId]);
        } elseif ($amount >= 0) {
            $stmt = $pdo->prepare("
                UPDATE token_wallets
                SET balance      = balance + ?,
                    total_earned = total_earned + ?,
                    updated_at   = GETDATE()
                WHERE employee_id = ?
            ");
            $stmt->execute([$amount, $amount, $employeeId]);
        } else {
            $abs = abs($amount);
            $stmt = $pdo->prepare("
                UPDATE token_wallets
                SET balance     = balance + ?,
                    total_spent = total_spent + ?,
                    updated_at  = GETDATE()
                WHERE employee_id = ?
            ");
            $stmt->execute([$amount, $abs, $employeeId]);
        }

        $pdo->commit();

        // Refresh session balance cache
        if (isset($_SESSION['employee_id']) && (int)$_SESSION['employee_id'] === $employeeId) {
            $_SESSION['token_balance'] = getWalletBalance($employeeId);
        }

        return true;

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('[MissionToken] awardTokens error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get current token balance for an employee.
 */
function getWalletBalance(int $employeeId): int
{
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT balance FROM token_wallets WHERE employee_id = ?");
    $stmt->execute([$employeeId]);
    $row = $stmt->fetch();
    return $row ? (int)$row['balance'] : 0;
}

/**
 * Get full wallet info: balance, total_earned, total_spent.
 */
function getWalletInfo(int $employeeId): array
{
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT balance, total_earned, total_spent FROM token_wallets WHERE employee_id = ?");
    $stmt->execute([$employeeId]);
    return $stmt->fetch() ?: ['balance' => 0, 'total_earned' => 0, 'total_spent' => 0];
}

/**
 * Format token number with Thai-style comma separator.
 */
function formatTokens(int $amount): string
{
    return number_format($amount);
}

// ============================================================
// Challenge Helpers
// ============================================================

/**
 * Check if employee has submitted (or is pending) for a challenge.
 * Prevents double-submission regardless of status.
 */
function hasSubmittedChallenge(int $employeeId, int $challengeId): bool
{
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt
        FROM challenge_submissions
        WHERE employee_id  = ?
          AND challenge_id = ?
          AND status NOT IN ('rejected')
    ");
    $stmt->execute([$employeeId, $challengeId]);
    $row = $stmt->fetch();
    return (int)$row['cnt'] > 0;
}

/**
 * Get all active challenges for today (within date range).
 */
function getActiveChallenges(): array
{
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT challenge_id, title, description, type, instructions,
               token_reward, start_date, end_date, strava_condition
        FROM   challenges
        WHERE  is_active  = 1
          AND  start_date <= CAST(GETDATE() AS DATE)
          AND  end_date   >= CAST(GETDATE() AS DATE)
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get a single challenge by ID.
 */
function getChallenge(int $challengeId): ?array
{
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM challenges WHERE challenge_id = ?");
    $stmt->execute([$challengeId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Get quiz questions for a challenge (ordered by display_order).
 */
function getQuizQuestions(int $challengeId): array
{
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT question_id, question_text, option_a, option_b, option_c, option_d,
               correct_option, explanation, display_order
        FROM   quiz_questions
        WHERE  challenge_id = ?
        ORDER BY display_order ASC
    ");
    $stmt->execute([$challengeId]);
    return $stmt->fetchAll();
}

/**
 * Get the correct option for a quiz question (admin/verification use only).
 */
function getCorrectOption(int $questionId): string
{
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT correct_option, explanation FROM quiz_questions WHERE question_id = ?");
    $stmt->execute([$questionId]);
    $row = $stmt->fetch();
    return $row ? $row['correct_option'] : '';
}

// ============================================================
// Streak & Stats
// ============================================================

/**
 * Calculate consecutive days the employee has had at least one approved submission.
 * Counts backward from today.
 */
function getActivityStreak(int $employeeId): int
{
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT DISTINCT CAST(submitted_at AS DATE) AS sub_date
        FROM   challenge_submissions
        WHERE  employee_id = ?
          AND  status IN ('auto_approved', 'approved')
        ORDER BY sub_date DESC
    ");
    $stmt->execute([$employeeId]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($dates)) {
        return 0;
    }

    $streak = 0;
    $today  = new DateTime();
    $today->setTime(0, 0, 0);

    foreach ($dates as $dateStr) {
        $date = new DateTime($dateStr);
        $diff = (int)$today->diff($date)->days;
        if ($diff === $streak) {
            $streak++;
        } else {
            break;
        }
    }

    return $streak;
}

/**
 * Get employee's recent token transactions (for history page).
 */
function getRecentTransactions(int $employeeId, int $limit = 20): array
{
    $pdo  = getDB();
    $limit = max(1, $limit);
    $stmt = $pdo->prepare("
        SELECT TOP ({$limit})
               tx_id, amount, tx_type, note, created_at
        FROM   token_transactions
        WHERE  employee_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$employeeId]);
    return $stmt->fetchAll();
}

/**
 * Get employee's recent submissions (for history page).
 */
function getRecentSubmissions(int $employeeId, int $limit = 20): array
{
    $pdo  = getDB();
    $limit = max(1, $limit);
    $stmt = $pdo->prepare("
        SELECT TOP ({$limit})
               cs.submission_id, cs.submission_type, cs.status,
               cs.token_awarded, cs.submitted_at, cs.review_note,
               c.title AS challenge_title, c.token_reward
        FROM   challenge_submissions cs
        JOIN   challenges c ON c.challenge_id = cs.challenge_id
        WHERE  cs.employee_id = ?
        ORDER BY cs.submitted_at DESC
    ");
    $stmt->execute([$employeeId]);
    return $stmt->fetchAll();
}

/**
 * Get leaderboard (top earners by total_earned).
 */
function getLeaderboard(int $limit = 10): array
{
    $pdo  = getDB();
    $limit = max(1, $limit);
    $stmt = $pdo->prepare("
        SELECT TOP ({$limit})
               e.employee_id, e.full_name, e.department,
               w.total_earned, w.balance,
               ROW_NUMBER() OVER (ORDER BY w.total_earned DESC) AS rank
        FROM   token_wallets w
        JOIN   employees e ON e.employee_id = w.employee_id
        WHERE  e.role = 'employee' AND e.is_active = 1
        ORDER BY w.total_earned DESC
    ");
    $stmt->execute([]);
    return $stmt->fetchAll();
}

/**
 * Get homepage summary stats for the team overview.
 */
function getHomeOverviewStats(): array
{
    $pdo  = getDB();
    $stmt = $pdo->query("
        SELECT
            (SELECT COUNT(*)
             FROM employees
             WHERE role = 'employee' AND is_active = 1) AS active_employees,
            (SELECT ISNULL(SUM(w.balance), 0)
             FROM token_wallets w
             JOIN employees e ON e.employee_id = w.employee_id
             WHERE e.role = 'employee' AND e.is_active = 1) AS team_balance,
            (SELECT ISNULL(SUM(w.total_earned), 0)
             FROM token_wallets w
             JOIN employees e ON e.employee_id = w.employee_id
             WHERE e.role = 'employee' AND e.is_active = 1) AS team_earned,
            (SELECT COUNT(*)
             FROM challenges
             WHERE is_active = 1
               AND start_date <= CAST(GETDATE() AS DATE)
               AND end_date >= CAST(GETDATE() AS DATE)) AS active_challenges,
            (SELECT COUNT(*)
             FROM challenge_submissions
             WHERE status = 'pending') AS pending_reviews,
            (SELECT COUNT(*)
             FROM challenge_submissions
             WHERE CAST(submitted_at AS DATE) = CAST(GETDATE() AS DATE)) AS submissions_today
    ");
    $row = $stmt->fetch();

    return $row ?: [
        'active_employees' => 0,
        'team_balance' => 0,
        'team_earned' => 0,
        'active_challenges' => 0,
        'pending_reviews' => 0,
        'submissions_today' => 0,
    ];
}

/**
 * Get recent team activity feed for the homepage.
 */
function getRecentTeamActivity(int $limit = 6): array
{
    $pdo  = getDB();
    $limit = max(1, $limit);
    $stmt = $pdo->prepare("
        SELECT TOP ({$limit})
               cs.submission_id,
               cs.submission_type,
               cs.status,
               cs.token_awarded,
               cs.submitted_at,
               e.full_name,
               e.department,
               c.title AS challenge_title
        FROM   challenge_submissions cs
        JOIN   employees e ON e.employee_id = cs.employee_id
        JOIN   challenges c ON c.challenge_id = cs.challenge_id
        ORDER BY cs.submitted_at DESC
    ");
    $stmt->execute([]);
    return $stmt->fetchAll();
}

/**
 * Get token activity trend for the last 7 days.
 */
function getWeeklyTokenTrend(): array
{
    $pdo  = getDB();
    $stmt = $pdo->query("
        WITH last_7_days AS (
            SELECT CAST(DATEADD(DAY, 0, CAST(GETDATE() AS DATE)) AS DATE) AS trend_date UNION ALL
            SELECT CAST(DATEADD(DAY, -1, CAST(GETDATE() AS DATE)) AS DATE) UNION ALL
            SELECT CAST(DATEADD(DAY, -2, CAST(GETDATE() AS DATE)) AS DATE) UNION ALL
            SELECT CAST(DATEADD(DAY, -3, CAST(GETDATE() AS DATE)) AS DATE) UNION ALL
            SELECT CAST(DATEADD(DAY, -4, CAST(GETDATE() AS DATE)) AS DATE) UNION ALL
            SELECT CAST(DATEADD(DAY, -5, CAST(GETDATE() AS DATE)) AS DATE) UNION ALL
            SELECT CAST(DATEADD(DAY, -6, CAST(GETDATE() AS DATE)) AS DATE)
        ),
        tx_daily AS (
            SELECT CAST(created_at AS DATE) AS trend_date,
                   SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) AS earned_tokens
            FROM token_transactions
            GROUP BY CAST(created_at AS DATE)
        ),
        submission_daily AS (
            SELECT CAST(submitted_at AS DATE) AS trend_date,
                   COUNT(*) AS submissions_count
            FROM challenge_submissions
            GROUP BY CAST(submitted_at AS DATE)
        )
        SELECT
            d.trend_date,
            ISNULL(tx.earned_tokens, 0) AS earned_tokens,
            ISNULL(sd.submissions_count, 0) AS submissions_count
        FROM last_7_days d
        LEFT JOIN tx_daily tx
               ON tx.trend_date = d.trend_date
        LEFT JOIN submission_daily sd
               ON sd.trend_date = d.trend_date
        ORDER BY d.trend_date ASC
    ");
    return $stmt->fetchAll();
}

/**
 * Map challenge type to Thai label.
 */
function challengeTypeLabel(string $type): string
{
    return match($type) {
        'quiz' => 'Quiz',
        'photo' => 'Photo',
        default => ucfirst($type),
    };
}

// ============================================================
// Admin Helpers
// ============================================================

/**
 * Get count of pending photo submissions (for admin badge).
 */
function getPendingCount(): int
{
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM challenge_submissions WHERE status = 'pending'");
    $stmt->execute();
    $row = $stmt->fetch();
    return (int)$row['cnt'];
}

/**
 * Map tx_type to human-readable Thai label.
 */
function txTypeLabel(string $txType): string
{
    return match($txType) {
        'quiz_reward'  => 'ทำ Quiz สำเร็จ',
        'photo_reward' => 'ส่งรูปภาพ (อนุมัติแล้ว)',
        'admin_adjust' => 'ปรับโดย Admin',
        'bonus'         => 'โบนัส',
        'redemption'    => 'แลกรางวัล',
        'strava_reward' => 'Strava สำเร็จ',
        default         => $txType,
    };
}

/**
 * Map submission status to Thai label + CSS color class.
 */
function statusBadge(string $status): array
{
    return match($status) {
        'auto_approved' => ['label' => 'ผ่านอัตโนมัติ', 'color' => '#518e5c',  'bg' => '#e8f4ec'],
        'approved'      => ['label' => 'อนุมัติแล้ว',   'color' => '#518e5c',  'bg' => '#e8f4ec'],
        'pending'       => ['label' => 'รอตรวจสอบ',     'color' => '#dab937',  'bg' => '#fdf8e1'],
        'rejected'      => ['label' => 'ไม่ผ่าน',        'color' => '#d2592a',  'bg' => '#fdf0ea'],
        default         => ['label' => $status,          'color' => '#6b6e77',  'bg' => '#f5f5f4'],
    };
}

// ============================================================
// Employee Profile & Tenure
// ============================================================

/**
 * คำนวณอายุงานจาก start_date string (YYYY-MM-DD)
 * คืน array: [ years, months, days, text (Thai), total_days ]
 * ถ้า start_date เป็น null หรือวันที่ไม่ถูกต้อง คืน null
 */
function getWorkTenure(?string $startDate): ?array
{
    if (!$startDate || str_starts_with($startDate, '1900')) return null;

    try {
        $start = new DateTime($startDate);
    } catch (Exception $e) {
        return null;
    }

    $now = new DateTime('today');
    if ($start > $now) return null;

    $diff   = $start->diff($now);
    $years  = (int)$diff->y;
    $months = (int)$diff->m;
    $days   = (int)$diff->d;

    $parts = [];
    if ($years  > 0) $parts[] = $years  . ' ปี';
    if ($months > 0) $parts[] = $months . ' เดือน';
    if ($days   > 0) $parts[] = $days   . ' วัน';
    $text = $parts ? implode(' ', $parts) : 'น้อยกว่า 1 วัน';

    return [
        'years'      => $years,
        'months'     => $months,
        'days'       => $days,
        'text'       => $text,
        'total_days' => (int)$diff->days,
        'start_date' => $startDate,
    ];
}

/**
 * ดึงข้อมูลอายุงานของพนักงานจาก DB
 */
function getEmployeeTenure(int $employeeId): ?array
{
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT start_date FROM dbo.employees WHERE employee_id = ?");
    $stmt->execute([$employeeId]);
    $row  = $stmt->fetch();
    return $row ? getWorkTenure($row['start_date']) : null;
}

/**
 * ดึงข้อมูลโปรไฟล์เต็มของพนักงาน (รวม start_date, email)
 */
function getEmployeeProfile(int $employeeId): ?array
{
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT employee_id, employee_code, full_name, department, position,
               email, role, avatar_url, is_active, start_date, created_at
        FROM   dbo.employees
        WHERE  employee_id = ?
    ");
    $stmt->execute([$employeeId]);
    $row = $stmt->fetch();
    return $row ?: null;
}
