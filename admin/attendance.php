<?php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/layout.php';
require_once __DIR__ . '/../include/db_compat.php';
requireLogin();

$db  = db();
$msg = '';
$err = '';

// ── Open a new session via GET shortcut (from dashboard) ──────────────────
if (isset($_GET['open_tt']) && isset($_GET['date'])) {
    $ttId = (int)$_GET['open_tt'];
    $date = $_GET['date'];
    try {
        insert_ignore($db, 'att_sessions', ['timetable_id','session_date','opened_by'], [$ttId, $date, $_SESSION['rep_id']]);
    } catch (PDOException $e) {}
    $sid = $db->prepare('SELECT id FROM att_sessions WHERE timetable_id=? AND session_date=?');
    $sid->execute([$ttId, $date]);
    $r = $sid->fetch();
    if ($r) {
        header('Location: ' . SITE_URL . '/admin/attendance.php?session_id=' . $r['id'] . '&date=' . $date);
        exit;
    }
}

// ── POST actions ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'open_session') {
        $ttId = (int)($_POST['timetable_id'] ?? 0);
        $date = $_POST['session_date'] ?? date('Y-m-d');
        try {
            insert_ignore($db, 'att_sessions', ['timetable_id','session_date','opened_by'], [$ttId, $date, $_SESSION['rep_id']]);
            $msg = 'Session opened.';
        } catch (PDOException $e) { $err = 'Session already exists for this slot and date.'; }

    } elseif ($action === 'mark_manual') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $studentId = (int)($_POST['student_id'] ?? 0);
        try {
            insert_ignore($db, 'attendance', ['session_id','student_id','method'], [$sessionId, $studentId, 'manual']);
        } catch (PDOException $e) {}
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;

    } elseif ($action === 'unmark') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $studentId = (int)($_POST['student_id'] ?? 0);
        $db->prepare('DELETE FROM attendance WHERE session_id=? AND student_id=?')
           ->execute([$sessionId, $studentId]);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;

    } elseif ($action === 'mark_all') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $students  = $db->query('SELECT id FROM students WHERE is_active=TRUE')->fetchAll();
        foreach ($students as $s) {
            insert_ignore($db, 'attendance', ['session_id', 'student_id', 'method'], [$sessionId, $s['id'], 'manual']);
        }
        $msg = 'All students marked present.';

    } elseif ($action === 'clear_all') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $db->prepare('DELETE FROM attendance WHERE session_id=?')->execute([$sessionId]);
        $msg = 'Attendance cleared.';

    } elseif ($action === 'close_session') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $notes     = trim($_POST['notes'] ?? '');
        $db->prepare('UPDATE att_sessions SET closed_at=NOW(), notes=? WHERE id=?')
           ->execute([$notes, $sessionId]);
        $msg = 'Session closed.';

    } elseif ($action === 'reopen_session') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $db->prepare('UPDATE att_sessions SET closed_at=NULL WHERE id=?')->execute([$sessionId]);
        $msg = 'Session re-opened.';

    } elseif ($action === 'delete_session') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $db->prepare('DELETE FROM att_sessions WHERE id=?')->execute([$sessionId]);
        header('Location: ' . SITE_URL . '/admin/attendance.php');
        exit;
    }
}

// ── Selected date & session ───────────────────────────────────────────────
$selDate      = $_GET['date'] ?? date('Y-m-d');
$selSessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$selDow       = (int)date('w', strtotime($selDate)); // 0=Sun

// Calendar month
$calYear  = isset($_GET['cy']) ? (int)$_GET['cy'] : (int)date('Y');
$calMonth = isset($_GET['cm']) ? (int)$_GET['cm'] : (int)date('n');
if ($calMonth < 1)  { $calMonth = 12; $calYear--; }
if ($calMonth > 12) { $calMonth = 1;  $calYear++; }
$firstDay    = (int)date('w', mktime(0,0,0,$calMonth,1,$calYear));
$daysInMonth = (int)date('t', mktime(0,0,0,$calMonth,1,$calYear));

// Days that have timetable slots — for dot indicators
$dotDays = $db->query('SELECT DISTINCT day_of_week FROM timetable t JOIN courses c ON t.course_id=c.id WHERE c.is_active=TRUE')->fetchAll(PDO::FETCH_COLUMN);

