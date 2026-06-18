<?php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/layout.php';
requireLogin();

$db  = db();
$msg = '';
$err = '';

// ── Handle POST actions ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $code   = strtoupper(trim($_POST['code'] ?? ''));
        $title  = trim($_POST['title'] ?? '');
        $units  = (int)($_POST['units'] ?? 3);
        $instr  = trim($_POST['instructor'] ?? '');
        $email  = trim($_POST['instructor_email'] ?? '');
        $sem    = $_POST['semester'] ?? 'First';
        $sess   = trim($_POST['session'] ?? '2024/2025');
        if (!$code || !$title || !$instr) { $err = 'Code, title and instructor are required.'; }
        else {
            if ($action === 'add') {
                try {
                    $db->prepare('INSERT INTO courses (code,title,units,instructor,instructor_email,semester,session) VALUES (?,?,?,?,?,?,?)')
                       ->execute([$code,$title,$units,$instr,$email,$sem,$sess]);
                    $msg = "Course {$code} added.";
                } catch (PDOException $e) { $err = 'Code already exists.'; }
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $db->prepare('UPDATE courses SET code=?,title=?,units=?,instructor=?,instructor_email=?,semester=?,session=? WHERE id=?')
                   ->execute([$code,$title,$units,$instr,$email,$sem,$sess,$id]);
                $msg = "Course {$code} updated.";
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare('UPDATE courses SET is_active = 1 - is_active WHERE id=?')->execute([$id]);
        $msg = 'Course status toggled.';
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // check if used in timetable
        $used = $db->prepare('SELECT COUNT(*) FROM timetable WHERE course_id=?');
        $used->execute([$id]);
        if ($used->fetchColumn() > 0) { $err = 'Cannot delete: course has timetable slots. Remove slots first.'; }
        else { $db->prepare('DELETE FROM courses WHERE id=?')->execute([$id]); $msg = 'Course deleted.'; }
    }
}

