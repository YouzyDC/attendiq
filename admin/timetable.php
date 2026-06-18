<?php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/layout.php';
requireLogin();

$db  = db();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $course_id  = (int)($_POST['course_id'] ?? 0);
        $dow        = (int)($_POST['day_of_week'] ?? 1);
        $start      = $_POST['start_time'] ?? '';
        $end        = $_POST['end_time'] ?? '';
        $venue      = trim($_POST['venue'] ?? '');
        if (!$course_id || !$start || !$end) { $err = 'Course, start and end time are required.'; }
        elseif ($end <= $start) { $err = 'End time must be after start time.'; }
        else {
            try {
                if ($action === 'add') {
                    $db->prepare('INSERT INTO timetable (course_id,day_of_week,start_time,end_time,venue) VALUES (?,?,?,?,?)')
                       ->execute([$course_id,$dow,$start,$end,$venue]);
                    $msg = 'Timetable slot added.';
                } else {
                    $id = (int)($_POST['id'] ?? 0);
                    $db->prepare('UPDATE timetable SET course_id=?,day_of_week=?,start_time=?,end_time=?,venue=? WHERE id=?')
                       ->execute([$course_id,$dow,$start,$end,$venue,$id]);
                    $msg = 'Timetable slot updated.';
                }
            } catch (PDOException $e) { $err = 'A slot for that course on that day/time already exists.'; }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $used = $db->prepare('SELECT COUNT(*) FROM att_sessions WHERE timetable_id=?');
        $used->execute([$id]);
        if ($used->fetchColumn() > 0) { $err = 'Cannot delete: this slot has attendance sessions recorded.'; }
        else { $db->prepare('DELETE FROM timetable WHERE id=?')->execute([$id]); $msg = 'Slot removed.'; }
    }
}

$slots = $db->query('
    SELECT t.*, c.code, c.title, c.instructor
    FROM timetable t
    JOIN courses c ON t.course_id = c.id
    WHERE c.is_active = 1
    ORDER BY t.day_of_week, t.start_time
')->fetchAll();

$courses = $db->query('SELECT id, code, title FROM courses WHERE is_active=1 ORDER BY code')->fetchAll();

$days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
$dayColors = ['#9ca3af','#5c4ef7','#22c48c','#3b8df9','#f5a524','#e84646','#a855f7'];

// Group by day
$byDay = [];
foreach ($slots as $s) $byDay[$s['day_of_week']][] = $s;

layout_head('Timetable', 'timetable');
layout_topbar('Timetable', '<button class="btn btn-primary" onclick="openModal(\'add-modal\')"><i class="fa-solid fa-plus"></i> Add Slot</button>');

if ($msg): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check"></i><?= htmlspecialchars($msg) ?></div><?php endif;
if ($err): ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($err) ?></div><?php endif;
?>

<div style="display:grid;gap:20px">
<?php for ($d = 1; $d <= 6; $d++): // Mon–Sat
  if (empty($byDay[$d])) continue;
  $color = $dayColors[$d];
?>
  <div class="card">
    <div class="card-header">
      <span class="card-title" style="color:<?= $color ?>"><i class="fa-solid fa-calendar-day"></i> <?= $days[$d] ?></span>
      <span class="badge badge-brand"><?= count($byDay[$d]) ?> class<?= count($byDay[$d])>1?'es':'' ?></span>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Time</th><th>Course</th><th>Instructor</th><th>Venue</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($byDay[$d] as $slot): ?>
          <tr>
            <td class="mono" style="white-space:nowrap">
              <?= date('g:i A', strtotime($slot['start_time'])) ?> – <?= date('g:i A', strtotime($slot['end_time'])) ?>
            </td>
            <td><span class="badge badge-brand"><?= htmlspecialchars($slot['code']) ?></span> <?= htmlspecialchars($slot['title']) ?></td>
            <td style="color:var(--muted);font-size:12px"><?= htmlspecialchars($slot['instructor']) ?></td>
            <td><?= htmlspecialchars($slot['venue'] ?? '—') ?></td>
            <td>
              <div style="display:flex;gap:6px">
                <button class="btn btn-ghost btn-sm btn-icon"
                  onclick='editSlot(<?= htmlspecialchars(json_encode($slot)) ?>)'>
                  <i class="fa-solid fa-pencil"></i></button>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $slot['id'] ?>">
                  <button class="btn btn-danger btn-sm btn-icon"
                    onclick="return confirm('Remove this timetable slot?')">
                    <i class="fa-solid fa-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endfor; ?>
<?php if (empty($slots)): ?>
  <div class="card"><div class="empty-state"><i class="fa-solid fa-table-cells"></i><p>No timetable slots yet. Add your first class schedule.</p></div></div>
<?php endif; ?>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="add-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add Timetable Slot</span>
      <button class="modal-close" onclick="closeModal('add-modal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST">
    <div class="modal-body">
      <input type="hidden" name="action" value="add">
      <div class="form-group"><label>Course *</label>
        <select name="course_id" required>
          <option value="">— Select course —</option>
          <?php foreach ($courses as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['code']) ?> — <?= htmlspecialchars($c['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Day of Week *</label>
        <select name="day_of_week">
          <?php foreach (['Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,'Thursday'=>4,'Friday'=>5,'Saturday'=>6] as $dn=>$di): ?>
            <option value="<?= $di ?>"><?= $dn ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Start Time *</label><input type="time" name="start_time" required></div>
        <div class="form-group"><label>End Time *</label><input type="time" name="end_time" required></div>
      </div>
      <div class="form-group"><label>Venue</label><input name="venue" placeholder="LT-A, Lab-1, SM-1…"></div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" onclick="closeModal('add-modal')">Cancel</button>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Slot</button>
    </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="edit-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Slot</span>
      <button class="modal-close" onclick="closeModal('edit-modal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST">
    <div class="modal-body">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="e-id">
      <div class="form-group"><label>Course *</label>
        <select name="course_id" id="e-course" required>
          <?php foreach ($courses as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['code']) ?> — <?= htmlspecialchars($c['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Day *</label>
        <select name="day_of_week" id="e-dow">
          <?php foreach (['Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,'Thursday'=>4,'Friday'=>5,'Saturday'=>6] as $dn=>$di): ?>
            <option value="<?= $di ?>"><?= $dn ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Start</label><input type="time" name="start_time" id="e-start"></div>
        <div class="form-group"><label>End</label><input type="time" name="end_time" id="e-end"></div>
      </div>
      <div class="form-group"><label>Venue</label><input name="venue" id="e-venue"></div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" onclick="closeModal('edit-modal')">Cancel</button>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save</button>
    </div>
    </form>
  </div>
</div>

<script>
function editSlot(s){
  document.getElementById('e-id').value = s.id;
  document.getElementById('e-course').value = s.course_id;
  document.getElementById('e-dow').value = s.day_of_week;
  document.getElementById('e-start').value = s.start_time.substring(0,5);
  document.getElementById('e-end').value = s.end_time.substring(0,5);
  document.getElementById('e-venue').value = s.venue || '';
  openModal('edit-modal');
}
</script>
<?php layout_foot(); ?>