// Sessions that exist in this month (for calendar highlight)
$monthSessions = $db->prepare('
    SELECT s.session_date, COUNT(*) AS cnt
    FROM att_sessions s
    WHERE EXTRACT(YEAR FROM s.session_date)=? AND EXTRACT(MONTH FROM s.session_date)=?
    GROUP BY s.session_date
');
$monthSessions->execute([$calYear, $calMonth]);
$sessionDates = array_column($monthSessions->fetchAll(), 'cnt', 'session_date');

// Courses for selected date (timetable slots)
$coursesForDate = $db->prepare('
    SELECT t.*, c.code, c.title, c.instructor, c.units,
           s.id AS session_id, s.closed_at, s.notes,
           (SELECT COUNT(*) FROM attendance a WHERE a.session_id=s.id) AS present_count
    FROM timetable t
    JOIN courses c ON t.course_id=c.id
    LEFT JOIN att_sessions s ON s.timetable_id=t.id AND s.session_date=?
    WHERE t.day_of_week=? AND c.is_active=TRUE
    ORDER BY t.start_time
');
$coursesForDate->execute([$selDate, $selDow]);
$dateClasses = $coursesForDate->fetchAll();

// Active session details
$activeSession = null;
$sessionStudents = [];
$presentSet = [];

if ($selSessionId) {
    $ss = $db->prepare('
        SELECT s.*, t.start_time, t.end_time, t.venue, t.day_of_week,
               c.code, c.title, c.instructor
        FROM att_sessions s
        JOIN timetable t ON s.timetable_id=t.id
        JOIN courses c ON t.course_id=c.id
        WHERE s.id=?
    ');
    $ss->execute([$selSessionId]);
    $activeSession = $ss->fetch();

    if ($activeSession) {
        $selDate = $activeSession['session_date'];

        // All active students + their presence + fingerprint status
        $stmtStu = $db->prepare('
            SELECT s.*,
                   a.id AS att_id, a.verified_at, a.method,
                   (SELECT COUNT(*) FROM webauthn_credentials w WHERE w.student_id=s.id) AS has_fp
            FROM students s
            LEFT JOIN attendance a ON a.student_id=s.id AND a.session_id=?
            WHERE s.is_active=TRUE
            ORDER BY s.full_name
        ');
        $stmtStu->execute([$selSessionId]);
        $sessionStudents = $stmtStu->fetchAll();
        $presentSet = array_map(fn($r)=>$r['id'],
                        array_filter($sessionStudents, fn($r)=>!empty($r['att_id'])));
    }
}

$totalStudents = $db->query('SELECT COUNT(*) FROM students WHERE is_active=TRUE')->fetchColumn();

$monthNames = ['','January','February','March','April','May','June',
               'July','August','September','October','November','December'];
$dayNames   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

$colors = ['#5c4ef7','#22c48c','#3b8df9','#f5a524','#e84646','#a855f7','#e05c7a','#f97316','#14b8a6','#ec4899'];
function stuColor2(int $id, array $cols): string { return $cols[$id % count($cols)]; }

layout_head('Attendance', 'attendance');
layout_topbar('Attendance');

if ($msg): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check"></i><?= htmlspecialchars($msg) ?></div><?php endif;
if ($err): ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($err) ?></div><?php endif;
?>

<div style="display:grid;grid-template-columns:300px 1fr;gap:22px;align-items:start">

<!-- ══ LEFT: Calendar + course list ══════════════════════════════════════ -->
<div style="display:flex;flex-direction:column;gap:16px">

  <!-- Calendar -->
  <div class="card">
    <div class="card-header" style="padding:14px 16px">
      <button class="btn btn-ghost btn-sm"
        onclick="location.href='?cy=<?= $calMonth===1?$calYear-1:$calYear ?>&cm=<?= $calMonth===1?12:$calMonth-1 ?>&date=<?= $selDate ?>'">
        <i class="fa-solid fa-chevron-left"></i>
      </button>
      <span style="font-weight:800;font-size:14px"><?= $monthNames[$calMonth] ?> <?= $calYear ?></span>
      <button class="btn btn-ghost btn-sm"
        onclick="location.href='?cy=<?= $calMonth===12?$calYear+1:$calYear ?>&cm=<?= $calMonth===12?1:$calMonth+1 ?>&date=<?= $selDate ?>'">
        <i class="fa-solid fa-chevron-right"></i>
      </button>
    </div>
    <div style="padding:8px 12px 14px">
      <!-- Day labels -->
      <div style="display:grid;grid-template-columns:repeat(7,1fr);margin-bottom:4px">
        <?php foreach ($dayNames as $dn): ?>
          <div style="text-align:center;font-size:10px;font-weight:700;color:var(--muted);padding:3px 0;text-transform:uppercase"><?= $dn ?></div>
        <?php endforeach; ?>
      </div>
      <!-- Date cells -->
      <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px">
        <?php
        for ($i = 0; $i < $firstDay; $i++) echo '<div></div>';
        for ($d = 1; $d <= $daysInMonth; $d++):
            $dStr  = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $d);
            $dow   = (int)date('w', strtotime($dStr));
            $hasTT = in_array($dow, $dotDays);
            $hasSess = isset($sessionDates[$dStr]);
            $isToday = $dStr === date('Y-m-d');
            $isSel   = $dStr === $selDate;
            $isWknd  = $dow === 0 || $dow === 6;

            $bg     = $isSel  ? 'var(--brand)' : 'transparent';
            $color  = $isSel  ? '#fff' : ($isWknd ? 'var(--muted)' : 'var(--text)');
            $border = $isToday && !$isSel ? '1.5px solid var(--brand)' : '1.5px solid transparent';
            $fw     = $isToday ? '800' : '500';
        ?>
          <a href="?cy=<?= $calYear ?>&cm=<?= $calMonth ?>&date=<?= $dStr ?>"
             style="display:flex;flex-direction:column;align-items:center;justify-content:center;
                    aspect-ratio:1;border-radius:8px;background:<?= $bg ?>;color:<?= $color ?>;
                    font-size:13px;font-weight:<?= $fw ?>;border:<?= $border ?>;
                    text-decoration:none;position:relative;transition:background .12s">
            <?= $d ?>
            <?php if ($hasTT && !$isSel): ?>
              <span style="position:absolute;bottom:2px;width:4px;height:4px;border-radius:50%;
                           background:<?= $hasSess ? 'var(--green)' : 'var(--brand)' ?>"></span>
            <?php elseif ($hasTT && $isSel): ?>
              <span style="position:absolute;bottom:2px;width:4px;height:4px;border-radius:50%;background:rgba(255,255,255,.7)"></span>
            <?php endif; ?>
          </a>
        <?php endfor; ?>
      </div>
      <div style="display:flex;gap:14px;margin-top:12px;padding-top:10px;border-top:1px solid var(--border)">
        <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--muted)">
          <span style="width:7px;height:7px;border-radius:50%;background:var(--brand);display:inline-block"></span> Has class
        </div>
        <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--muted)">
          <span style="width:7px;height:7px;border-radius:50%;background:var(--green);display:inline-block"></span> Session recorded
        </div>
      </div>
    </div>
  </div>

  <!-- Courses for selected date -->
  <div class="card">
    <div class="card-header" style="padding:13px 16px">
      <span style="font-weight:700;font-size:13px">
        <i class="fa-regular fa-calendar c-brand"></i>
        <?= date('D, M j', strtotime($selDate)) ?>
      </span>
    </div>
    <?php if (empty($dateClasses)): ?>
      <div style="padding:24px;text-align:center;color:var(--muted);font-size:13px">
        <i class="fa-solid fa-mug-hot" style="font-size:28px;opacity:.3;display:block;margin-bottom:8px"></i>
        No classes this day
      </div>
    <?php else: ?>
      <div style="padding:6px 0">
        <?php foreach ($dateClasses as $cls):
          $hasSession = !empty($cls['session_id']);
          $isActive   = $selSessionId === (int)($cls['session_id'] ?? 0);
          $pct = $totalStudents > 0 && $hasSession ? round($cls['present_count']/$totalStudents*100) : 0;
          $ccolor = $colors[hexdec(substr(hash('sha256', $cls['code']), 0, 8)) % count($colors)];
        ?>
        <div style="padding:10px 14px;border-bottom:1px solid #f0f2f8;
                    background:<?= $isActive ? 'var(--brand-light)' : 'transparent' ?>">
          <div style="display:flex;align-items:center;gap:9px">
            <div style="width:3px;height:38px;border-radius:3px;background:<?= $ccolor ?>;flex-shrink:0"></div>
            <div style="flex:1;min-width:0">
              <div style="font-size:12px;font-weight:700;color:<?= $isActive?'var(--brand)':'var(--text)' ?>">
                <?= htmlspecialchars($cls['code']) ?></div>
              <div style="font-size:11px;color:var(--muted)">
                <?= date('g:i', strtotime($cls['start_time'])) ?>–<?= date('g:i A', strtotime($cls['end_time'])) ?>
                <?= $cls['venue'] ? ' · ' . htmlspecialchars($cls['venue']) : '' ?>
              </div>
            </div>
            <?php if ($hasSession): ?>
              <a href="?date=<?= $selDate ?>&session_id=<?= $cls['session_id'] ?>&cy=<?= $calYear ?>&cm=<?= $calMonth ?>"
                 class="btn btn-ghost btn-sm" style="<?= $isActive?'border-color:var(--brand);color:var(--brand)':'' ?>">
                <?= $cls['present_count'] ?>/<?= $totalStudents ?>
              </a>
            <?php else: ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="open_session">
                <input type="hidden" name="timetable_id" value="<?= $cls['id'] ?>">
                <input type="hidden" name="session_date" value="<?= $selDate ?>">
                <button class="btn btn-primary btn-sm"><i class="fa-solid fa-play"></i> Open</button>
              </form>
            <?php endif; ?>
          </div>
          <?php if ($hasSession && $totalStudents > 0): ?>
            <div style="margin:6px 12px 0;display:flex;align-items:center;gap:6px">
              <div class="progress-bar-wrap" style="height:4px">
                <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $pct>=70?'var(--green)':($pct>=50?'var(--amber)':'var(--red)') ?>"></div>
              </div>
              <span style="font-size:10px;font-weight:700;color:var(--muted)"><?= $pct ?>%</span>
            </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ══ RIGHT: Session / student roster ════════════════════════════════════ -->
<div>
<?php if (!$activeSession): ?>
  <div class="card" style="min-height:300px">
    <div style="padding:60px 24px;text-align:center;color:var(--muted)">
      <i class="fa-solid fa-hand-pointer" style="font-size:48px;opacity:.25;display:block;margin-bottom:14px"></i>
      <div style="font-size:15px;font-weight:600;margin-bottom:6px">Select a class to begin</div>
      <div style="font-size:13px">Click a date on the calendar, then open or select a session</div>
    </div>
  </div>

<?php else:
  $isClosed = !empty($activeSession['closed_at']);
  $presentCount = count($presentSet);
  $absentCount  = $totalStudents - $presentCount;
  $attRate = $totalStudents > 0 ? round($presentCount/$totalStudents*100) : 0;
  $rateColor = $attRate >= 70 ? 'var(--green)' : ($attRate >= 50 ? 'var(--amber)' : 'var(--red)');
?>
  <!-- Session header -->
  <div class="card mb-4">
    <div style="padding:16px 20px;display:flex;align-items:center;gap:16px">
      <div style="flex:1">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
          <span style="font-size:17px;font-weight:800"><?= htmlspecialchars($activeSession['code']) ?> — <?= htmlspecialchars($activeSession['title']) ?></span>
          <?php if ($isClosed): ?>
            <span class="badge badge-gray"><i class="fa-solid fa-lock"></i> Closed</span>
          <?php else: ?>
            <span class="badge badge-green"><i class="fa-solid fa-circle" style="font-size:7px"></i> Open</span>
          <?php endif; ?>
        </div>
        <div style="font-size:12px;color:var(--muted)">
          <?= date('l, F j Y', strtotime($activeSession['session_date'])) ?>
          · <?= date('g:i A', strtotime($activeSession['start_time'])) ?>–<?= date('g:i A', strtotime($activeSession['end_time'])) ?>
          · <?= htmlspecialchars($activeSession['venue'] ?? 'TBD') ?>
          · <?= htmlspecialchars($activeSession['instructor']) ?>
        </div>
      </div>
      <!-- Circular rate -->
      <div style="text-align:center;flex-shrink:0">
        <svg width="64" height="64" viewBox="0 0 64 64">
          <circle cx="32" cy="32" r="26" fill="none" stroke="#e4e8f0" stroke-width="6"/>
          <circle cx="32" cy="32" r="26" fill="none" stroke="<?= $rateColor ?>" stroke-width="6"
            stroke-dasharray="<?= round($attRate * 163.36 / 100) ?> 163.36"
            stroke-linecap="round" transform="rotate(-90 32 32)"/>
          <text x="32" y="37" text-anchor="middle" font-size="13" font-weight="800" fill="<?= $rateColor ?>"
            font-family="Plus Jakarta Sans,sans-serif"><?= $attRate ?>%</text>
        </svg>
      </div>
    </div>

    <!-- Stats row -->
    <div style="display:flex;border-top:1px solid var(--border)">
      <?php foreach ([
        ['Present', $presentCount, 'var(--green)'],
        ['Absent',  $absentCount,  'var(--red)'],
        ['Total',   $totalStudents,'var(--brand)'],
      ] as [$lbl, $val, $col]): ?>
      <div style="flex:1;padding:12px;text-align:center;border-right:1px solid var(--border)">
        <div style="font-size:22px;font-weight:800;color:<?= $col ?>"><?= $val ?></div>
        <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px"><?= $lbl ?></div>
      </div>
      <?php endforeach; ?>
      <div style="flex:1;padding:12px;text-align:center">
        <div style="font-size:22px;font-weight:800;color:var(--muted)"><?= date('g:i A') ?></div>
        <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">Now</div>
      </div>
    </div>

    <!-- Action bar -->
    <?php if (!$isClosed): ?>
    <div style="padding:12px 16px;background:#fafbff;border-top:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap">
      <form method="POST" style="display:inline">
        <input type="hidden" name="action" value="mark_all">
        <input type="hidden" name="session_id" value="<?= $selSessionId ?>">
        <button class="btn btn-ghost btn-sm"><i class="fa-solid fa-check-double"></i> Mark All Present</button>
      </form>
      <form method="POST" style="display:inline">
        <input type="hidden" name="action" value="clear_all">
        <input type="hidden" name="session_id" value="<?= $selSessionId ?>">
        <button class="btn btn-ghost btn-sm" onclick="return confirm('Clear all attendance for this session?')">
          <i class="fa-solid fa-rotate-left"></i> Clear All</button>
      </form>
      <button class="btn btn-ghost btn-sm" onclick="openModal('close-modal')">
        <i class="fa-solid fa-lock"></i> Close Session</button>
      <form method="POST" style="margin-left:auto;display:inline">
        <input type="hidden" name="action" value="delete_session">
        <input type="hidden" name="session_id" value="<?= $selSessionId ?>">
        <button class="btn btn-danger btn-sm" onclick="return confirm('Delete this entire session and all its records?')">
          <i class="fa-solid fa-trash"></i> Delete</button>
      </form>
    </div>
    <?php else: ?>
    <div style="padding:10px 16px;background:#fafbff;border-top:1px solid var(--border);display:flex;gap:8px">
      <form method="POST" style="display:inline">
        <input type="hidden" name="action" value="reopen_session">
        <input type="hidden" name="session_id" value="<?= $selSessionId ?>">
        <button class="btn btn-ghost btn-sm"><i class="fa-solid fa-lock-open"></i> Re-open Session</button>
      </form>
      <a href="<?= SITE_URL ?>/admin/reports.php?session_id=<?= $selSessionId ?>" class="btn btn-ghost btn-sm">
        <i class="fa-solid fa-chart-bar"></i> View Report</a>
    </div>
    <?php endif; ?>
  </div>

  <!-- Student roster -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fa-solid fa-users c-brand"></i> Student Roster</span>
      <div style="display:flex;gap:8px;align-items:center">
        <input id="search-stu" placeholder="Filter students…" style="width:180px;padding:6px 10px;font-size:12px;height:34px"
               oninput="filterStudents(this.value)">
      </div>
    </div>

    <div id="student-list">
      <?php foreach ($sessionStudents as $s):
        $isPresent = !empty($s['att_id']);
        $hasFp     = (int)$s['has_fp'];
        $col       = stuColor2((int)$s['id'], $colors);
        $ini       = initials($s['full_name']);
      ?>
      <div class="stu-row<?= $isPresent ? ' stu-present' : '' ?>"
           data-name="<?= strtolower(htmlspecialchars($s['full_name'])) ?>"
           data-matric="<?= strtolower(htmlspecialchars($s['matric_no'])) ?>"
           style="display:flex;align-items:center;gap:12px;padding:11px 18px;
                  border-bottom:1px solid #f0f2f8;
                  background:<?= $isPresent ? '#f0fdf8' : 'transparent' ?>;
                  transition:background .15s"
           id="srow-<?= $s['id'] ?>">
        <div class="av av-sm" style="background:<?= $col ?>"><?= $ini ?></div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($s['full_name']) ?></div>
          <div style="font-size:11px;color:var(--muted)" class="mono"><?= htmlspecialchars($s['matric_no']) ?></div>
        </div>

        <!-- Verified at time (if present) -->
        <div style="font-size:11px;color:var(--muted);min-width:72px;text-align:right">
          <?php if ($isPresent): ?>
            <div style="color:var(--green);font-weight:600"><?= date('g:i A', strtotime($s['verified_at'])) ?></div>
            <div><?= $s['method'] === 'biometric' ? '☝️ Bio' : '✍️ Manual' ?></div>
          <?php endif; ?>
        </div>

        <!-- Status badge -->
        <?php if ($isPresent): ?>
          <span class="badge badge-green" id="badge-<?= $s['id'] ?>"><i class="fa-solid fa-circle-check"></i> Present</span>
        <?php elseif (!$hasFp): ?>
          <span class="badge badge-amber" id="badge-<?= $s['id'] ?>"><i class="fa-solid fa-triangle-exclamation"></i> No Fingerprint</span>
        <?php else: ?>
          <span class="badge badge-gray" id="badge-<?= $s['id'] ?>"><i class="fa-regular fa-circle"></i> Absent</span>
        <?php endif; ?>

        <!-- Action buttons -->
        <?php if (!$isClosed): ?>
          <?php if ($isPresent): ?>
            <button class="btn btn-ghost btn-sm" style="font-size:11px"
              onclick="unmark(<?= $s['id'] ?>, <?= $selSessionId ?>)">Undo</button>
          <?php elseif ($hasFp): ?>
            <button class="btn btn-primary btn-sm"
              onclick="openScanModal(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['full_name'])) ?>', '<?= $ini ?>', '<?= $col ?>', <?= $selSessionId ?>)">
              <i class="fa-solid fa-fingerprint"></i> Scan</button>
          <?php else: ?>
            <button class="btn btn-ghost btn-sm" style="font-size:11px"
              onclick="markManual(<?= $s['id'] ?>, <?= $selSessionId ?>)">
              <i class="fa-solid fa-pen"></i> Manual</button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php if (empty($sessionStudents)): ?>
        <div class="empty-state"><i class="fa-solid fa-users"></i><p>No active students found.</p></div>
      <?php endif; ?>
    </div>
  </div>

<?php endif; ?>
</div>
</div>

<!-- ══ Close session modal ════════════════════════════════════════════════ -->
<div class="modal-overlay" id="close-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i class="fa-solid fa-lock"></i> Close Session</span>
      <button class="modal-close" onclick="closeModal('close-modal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST">
    <div class="modal-body">
      <input type="hidden" name="action" value="close_session">
      <input type="hidden" name="session_id" value="<?= $selSessionId ?>">
      <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i>
        Once closed, no more changes can be made unless you re-open the session.
      </div>
      <div class="form-group">
        <label>Notes (optional)</label>
        <textarea name="notes" placeholder="e.g. Lab session, quiz held, CAT week…"></textarea>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" onclick="closeModal('close-modal')">Cancel</button>
      <button type="submit" class="btn btn-danger"><i class="fa-solid fa-lock"></i> Close Session</button>
    </div>
    </form>
  </div>
</div>

<!-- ══ Biometric Scan Modal ═══════════════════════════════════════════════ -->
<div class="modal-overlay" id="scan-modal">
  <div class="modal" style="max-width:360px">
    <div class="modal-header">
      <span class="modal-title">Biometric Scan</span>
      <button class="modal-close" onclick="abortScan()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body" id="scan-body">
      <!-- Filled by JS -->
    </div>
  </div>
</div>

<script>
// ── Student list filter ──────────────────────────────────────────────────
function filterStudents(q) {
  q = q.toLowerCase();
  document.querySelectorAll('#student-list .stu-row').forEach(row => {
    const name   = row.dataset.name   || '';
    const matric = row.dataset.matric || '';
    row.style.display = (name.includes(q) || matric.includes(q)) ? '' : 'none';
  });
}

// ── Manual mark / unmark ─────────────────────────────────────────────────
function markManual(stuId, sessionId) {
  fetch('', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`action=mark_manual&session_id=${sessionId}&student_id=${stuId}`
  }).then(()=>location.reload());
}

