<?php
/**
 * admin/challenges/index.php
 * Admin — list all challenges, toggle active, delete
 */

require_once __DIR__ . '/../../includes/admin_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$adminId = (int)$_SESSION['employee_id'];

// ── POST actions ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = (string)($_POST['action'] ?? '');
    $cid    = (int)($_POST['challenge_id'] ?? 0);

    if ($cid > 0) {
        $pdo = getDB();

        if ($action === 'toggle_active') {
            $pdo->prepare("UPDATE challenges SET is_active = 1 - is_active WHERE challenge_id = ?")
                ->execute([$cid]);
            setFlash('success', 'เปลี่ยนสถานะภารกิจแล้ว');

        } elseif ($action === 'delete') {
            // Only allow delete if no submissions exist
            $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM challenge_submissions WHERE challenge_id = ?");
            $stmt->execute([$cid]);
            $cnt = (int)$stmt->fetch()['cnt'];
            if ($cnt > 0) {
                setFlash('error', 'ไม่สามารถลบได้ เพราะมีงานที่ส่งแล้ว (' . $cnt . ' รายการ) — ปิดการใช้งานแทน');
            } else {
                $pdo->prepare("DELETE FROM quiz_questions WHERE challenge_id = ?")->execute([$cid]);
                $pdo->prepare("DELETE FROM challenges WHERE challenge_id = ?")->execute([$cid]);
                setFlash('success', 'ลบภารกิจแล้ว');
            }
        }
    }

    redirect(BASE_URL . '/admin/challenges/index.php');
}

// ── GET: load all challenges ─────────────────────────────────
$pdo = getDB();
$stmt = $pdo->query("
    SELECT c.challenge_id, c.title, c.type, c.token_reward,
           c.start_date, c.end_date, c.is_active, c.created_at,
           (SELECT COUNT(*) FROM challenge_submissions cs WHERE cs.challenge_id = c.challenge_id) AS submission_count,
           (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.challenge_id = c.challenge_id) AS question_count
    FROM challenges c
    ORDER BY c.created_at DESC
");
$challenges = $stmt->fetchAll();

$flash = getFlash();

$pageTitle  = 'จัดการภารกิจ';
$activePage = 'admin_challenges';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <?php if ($flash): ?>
    <div class="mb-6 rounded-xl px-5 py-4 text-sm font-medium
        <?= $flash['type'] === 'success' ? 'border border-green-200 bg-green-50 text-green-800' : 'border border-red-200 bg-red-50 text-red-800' ?>">
        <?= e($flash['message']) ?>
    </div>
    <?php endif; ?>

    <div class="mb-6 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-semibold text-j-dark">จัดการภารกิจ</h1>
            <p class="mt-1 text-sm text-j-slate">สร้าง แก้ไข และจัดการ challenge ทั้งหมด</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/challenges/edit.php" class="btn-gold">
            + สร้างภารกิจใหม่
        </a>
    </div>

    <?php if ($challenges): ?>
    <div class="journal-card overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr style="background:#f5f3ea; border-bottom:1px solid #cecdcd;">
                    <th class="px-5 py-3 text-left text-xs font-semibold text-j-slate uppercase tracking-wider">ชื่อภารกิจ</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-j-slate uppercase tracking-wider hidden sm:table-cell">ประเภท</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-j-slate uppercase tracking-wider hidden md:table-cell">Token</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-j-slate uppercase tracking-wider hidden lg:table-cell">ช่วงเวลา</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-j-slate uppercase tracking-wider">ส่งงาน</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-j-slate uppercase tracking-wider">สถานะ</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-j-slate uppercase tracking-wider">จัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($challenges as $i => $ch): ?>
            <?php
                $isActive = (bool)$ch['is_active'];
                $sd = $ch['start_date'] instanceof DateTimeInterface ? $ch['start_date']->format('d/m/Y') : date('d/m/Y', strtotime((string)$ch['start_date']));
                $ed = $ch['end_date']   instanceof DateTimeInterface ? $ch['end_date']->format('d/m/Y')   : date('d/m/Y', strtotime((string)$ch['end_date']));
            ?>
            <tr class="<?= $i % 2 === 0 ? 'bg-white' : '' ?> border-b border-[#eeebe1] last:border-0">
                <td class="px-5 py-3.5">
                    <p class="font-medium text-j-dark"><?= e($ch['title']) ?></p>
                    <?php if ($ch['type'] === 'quiz'): ?>
                    <p class="text-xs text-j-slate mt-0.5"><?= (int)$ch['question_count'] ?> คำถาม</p>
                    <?php endif; ?>
                </td>
                <td class="px-5 py-3.5 hidden sm:table-cell">
                    <span class="badge text-xs" style="background:#091113; color:#dab937;">
                        <?= $ch['type'] === 'quiz' ? '📝 Quiz' : '📷 Photo' ?>
                    </span>
                </td>
                <td class="px-5 py-3.5 text-center font-semibold text-j-gold hidden md:table-cell">
                    +<?= formatTokens((int)$ch['token_reward']) ?>
                </td>
                <td class="px-5 py-3.5 text-center text-xs text-j-slate hidden lg:table-cell">
                    <?= $sd ?><br><?= $ed ?>
                </td>
                <td class="px-5 py-3.5 text-center text-j-slate">
                    <?= (int)$ch['submission_count'] ?>
                </td>
                <td class="px-5 py-3.5 text-center">
                    <form method="POST" class="inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="challenge_id" value="<?= (int)$ch['challenge_id'] ?>">
                        <button type="submit"
                                class="badge text-xs font-medium cursor-pointer border-0"
                                style="background:<?= $isActive ? '#dcfce7' : '#f3f4f6' ?>; color:<?= $isActive ? '#166534' : '#6b7280' ?>;">
                            <?= $isActive ? 'เปิดอยู่' : 'ปิดแล้ว' ?>
                        </button>
                    </form>
                </td>
                <td class="px-5 py-3.5">
                    <div class="flex items-center justify-end gap-2">
                        <a href="<?= BASE_URL ?>/admin/challenges/edit.php?id=<?= (int)$ch['challenge_id'] ?>"
                           class="btn-outline px-3 py-1.5 text-xs">แก้ไข</a>
                        <?php if ((int)$ch['submission_count'] === 0): ?>
                        <form method="POST" class="inline"
                              onsubmit="return confirm('ยืนยันลบภารกิจ "<?= e($ch['title']) ?>"?\nการกระทำนี้ไม่สามารถย้อนกลับได้')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="challenge_id" value="<?= (int)$ch['challenge_id'] ?>">
                            <button type="submit"
                                    title="ลบภารกิจ"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-red-500 hover:bg-red-50 hover:text-red-700 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                        <?php else: ?>
                        <span title="ลบไม่ได้ เพราะมีงานส่งแล้ว (<?= (int)$ch['submission_count'] ?> รายการ)"
                              class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-300 cursor-not-allowed">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php else: ?>
    <div class="rounded-2xl border border-dashed border-j-silver bg-white px-5 py-16 text-center text-sm text-j-slate">
        ยังไม่มีภารกิจ — <a href="<?= BASE_URL ?>/admin/challenges/edit.php" class="text-j-gold hover:underline">สร้างภารกิจแรก</a>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
