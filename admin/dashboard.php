<?php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/layout.php';
requireLogin();

$db    = db();
$today = date('Y-m-d');
$todayDow = (int)date('N'); // 1=Mon … 7=Sun → convert to 0-indexed Sun-Sat
$dow  = (int)date('w'); // 0=Sun … 6=Sat

// Summary stats
$totalStudents  = $db->query('SELECT COUNT(*) FROM students WHERE is_active=TRUE')->fetchColumn();
$totalCourses   = $db->query('SELECT COUNT(*) FROM courses WHERE is_active=TRUE')->fetchColumn();
$totalSessions  = $db->query('SELECT COUNT(*) FROM att_sessions')->fetchColumn();

// Sessions today (by timetable slots matching today's day_of_week)
$stToday = $db->prepare('
    SELECT COUNT(*) FROM att_sessions s
    JOIN timetable t ON s.timetable_id = t.id
    WHERE s.session_date = ?
');
$stToday->execute([$today]);
$sessionsToday = $stToday->fetchColumn();

// Today's timetable
$ttToday = $db->prepare('
    SELECT t.*, c.code, c.title, c.instructor,
           s.id AS session_id, s.closed_at,
           (SELECT COUNT(*) FROM attendance a WHERE a.session_id = s.id) AS present_count
    FROM timetable t
    JOIN courses c ON t.course_id = c.id
    LEFT JOIN att_sessions s ON s.timetable_id = t.id AND s.session_date = ?
    WHERE t.day_of_week = ? AND c.is_active = TRUE
    ORDER BY t.start_time
');
$ttToday->execute([$today, $dow]);
$todayClasses = $ttToday->fetchAll();

// Recent attendance sessions (last 10)
$recentSess = $db->prepare('
    SELECT s.*, c.code, c.title, t.start_time, t.venue,
           (SELECT COUNT(*) FROM attendance a WHERE a.session_id = s.id) AS present_count
    FROM att_sessions s
    JOIN timetable t ON s.timetable_id = t.id
    JOIN courses c ON t.course_id = c.id
    ORDER BY s.created_at DESC LIMIT 10
');
$recentSess->execute();
$recentSessions = $recentSess->fetchAll();

// Fingerprint coverage
$fpCount = $db->query('SELECT COUNT(*) FROM webauthn_credentials')->fetchColumn();

layout_head('Dashboard', 'dashboard');
layout_topbar('Dashboard');
?>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-lbl">Total Students</div>
    <div class="stat-val c-brand"><?= $totalStudents ?></div>
    <div class="stat-sub"><?= $fpCount ?> fingerprints registered</div>
  </div>
  <div class="stat-card">
    <div class="stat-lbl">Active Courses</div>
    <div class="stat-val c-blue"><?= $totalCourses ?></div>
    <div class="stat-sub">this semester</div>
  </div>
  <div class="stat-card">
    <div class="stat-lbl">Sessions Recorded</div>
    <div class="stat-val c-green"><?= $totalSessions ?></div>
    <div class="stat-sub"><?= $sessionsToday ?> today</div>
  </div>
  <div class="stat-card">
    <div class="stat-lbl">Biometric Coverage</div>
    <div class="stat-val c-amber"><?= $totalStudents > 0 ? round($fpCount/$totalStudents*100) : 0 ?>%</div>
    <div class="stat-sub"><?= $totalStudents - $fpCount ?> still unregistered</div>
  </div>
</div>

<div class="grid-2">
  <!-- Today's classes -->
  <div class="card mb-4">
    <div class="card-header">
      <span class="card-title"><i class="fa-solid fa-calendar-day c-brand"></i> Today's Classes</span>
      <span class="badge badge-blue"><?= date('l') ?></span>
    </div>
    <?php if (empty($todayClasses)): ?>
      <div class="empty-state"><i class="fa-solid fa-mug-hot"></i><p>No classes scheduled today. Enjoy!</p></div>
    <?php else: ?>
      <div style="padding:8px 0">
        <?php foreach ($todayClasses as $cls):
          $hasSession = !empty($cls['session_id']);
          $pct = $totalStudents > 0 ? round($cls['present_count'] / $totalStudents * 100) : 0;
        ?>
        <div style="padding:12px 20px;border-bottom:1px solid #f0f2f8;display:flex;align-items:center;gap:12px">
          <div style="width:4px;height:48px;border-radius:4px;background:var(--brand);flex-shrink:0"></div>
          <div style="flex:1;min-width:0">
            <div style="font-weight:700;font-size:13.5px"><?= htmlspecialchars($cls['code']) ?> — <?= htmlspecialchars($cls['title']) ?></div>
            <div style="font-size:11px;color:var(--muted);margin-top:2px">
              <?= date('g:i A', strtotime($cls['start_time'])) ?> – <?= date('g:i A', strtotime($cls['end_time'])) ?>
              · <?= htmlspecialchars($cls['venue'] ?? 'TBD') ?>
            </div>
            <?php if ($hasSession): ?>
              <div style="margin-top:6px;display:flex;align-items:center;gap:8px">
                <div class="progress-bar-wrap"><div class="progress-bar" style="width:<?= $pct ?>%;background:var(--green)"></div></div>
                <span style="font-size:11px;font-weight:700;color:var(--green)"><?= $cls['present_count'] ?>/<?= $totalStudents ?></span>
              </div>
            <?php endif; ?>
          </div>
          <?php if ($hasSession): ?>
            <a href="<?= SITE_URL ?>/admin/attendance.php?session_id=<?= $cls['session_id'] ?>" class="btn btn-ghost btn-sm">View</a>
          <?php else: ?>
            <a href="<?= SITE_URL ?>/admin/attendance.php?open_tt=<?= $cls['id'] ?>&date=<?= $today ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-play"></i> Open</a>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Recent sessions -->
  <div class="card mb-4">
    <div class="card-header">
      <span class="card-title"><i class="fa-solid fa-clock-rotate-left c-brand"></i> Recent Sessions</span>
      <a href="<?= SITE_URL ?>/admin/attendance.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <?php if (empty($recentSessions)): ?>
      <div class="empty-state"><i class="fa-solid fa-inbox"></i><p>No sessions recorded yet.</p></div>
    <?php else: ?>
      <div style="padding:8px 0">
        <?php foreach ($recentSessions as $rs):
          $pct = $totalStudents > 0 ? round($rs['present_count'] / $totalStudents * 100) : 0;
          $color = $pct >= 70 ? 'var(--green)' : ($pct >= 50 ? 'var(--amber)' : 'var(--red)');
        ?>
        <div style="padding:10px 20px;border-bottom:1px solid #f0f2f8;display:flex;align-items:center;gap:10px">
          <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:600"><?= htmlspecialchars($rs['code']) ?> — <?= htmlspecialchars($rs['title']) ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= date('D, M j', strtotime($rs['session_date'])) ?> · <?= htmlspecialchars($rs['venue'] ?? '') ?></div>
          </div>
          <div style="text-align:right;flex-shrink:0">
            <div style="font-size:15px;font-weight:800;color:<?= $color ?>"><?= $pct ?>%</div>
            <div style="font-size:11px;color:var(--muted)"><?= $rs['present_count'] ?>/<?= $totalStudents ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php layout_foot(); ?>