function unmark(stuId, sessionId) {
  if (!confirm('Remove attendance for this student?')) return;
  fetch('', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`action=unmark&session_id=${sessionId}&student_id=${stuId}`
  }).then(()=>location.reload());
}

// ── Biometric scan flow ──────────────────────────────────────────────────
let _scanStuId = null, _scanSessionId = null, _scanAborted = false;

function openScanModal(stuId, name, ini, color, sessionId) {
  _scanStuId = stuId;
  _scanSessionId = sessionId;
  _scanAborted = false;
  renderScanIdle(name, ini, color, stuId, sessionId);
  openModal('scan-modal');
}

function abortScan() {
  _scanAborted = true;
  closeModal('scan-modal');
}

function renderScanIdle(name, ini, color, stuId, sessionId) {
  document.getElementById('scan-body').innerHTML = `
    <div style="text-align:center;padding:8px 0 20px">
      <div style="width:68px;height:68px;border-radius:18px;background:${color};display:flex;align-items:center;justify-content:center;
                  font-size:22px;font-weight:800;color:#fff;margin:0 auto 12px;box-shadow:0 8px 24px rgba(0,0,0,.12)">${ini}</div>
      <div style="font-size:18px;font-weight:800;margin-bottom:4px">${name}</div>
    </div>
    <div style="display:grid;gap:10px">
      <button class="btn btn-success w-full" style="padding:16px;font-size:15px;justify-content:center"
        onclick="showQrScan('${name}', '${ini}', '${color}', ${stuId}, ${sessionId})">
        <i class="fa-solid fa-qrcode"></i> &nbsp;Show QR Code
      </button>
      <button class="btn btn-primary w-full" style="padding:16px;font-size:15px;justify-content:center"
        onclick="startBiometricScan(${stuId}, '${name}', '${ini}', '${color}', ${sessionId})">
        <i class="fa-solid fa-fingerprint"></i> &nbsp;Scan Fingerprint
      </button>
    </div>
    <div style="font-size:12px;color:var(--muted);text-align:center;margin-top:10px;line-height:1.7">
      Students can scan the QR code with their phone camera to mark attendance automatically.
    </div>`;
}