// ── Load courses ────────────────────────────────────────────────────────────
$courses = $db->query('
    SELECT c.*, 
           (SELECT COUNT(*) FROM timetable t WHERE t.course_id=c.id) AS slot_count,
           (SELECT COUNT(*) FROM att_sessions s JOIN timetable t ON s.timetable_id=t.id WHERE t.course_id=c.id) AS session_count
    FROM courses c ORDER BY c.code
')->fetchAll();

layout_head('Courses', 'courses');
layout_topbar('Courses', '<button class="btn btn-primary" onclick="openModal(\'add-modal\')"><i class="fa-solid fa-plus"></i> Add Course</button>');

if ($msg): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check"></i><?= htmlspecialchars($msg) ?></div><?php endif;
if ($err): ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($err) ?></div><?php endif;
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fa-solid fa-book c-brand"></i> All Courses</span>
    <span class="badge badge-brand"><?= count($courses) ?> total</span>
  </div>
  <div class="table-wrap">
    <?php if (empty($courses)): ?>
      <div class="empty-state"><i class="fa-solid fa-book-open"></i><p>No courses yet. Add your first course.</p></div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Code</th><th>Title</th><th>Units</th><th>Instructor</th>
          <th>Semester</th><th>Slots</th><th>Sessions</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($courses as $c): ?>
        <tr>
          <td><span class="badge badge-brand mono"><?= htmlspecialchars($c['code']) ?></span></td>
          <td style="font-weight:600"><?= htmlspecialchars($c['title']) ?></td>
          <td class="text-center"><?= $c['units'] ?> cr</td>
          <td>
            <div style="font-size:13px"><?= htmlspecialchars($c['instructor']) ?></div>
            <?php if ($c['instructor_email']): ?><div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($c['instructor_email']) ?></div><?php endif; ?>
          </td>
          <td><?= htmlspecialchars($c['semester']) ?> · <?= htmlspecialchars($c['session']) ?></td>
          <td class="text-center"><?= $c['slot_count'] ?></td>
          <td class="text-center"><?= $c['session_count'] ?></td>
          <td>
            <?php if ($c['is_active']): ?>
              <span class="badge badge-green"><i class="fa-solid fa-circle" style="font-size:7px"></i> Active</span>
            <?php else: ?>
              <span class="badge badge-gray">Inactive</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:6px">
              <button class="btn btn-ghost btn-sm btn-icon" title="Edit"
                onclick='editCourse(<?= htmlspecialchars(json_encode($c)) ?>)'><i class="fa-solid fa-pencil"></i></button>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button class="btn btn-ghost btn-sm btn-icon" title="Toggle status"
                  onclick="return confirm('Toggle course status?')">
                  <i class="fa-solid fa-power-off"></i></button>
              </form>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button class="btn btn-danger btn-sm btn-icon" title="Delete"
                  onclick="return confirm('Delete this course? This cannot be undone.')">
                  <i class="fa-solid fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Add modal -->
<div class="modal-overlay" id="add-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add Course</span>
      <button class="modal-close" onclick="closeModal('add-modal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST">
    <div class="modal-body">
      <input type="hidden" name="action" value="add">
      <div class="form-row">
        <div class="form-group"><label>Course Code *</label><input name="code" placeholder="CSC301" required></div>
        <div class="form-group"><label>Credit Units</label>
          <select name="units"><option>0</option><option>1</option><option>2</option><option selected>3</option><option>4</option></select>
        </div>
      </div>
      <div class="form-group"><label>Course Title *</label><input name="title" placeholder="Data Structures & Algorithms" required></div>
      <div class="form-row">
        <div class="form-group"><label>Instructor *</label><input name="instructor" placeholder="Dr. Adeyemi O." required></div>
        <div class="form-group"><label>Instructor Email</label><input name="instructor_email" type="email" placeholder="adeyemi@uni.edu.ng"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Semester</label>
          <select name="semester"><option>First</option><option>Second</option></select>
        </div>
        <div class="form-group"><label>Session</label><input name="session" placeholder="2024/2025" value="2024/2025"></div>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" onclick="closeModal('add-modal')">Cancel</button>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Course</button>
    </div>
    </form>
  </div>
</div>

<!-- Edit modal -->
<div class="modal-overlay" id="edit-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Course</span>
      <button class="modal-close" onclick="closeModal('edit-modal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST">
    <div class="modal-body">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="e-id">
      <div class="form-row">
        <div class="form-group"><label>Course Code *</label><input name="code" id="e-code" required></div>
        <div class="form-group"><label>Credit Units</label>
          <select name="units" id="e-units">
            <option>0</option><option>1</option><option>2</option><option>3</option><option>4</option>
          </select>
        </div>
      </div>
      <div class="form-group"><label>Course Title *</label><input name="title" id="e-title" required></div>
      <div class="form-row">
        <div class="form-group"><label>Instructor *</label><input name="instructor" id="e-instructor" required></div>
        <div class="form-group"><label>Instructor Email</label><input name="instructor_email" id="e-iemail" type="email"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Semester</label>
          <select name="semester" id="e-semester"><option>First</option><option>Second</option></select>
        </div>
        <div class="form-group"><label>Session</label><input name="session" id="e-session"></div>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" onclick="closeModal('edit-modal')">Cancel</button>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
    </div>
    </form>
  </div>
</div>

<script>
function editCourse(c){
  document.getElementById('e-id').value = c.id;
  document.getElementById('e-code').value = c.code;
  document.getElementById('e-title').value = c.title;
  document.getElementById('e-units').value = c.units;
  document.getElementById('e-instructor').value = c.instructor;
  document.getElementById('e-iemail').value = c.instructor_email || '';
  document.getElementById('e-semester').value = c.semester;
  document.getElementById('e-session').value = c.session;
  openModal('edit-modal');
}
</script>
<?php layout_foot(); ?>
