<?php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/layout.php';
requireLogin();

$db = db();

// ── Filters ───────────────────────────────────────────────────────────────
$filterCourse  = (int)($_GET['course_id'] ?? 0);
$filterStudent = (int)($_GET['student_id'] ?? 0);
$filterFrom    = $_GET['date_from'] ?? date('Y-m-01');
$filterTo      = $_GET['date_to']   ?? date('Y-m-d');
$sessionId     = (int)($_GET['session_id'] ?? 0);

$courses  = $db->query('SELECT id, code, title FROM courses WHERE is_active=TRUE ORDER BY code')->fetchAll();
$students = $db->query('SELECT id, matric_no, full_name FROM students WHERE is_active=TRUE ORDER BY full_name')->fetchAll();

$totalStudents = $db->query('SELECT COUNT(*) FROM students WHERE is_active=TRUE')->fetchColumn();

// ── Overview stats ────────────────────────────────────────────────────────
$statSessions = $db->prepare('
    SELECT COUNT(*) FROM att_sessions s
    JOIN timetable t ON s.timetable_id=t.id
    WHERE s.session_date BETWEEN ? AND ?
    ' . ($filterCourse ? 'AND t.course_id=?' : ''));
$p = [$filterFrom, $filterTo];
if ($filterCourse) $p[] = $filterCourse;
$statSessions->execute($p);
$numSessions = $statSessions->fetchColumn();

$statPresence = $db->prepare('
    SELECT COUNT(*) FROM attendance a
    JOIN att_sessions s ON a.session_id=s.id
    JOIN timetable t ON s.timetable_id=t.id
    WHERE s.session_date BETWEEN ? AND ?
    ' . ($filterCourse ? 'AND t.course_id=?' : ''));
$statPresence->execute($p);
$numPresences = $statPresence->fetchColumn();

$avgRate = ($numSessions > 0 && $totalStudents > 0)
    ? round($numPresences / ($numSessions * $totalStudents) * 100)
    : 0;

// ── Per-course breakdown ──────────────────────────────────────────────────
$courseBreakdown = $db->prepare('
    SELECT c.id, c.code, c.title, c.instructor,
           COUNT(DISTINCT s.id) AS sessions,
           COUNT(a.id)          AS presences,
           ? AS total_students
    FROM courses c
    LEFT JOIN timetable t ON t.course_id=c.id
    LEFT JOIN att_sessions s ON s.timetable_id=t.id AND s.session_date BETWEEN ? AND ?
    LEFT JOIN attendance a ON a.session_id=s.id
    WHERE c.is_active=TRUE
    GROUP BY c.id
    ORDER BY c.code
');
$courseBreakdown->execute([$totalStudents, $filterFrom, $filterTo]);
$courseStats = $courseBreakdown->fetchAll();

// ── Per-student breakdown ─────────────────────────────────────────────────
$stuParams  = [$filterFrom, $filterTo];
$stuWhere   = '';
if ($filterCourse)  { $stuWhere  .= ' AND t.course_id=?';  $stuParams[] = $filterCourse; }
if ($filterStudent) { $stuWhere  .= ' AND st.id=?';         $stuParams[] = $filterStudent; }
$stuParams2 = [$filterFrom, $filterTo];
if ($filterCourse) $stuParams2[] = $filterCourse;

$studentBreakdown = $db->prepare("
    SELECT st.id, st.matric_no, st.full_name, st.gender,
           COUNT(DISTINCT a.session_id) AS attended,
           (SELECT COUNT(DISTINCT s2.id) FROM att_sessions s2
            JOIN timetable t2 ON s2.timetable_id=t2.id
            WHERE s2.session_date BETWEEN ? AND ?
            " . ($filterCourse ? 'AND t2.course_id=?' : '') . "
           ) AS total_sess
    FROM students st
    LEFT JOIN attendance a ON a.student_id=st.id
    LEFT JOIN att_sessions s ON a.session_id=s.id AND s.session_date BETWEEN ? AND ?
    LEFT JOIN timetable t ON s.timetable_id=t.id
    {$stuWhere}
    WHERE st.is_active=TRUE
    GROUP BY st.id
    ORDER BY attended DESC, st.full_name
");
$finalParams = array_merge($stuParams2, [$filterFrom, $filterTo], ($filterCourse ? [$filterCourse] : []), ($filterStudent ? [$filterStudent] : []));
$studentBreakdown->execute($finalParams);
$studentStats = $studentBreakdown->fetchAll();

// ── Single session report ─────────────────────────────────────────────────
$singleSession = null;
$singlePresent = [];
if ($sessionId) {
    $ss = $db->prepare('
        SELECT s.*, t.start_time,t.end_time,t.venue, c.code,c.title,c.instructor
        FROM att_sessions s
        JOIN timetable t ON s.timetable_id=t.id
        JOIN courses c ON t.course_id=c.id
        WHERE s.id=?
    ');
    $ss->execute([$sessionId]);
    $singleSession = $ss->fetch();

    $sp = $db->prepare('
        SELECT st.*, a.verified_at, a.method
        FROM attendance a
        JOIN students st ON a.student_id=st.id
        WHERE a.session_id=?
        ORDER BY a.verified_at
    ');
    $sp->execute([$sessionId]);
    $singlePresent = $sp->fetchAll();
}

// Grade helpers
function rateGrade(int $pct): array {
    if ($pct >= 80) return ['A', 'badge-green'];
    if ($pct >= 70) return ['B', 'badge-green'];
    if ($pct >= 60) return ['C', 'badge-blue'];
    if ($pct >= 50) return ['D', 'badge-amber'];
    return ['F', 'badge-red'];
}

$colors = ['#5c4ef7','#22c48c','#3b8df9','#f5a524','#e84646','#a855f7','#e05c7a','#f97316','#14b8a6','#ec4899'];
function rColor(int $id, array $c): string { return $c[$id % count($c)]; }

layout_head('Reports', 'reports');
layout_topbar('Reports',
    '<a href="?export=1&course_id='.$filterCourse.'&date_from='.$filterFrom.'&date_to='.$filterTo.'" class="btn btn-ghost btn-sm"><i class="fa-solid fa-download"></i> Export CSV</a>'
);

// ── CSV Export ────────────────────────────────────────────────────────────
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Matric No', 'Full Name', 'Gender', 'Sessions Attended', 'Total Sessions', 'Attendance Rate', 'Grade']);
    foreach ($studentStats as $s) {
        $rate = $s['total_sess'] > 0 ? round($s['attended']/$s['total_sess']*100) : 0;
        [$grade] = rateGrade($rate);
        fputcsv($out, [$s['matric_no'], $s['full_name'], $s['gender'], $s['attended'], $s['total_sess'], $rate.'%', $grade]);
    }
    fclose($out);
    exit;
}
?>

<!-- ── Filter bar ──────────────────────────────────────────────────────── -->
<form method="GET" style="background:#fff;border:1px solid var(--border);border-radius:14px;padding:16px 20px;margin-bottom:22px">
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;align-items:end">
    <div class="form-group" style="margin:0">
      <label>Course</label>
      <select name="course_id">
        <option value="0">All Courses</option>
        <?php foreach ($courses as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $filterCourse===$c['id']?'selected':'' ?>>
            <?= htmlspecialchars($c['code']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0">
      <label>Student</label>
      <select name="student_id">
        <option value="0">All Students</option>
        <?php foreach ($students as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $filterStudent===$s['id']?'selected':'' ?>>
            <?= htmlspecialchars($s['full_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0">
      <label>From</label>
      <input type="date" name="date_from" value="<?= $filterFrom ?>">
    </div>
    <div class="form-group" style="margin:0">
      <label>To</label>
      <input type="date" name="date_to" value="<?= $filterTo ?>">
    </div>
    <div>
      <button type="submit" class="btn btn-primary w-full"><i class="fa-solid fa-filter"></i> Apply</button>
    </div>
    <div>
      <a href="reports.php" class="btn btn-ghost w-full"><i class="fa-solid fa-rotate-left"></i> Reset</a>
    </div>
  </div>
</form>

<!-- ── Single session view ─────────────────────────────────────────────── -->
<?php if ($singleSession): ?>
<div class="card mb-4">
  <div class="card-header">
    <span class="card-title">
      <i class="fa-solid fa-calendar-day c-brand"></i>
      Session: <?= htmlspecialchars($singleSession['code']) ?> — <?= date('D, M j Y', strtotime($singleSession['session_date'])) ?>
    </span>
    <a href="reports.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-xmark"></i> Clear</a>
  </div>
  <div style="padding:16px 20px;display:flex;gap:24px;border-bottom:1px solid var(--border)">
    <div><div style="font-size:11px;color:var(--muted)">Course</div><div style="font-weight:700"><?= htmlspecialchars($singleSession['code']) ?> — <?= htmlspecialchars($singleSession['title']) ?></div></div>
    <div><div style="font-size:11px;color:var(--muted)">Date</div><div style="font-weight:700"><?= date('D, M j Y', strtotime($singleSession['session_date'])) ?></div></div>
    <div><div style="font-size:11px;color:var(--muted)">Time</div><div style="font-weight:700"><?= date('g:i A', strtotime($singleSession['start_time'])) ?>–<?= date('g:i A', strtotime($singleSession['end_time'])) ?></div></div>
    <div><div style="font-size:11px;color:var(--muted)">Venue</div><div style="font-weight:700"><?= htmlspecialchars($singleSession['venue'] ?? '—') ?></div></div>
    <div><div style="font-size:11px;color:var(--muted)">Present</div>
      <div style="font-weight:800;color:var(--green)"><?= count($singlePresent) ?>/<?= $totalStudents ?></div>
    </div>
    <div><div style="font-size:11px;color:var(--muted)">Rate</div>
      <?php $sr = $totalStudents>0?round(count($singlePresent)/$totalStudents*100):0; ?>
      <div style="font-weight:800;color:<?= $sr>=70?'var(--green)':($sr>=50?'var(--amber)':'var(--red)') ?>"><?= $sr ?>%</div>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th></th><th>Name</th><th>Matric No.</th><th>Checked In</th><th>Method</th></tr></thead>
      <tbody>
        <?php foreach ($singlePresent as $p):
          $col = rColor((int)$p['id'], $colors);
        ?>
        <tr>
          <td><div class="av av-sm" style="background:<?= $col ?>"><?= initials($p['full_name']) ?></div></td>
          <td style="font-weight:600"><?= htmlspecialchars($p['full_name']) ?></td>
          <td class="mono"><?= htmlspecialchars($p['matric_no']) ?></td>
          <td><?= date('g:i:s A', strtotime($p['verified_at'])) ?></td>
          <td>
            <?= $p['method']==='biometric'
              ? '<span class="badge badge-brand"><i class="fa-solid fa-fingerprint"></i> Biometric</span>'
              : '<span class="badge badge-gray"><i class="fa-solid fa-pen"></i> Manual</span>' ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($singlePresent)): ?>
          <tr><td colspan="5" class="text-center" style="color:var(--muted);padding:24px">No students were marked present in this session.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ── Overview stats ──────────────────────────────────────────────────── -->
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr)">
  <div class="stat-card">
    <div class="stat-lbl">Sessions</div>
    <div class="stat-val c-brand"><?= $numSessions ?></div>
    <div class="stat-sub"><?= $filterFrom ?> → <?= $filterTo ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-lbl">Total Check-ins</div>
    <div class="stat-val c-green"><?= $numPresences ?></div>
    <div class="stat-sub">across all sessions</div>
  </div>
  <div class="stat-card">
    <div class="stat-lbl">Avg Attendance</div>
    <div class="stat-val" style="color:<?= $avgRate>=70?'var(--green)':($avgRate>=50?'var(--amber)':'var(--red)') ?>"><?= $avgRate ?>%</div>
    <div class="stat-sub"><?= $avgRate>=70?'Healthy':'Needs improvement' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-lbl">Students At Risk</div>
    <?php $atRisk = count(array_filter($studentStats, fn($s)=>
        $s['total_sess']>0 && ($s['attended']/$s['total_sess'])<0.5));
    ?>
    <div class="stat-val c-red"><?= $atRisk ?></div>
    <div class="stat-sub">below 50% attendance</div>
  </div>
</div>

<div class="grid-2" style="align-items:start">

  <!-- ── Course breakdown ── -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fa-solid fa-book c-brand"></i> By Course</span>
    </div>
    <div style="padding:16px 20px;display:flex;flex-direction:column;gap:14px">
      <?php foreach ($courseStats as $c):
        $pct = ($c['sessions'] > 0 && $c['total_students'] > 0)
               ? round($c['presences'] / ($c['sessions'] * $c['total_students']) * 100) : 0;
        $barCol = $pct >= 70 ? 'var(--green)' : ($pct >= 50 ? 'var(--amber)' : 'var(--red)');
      ?>
      <div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px">
          <div>
            <span style="font-weight:700;font-size:13px"><?= htmlspecialchars($c['code']) ?></span>
            <span style="font-size:12px;color:var(--muted);margin-left:6px"><?= htmlspecialchars($c['title']) ?></span>
          </div>
          <div style="display:flex;align-items:center;gap:8px">
            <span style="font-size:11px;color:var(--muted)"><?= $c['sessions'] ?> sess</span>
            <span style="font-weight:800;font-size:14px;color:<?= $barCol ?>"><?= $pct ?>%</span>
          </div>
        </div>
        <div class="progress-bar-wrap">
          <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $barCol ?>"></div>
        </div>
        <div style="font-size:11px;color:var(--muted);margin-top:3px"><?= htmlspecialchars($c['instructor']) ?></div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($courseStats)): ?>
        <div class="empty-state"><i class="fa-solid fa-chart-bar"></i><p>No data for this period.</p></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Attendance distribution chart ── -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fa-solid fa-chart-pie c-brand"></i> Attendance Distribution</span>
    </div>
    <div style="padding:20px">
      <?php
      $brackets = ['90–100%'=>0,'75–89%'=>0,'60–74%'=>0,'50–59%'=>0,'<50%'=>0];
      foreach ($studentStats as $s) {
          $r = $s['total_sess']>0 ? ($s['attended']/$s['total_sess']*100) : 0;
          if     ($r >= 90) $brackets['90–100%']++;
          elseif ($r >= 75) $brackets['75–89%']++;
          elseif ($r >= 60) $brackets['60–74%']++;
          elseif ($r >= 50) $brackets['50–59%']++;
          else              $brackets['<50%']++;
      }
      $bColors = ['#22c48c','#3b8df9','#5c4ef7','#f5a524','#e84646'];
      $bi = 0;
      foreach ($brackets as $label => $count):
          $bPct = count($studentStats) > 0 ? round($count/count($studentStats)*100) : 0;
      ?>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
        <div style="width:90px;font-size:12px;font-weight:600;color:var(--text)"><?= $label ?></div>
        <div class="progress-bar-wrap">
          <div class="progress-bar" style="width:<?= $bPct ?>%;background:<?= $bColors[$bi] ?>"></div>
        </div>
        <div style="font-size:12px;font-weight:700;min-width:28px;text-align:right"><?= $count ?></div>
      </div>
      <?php $bi++; endforeach; ?>

      <div class="divider"></div>

      <!-- Risk alert -->
      <?php if ($atRisk > 0): ?>
      <div class="alert alert-error" style="margin:0">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div><strong><?= $atRisk ?> student<?= $atRisk>1?'s are':' is' ?> at risk</strong> — below 50% attendance. Review the table below.</div>
      </div>
      <?php else: ?>
      <div class="alert alert-success" style="margin:0">
        <i class="fa-solid fa-circle-check"></i> All students are above 50% attendance. 
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- ── Per-student table ───────────────────────────────────────────────── -->
<div class="card mt-4">
  <div class="card-header">
    <span class="card-title"><i class="fa-solid fa-users c-brand"></i> Student Attendance Register</span>
    <div style="display:flex;gap:8px;align-items:center">
      <input id="stu-search" placeholder="Filter students…" style="width:180px;padding:6px 10px;font-size:12px;height:32px"
             oninput="filterTable(this.value)">
      <a href="?export=1&course_id=<?= $filterCourse ?>&date_from=<?= $filterFrom ?>&date_to=<?= $filterTo ?>"
         class="btn btn-ghost btn-sm"><i class="fa-solid fa-download"></i> CSV</a>
    </div>
  </div>
  <div class="table-wrap">
    <table id="stu-table">
      <thead>
        <tr>
          <th>#</th><th></th><th>Name</th><th>Matric</th><th>Gender</th>
          <th style="text-align:center">Attended</th>
          <th style="text-align:center">Total</th>
          <th style="text-align:center">Rate</th>
          <th style="text-align:center">Grade</th>
          <th style="text-align:center">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($studentStats as $idx => $s):
          $rate = $s['total_sess'] > 0 ? round($s['attended'] / $s['total_sess'] * 100) : 0;
          [$grade, $gradeBadge] = rateGrade($rate);
          $rateColor = $rate >= 70 ? 'var(--green)' : ($rate >= 50 ? 'var(--amber)' : 'var(--red)');
          $col = rColor((int)$s['id'], $colors);
          $statusBadge = $rate >= 75 ? 'badge-green' : ($rate >= 50 ? 'badge-amber' : 'badge-red');
          $statusLabel = $rate >= 75 ? 'Good' : ($rate >= 50 ? 'Warning' : 'At Risk');
        ?>
        <tr data-name="<?= strtolower(htmlspecialchars($s['full_name'])) ?>"
            data-matric="<?= strtolower(htmlspecialchars($s['matric_no'])) ?>">
          <td class="text-muted text-sm"><?= $idx+1 ?></td>
          <td><div class="av av-sm" style="background:<?= $col ?>"><?= initials($s['full_name']) ?></div></td>
          <td style="font-weight:600"><?= htmlspecialchars($s['full_name']) ?></td>
          <td class="mono"><?= htmlspecialchars($s['matric_no']) ?></td>
          <td style="color:var(--muted);font-size:12px"><?= $s['gender'] ?></td>
          <td style="text-align:center;font-weight:700;color:var(--green)"><?= $s['attended'] ?></td>
          <td style="text-align:center;color:var(--muted)"><?= $s['total_sess'] ?></td>
          <td style="text-align:center">
            <div style="display:flex;align-items:center;justify-content:center;gap:6px">
              <div class="progress-bar-wrap" style="width:60px">
                <div class="progress-bar" style="width:<?= $rate ?>%;background:<?= $rateColor ?>"></div>
              </div>
              <span style="font-weight:800;font-size:13px;color:<?= $rateColor ?>"><?= $rate ?>%</span>
            </div>
          </td>
          <td style="text-align:center"><span class="badge <?= $gradeBadge ?>"><?= $grade ?></span></td>
          <td style="text-align:center"><span class="badge <?= $statusBadge ?>"><?= $statusLabel ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($studentStats)): ?>
          <tr><td colspan="10" class="empty-state"><i class="fa-solid fa-inbox"></i><p>No data.</p></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Session history ────────────────────────────────────────────────── -->
<div class="card mt-4">
  <div class="card-header">
    <span class="card-title"><i class="fa-solid fa-clock-rotate-left c-brand"></i> Session History</span>
    <span class="badge badge-brand"><?= $numSessions ?> sessions</span>
  </div>
  <div class="table-wrap">
    <?php
    $histParams = [$filterFrom, $filterTo];
    $histWhere  = '';
    if ($filterCourse) { $histWhere .= ' AND t.course_id=?'; $histParams[] = $filterCourse; }
    $histStmt = $db->prepare("
        SELECT s.*, c.code, c.title, t.start_time, t.end_time, t.venue,
               COUNT(a.id) AS present_count
        FROM att_sessions s
        JOIN timetable t ON s.timetable_id=t.id
        JOIN courses c ON t.course_id=c.id
        LEFT JOIN attendance a ON a.session_id=s.id
        WHERE s.session_date BETWEEN ? AND ? {$histWhere}
        GROUP BY s.id
        ORDER BY s.session_date DESC, t.start_time DESC
        LIMIT 80
    ");
    $histStmt->execute($histParams);
    $sessions = $histStmt->fetchAll();
    ?>
    <table>
      <thead>
        <tr><th>Date</th><th>Course</th><th>Time</th><th>Venue</th>
            <th style="text-align:center">Present</th>
            <th style="text-align:center">Rate</th>
            <th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($sessions as $sess):
          $sRate = $totalStudents > 0 ? round($sess['present_count']/$totalStudents*100) : 0;
          $sColor = $sRate>=70?'var(--green)':($sRate>=50?'var(--amber)':'var(--red)');
        ?>
        <tr>
          <td style="font-weight:600"><?= date('D, M j Y', strtotime($sess['session_date'])) ?></td>
          <td><span class="badge badge-brand"><?= htmlspecialchars($sess['code']) ?></span></td>
          <td class="mono" style="font-size:12px">
            <?= date('g:i', strtotime($sess['start_time'])) ?>–<?= date('g:i A', strtotime($sess['end_time'])) ?>
          </td>
          <td style="color:var(--muted);font-size:12px"><?= htmlspecialchars($sess['venue'] ?? '—') ?></td>
          <td style="text-align:center;font-weight:700;color:var(--green)"><?= $sess['present_count'] ?>/<?= $totalStudents ?></td>
          <td style="text-align:center;font-weight:800;color:<?= $sColor ?>"><?= $sRate ?>%</td>
          <td>
            <?= $sess['closed_at']
              ? '<span class="badge badge-gray"><i class="fa-solid fa-lock"></i> Closed</span>'
              : '<span class="badge badge-green"><i class="fa-solid fa-lock-open"></i> Open</span>' ?>
          </td>
          <td>
            <div style="display:flex;gap:6px">
              <a href="?session_id=<?= $sess['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="View session">
                <i class="fa-solid fa-eye"></i></a>
              <a href="<?= SITE_URL ?>/admin/attendance.php?session_id=<?= $sess['id'] ?>&date=<?= $sess['session_date'] ?>"
                 class="btn btn-ghost btn-sm btn-icon" title="Open in attendance">
                <i class="fa-solid fa-pen-to-square"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($sessions)): ?>
          <tr><td colspan="8" class="empty-state" style="padding:32px;text-align:center;color:var(--muted)">No sessions in this period.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function filterTable(q) {
  q = q.toLowerCase();
  document.querySelectorAll('#stu-table tbody tr').forEach(row => {
    const name   = row.dataset.name   || '';
    const matric = row.dataset.matric || '';
    row.style.display = (name.includes(q) || matric.includes(q)) ? '' : 'none';
  });
}
</script>

<?php layout_foot(); ?>