async function showQrScan(name, ini, color, stuId, sessionId) {
  document.getElementById('scan-body').innerHTML = `
    <div style="text-align:center;padding:24px 0">
      <div style="width:68px;height:68px;border-radius:18px;background:${color};display:flex;align-items:center;justify-content:center;
                  font-size:22px;font-weight:800;color:#fff;margin:0 auto 16px">${ini}</div>
      <div style="font-size:16px;font-weight:700;margin-bottom:6px">${name}</div>
      <div style="display:flex;align-items:center;justify-content:center;gap:10px;color:var(--brand);font-weight:600;font-size:14px;margin-top:16px">
        <div class="fp-spinner"></div> Preparing QR…
      </div>
      <div style="font-size:12px;color:var(--muted);margin-top:8px">Please wait while the QR code is generated.</div>
    </div>`;

  try {
    const tokenRes = await fetch('<?= SITE_URL ?>/api/qr-token.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ student_id: stuId, session_id: sessionId })
    });
    const tokenData = await tokenRes.json();
    if (!tokenData.url) {
      throw new Error(tokenData.error || 'Unable to generate QR code');
    }

    document.getElementById('scan-body').innerHTML = `
      <div style="text-align:center;padding:8px 0 16px">
        <div style="width:68px;height:68px;border-radius:18px;background:${color};display:flex;align-items:center;justify-content:center;
                    font-size:22px;font-weight:800;color:#fff;margin:0 auto 12px">${ini}</div>
        <div style="font-size:18px;font-weight:800;margin-bottom:6px">${name}</div>
        <div id="qr-container" style="display:inline-block;margin:0 auto 14px"></div>
        <div style="font-size:13px;color:var(--muted);line-height:1.6;max-width:260px;margin:0 auto">
          Ask the student to open their phone camera and scan the QR code. The attendance will be recorded automatically.
        </div>
      </div>
      <button class="btn btn-ghost w-full" style="justify-content:center" onclick="renderScanIdle('${name}', '${ini}', '${color}', ${stuId}, ${sessionId})">
        <i class="fa-solid fa-arrow-left"></i> Back
      </button>`;

    new QRCode(document.getElementById('qr-container'), {
      text: tokenData.url,
      width: 220,
      height: 220,
      colorDark: '#000000',
      colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.H
    });
  } catch (err) {
    document.getElementById('scan-body').innerHTML = `
      <div style="text-align:center;padding:24px 0">
        <div style="font-size:48px;margin-bottom:12px">⚠️</div>
        <div style="font-size:15px;font-weight:700;color:var(--amber);margin-bottom:6px">Could not generate QR</div>
        <div style="font-size:12px;color:var(--muted);line-height:1.6">${err.message || 'Please try again.'}</div>
      </div>
      <button class="btn btn-ghost w-full" style="justify-content:center" onclick="renderScanIdle('${name}', '${ini}', '${color}', ${stuId}, ${sessionId})">
        <i class="fa-solid fa-arrow-left"></i> Back
      </button>`;
  }
}

async function startBiometricScan(stuId, name, ini, color, sessionId) {
  if (_scanAborted) return;
  // Show scanning state
  document.getElementById('scan-body').innerHTML = `
    <div style="text-align:center;padding:24px 0">
      <div style="width:68px;height:68px;border-radius:18px;background:${color};display:flex;align-items:center;justify-content:center;
                  font-size:22px;font-weight:800;color:#fff;margin:0 auto 16px">${ini}</div>
      <div style="font-size:16px;font-weight:700;margin-bottom:6px">${name}</div>
      <div style="display:flex;align-items:center;justify-content:center;gap:10px;color:var(--brand);font-weight:600;font-size:14px;margin-top:16px">
        <div class="fp-spinner"></div> Scanning…
      </div>
      <div style="font-size:12px;color:var(--muted);margin-top:8px">Hold finger steady on sensor</div>
    </div>`;

  // Attempt WebAuthn credential.get() for real device biometrics
  let success = false;
  if (window.PublicKeyCredential) {
    try {
      // Fetch challenge from server
      const chalRes = await fetch('<?= SITE_URL ?>/api/webauthn-challenge.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({student_id: stuId, action:'verify'})
      });
      const chalData = await chalRes.json();

      if (chalData.challenge) {
        const credOpts = {
          challenge: Uint8Array.from(atob(chalData.challenge), c=>c.charCodeAt(0)),
          timeout: 30000,
          userVerification: 'preferred',
          rpId: window.location.hostname,
        };
        if (chalData.credential_id) {
          credOpts.allowCredentials = [{
            id: Uint8Array.from(atob(chalData.credential_id), c=>c.charCodeAt(0)),
            type:'public-key'
          }];
        }
        const cred = await navigator.credentials.get({publicKey: credOpts});
        // Send assertion to server for verification
        const verRes = await fetch('<?= SITE_URL ?>/api/verify-attendance.php', {
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body: JSON.stringify({
            student_id: stuId,
            session_id: sessionId,
            credential_id: btoa(String.fromCharCode(...new Uint8Array(cred.rawId))),
            authenticator_data: btoa(String.fromCharCode(...new Uint8Array(cred.response.authenticatorData))),
            signature: btoa(String.fromCharCode(...new Uint8Array(cred.response.signature))),
            client_data_json: btoa(String.fromCharCode(...new Uint8Array(cred.response.clientDataJSON))),
          })
        });
        const verData = await verRes.json();
        success = verData.success === true;
      }
    } catch(e) {
      console.warn('WebAuthn error:', e.message);
      // Fallback: mark manually if biometrics not enrolled or cancelled
      success = false;
    }
  }

  if (_scanAborted) return;

  if (success) {
    showScanSuccess(name, ini, color, stuId, sessionId);
  } else {
    showScanFallback(stuId, name, ini, color, sessionId);
  }
}

function showScanSuccess(name, ini, color, stuId, sessionId) {
  document.getElementById('scan-body').innerHTML = `
    <div style="text-align:center;padding:16px 0 24px">
      <div style="font-size:60px;margin-bottom:12px">✅</div>
      <div style="font-size:18px;font-weight:800;color:var(--green);margin-bottom:6px">Attendance Recorded!</div>
      <div style="font-size:13px;color:var(--muted)">${name} marked present</div>
    </div>`;
  setTimeout(()=>{ closeModal('scan-modal'); location.reload(); }, 1800);
}

function showScanFallback(stuId, name, ini, color, sessionId) {
  document.getElementById('scan-body').innerHTML = `
    <div style="text-align:center;padding:8px 0 16px">
      <div style="font-size:48px;margin-bottom:10px">⚠️</div>
      <div style="font-size:15px;font-weight:700;color:var(--amber);margin-bottom:6px">Biometric scan unavailable</div>
      <div style="font-size:12px;color:var(--muted);margin-bottom:16px;line-height:1.6">
        This device may not support biometrics, or the student's fingerprint is not enrolled on this device.<br>
        You can mark manually instead.
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <button class="btn btn-success w-full" style="justify-content:center"
        onclick="confirmManualMark(${stuId}, '${name}', ${sessionId})">
        <i class="fa-solid fa-pen-to-square"></i> Mark Present Manually
      </button>
      <button class="btn btn-ghost w-full" style="justify-content:center" onclick="abortScan()">Cancel</button>
    </div>`;
}

function confirmManualMark(stuId, name, sessionId) {
  fetch('', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`action=mark_manual&session_id=${sessionId}&student_id=${stuId}`
  }).then(()=>{
    document.getElementById('scan-body').innerHTML = `
      <div style="text-align:center;padding:16px 0 24px">
        <div style="font-size:60px;margin-bottom:12px">✅</div>
        <div style="font-size:18px;font-weight:800;color:var(--green);margin-bottom:6px">Marked Present</div>
        <div style="font-size:13px;color:var(--muted)">${name} — marked manually</div>
      </div>`;
    setTimeout(()=>{ closeModal('scan-modal'); location.reload(); }, 1600);
  });
}
</script>

<style>
.fp-spinner{width:20px;height:20px;border:2.5px solid rgba(92,78,247,.25);border-top-color:var(--brand);border-radius:50%;animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<?php layout_foot(); ?>
