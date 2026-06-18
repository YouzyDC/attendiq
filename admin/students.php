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
        $matric = strtoupper(trim($_POST['matric_no'] ?? ''));
        $name   = trim($_POST['full_name'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $phone  = trim($_POST['phone'] ?? '');
        $gender = $_POST['gender'] ?? 'Male';
        if (!$matric || !$name) { $err = 'Matric number and full name are required.'; }
        else {
            try {
                if ($action === 'add') {
                    $db->prepare('INSERT INTO students (matric_no,full_name,email,phone,gender) VALUES (?,?,?,?,?)')
                       ->execute([$matric,$name,$email,$phone,$gender]);
                    $msg = "{$name} added.";
                } else {
                    $id = (int)($_POST['id'] ?? 0);
                    $db->prepare('UPDATE students SET matric_no=?,full_name=?,email=?,phone=?,gender=? WHERE id=?')
                       ->execute([$matric,$name,$email,$phone,$gender,$id]);
                    $msg = 'Student updated.';
                }
            } catch (PDOException $e) { $err = 'Matric number already exists.'; }
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare('UPDATE students SET is_active=NOT is_active WHERE id=?')->execute([$id]);
        $msg = 'Status updated.';
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $used = $db->prepare('SELECT COUNT(*) FROM attendance WHERE student_id=?');
        $used->execute([$id]);
        if ($used->fetchColumn() > 0) { $err = 'Cannot delete: student has attendance records. Deactivate instead.'; }
        else {
            $db->prepare('DELETE FROM webauthn_credentials WHERE student_id=?')->execute([$id]);
            $db->prepare('DELETE FROM students WHERE id=?')->execute([$id]);
            $msg = 'Student removed.';
        }
    } elseif ($action === 'reset_fp') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare('DELETE FROM webauthn_credentials WHERE student_id=?')->execute([$id]);
        $msg = 'Fingerprint reset. Student must re-register.';
    }
}

$search = trim($_GET['q'] ?? '');
$params = [];
$where  = 'WHERE 1';
if ($search) {
    $where .= ' AND (s.full_name LIKE ? OR s.matric_no LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$stmt = $db->prepare("
    SELECT s.*,
           (SELECT COUNT(*) FROM webauthn_credentials w WHERE w.student_id=s.id) AS has_fp,
           (SELECT COUNT(*) FROM attendance a WHERE a.student_id=s.id) AS att_count
    FROM students s {$where} ORDER BY s.full_name
");
$stmt->execute($params);
$students = $stmt->fetchAll();

$colors = ['#5c4ef7','#22c48c','#3b8df9','#f5a524','#e84646','#a855f7','#e05c7a','#f97316','#14b8a6','#ec4899'];
function stuColor(int $id, array $cols): string { return $cols[$id % count($cols)]; }

layout_head('Students', 'students');
layout_topbar('Students',
  '<button class="btn btn-primary" onclick="openModal(\'add-modal\')"><i class="fa-solid fa-user-plus"></i> Add Student</button>'
);

if ($msg): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check"></i><?= htmlspecialchars($msg) ?></div><?php endif;
if ($err): ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($err) ?></div><?php endif;
?>

<!-- Search bar -->
<form method="GET" style="margin-bottom:16px;display:flex;gap:10px">
  <input name="q" placeholder="Search by name or matric…" value="<?= htmlspecialchars($search) ?>" style="max-width:320px">
  <button type="submit" class="btn btn-ghost"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
  <?php if ($search): ?><a href="?" class="btn btn-ghost">Clear</a><?php endif; ?>
</form>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fa-solid fa-users c-brand"></i> Student Register</span>
    <div style="display:flex;gap:8px;align-items:center">
      <span class="badge badge-brand"><?= count($students) ?> student<?= count($students)!==1?'s':'' ?></span>
      <?php
        $fpReg = array_sum(array_column($students,'has_fp'));
        $pct = count($students) > 0 ? round($fpReg/count($students)*100) : 0;
      ?>
      <span class="badge badge-green"><i class="fa-solid fa-fingerprint"></i> <?= $fpReg ?>/<?= count($students) ?> registered</span>
    </div>
  </div>
  <div class="table-wrap">
    <?php if (empty($students)): ?>
      <div class="empty-state"><i class="fa-solid fa-user-slash"></i><p>No students found.</p></div>
    <?php else: ?>
    <table>
      <thead>
        <tr><th></th><th>Name</th><th>Matric No.</th><th>Gender</th><th>Contact</th>
            <th>Fingerprint</th><th>Attendance</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($students as $s):
          $col = stuColor((int)$s['id'], $colors);
          $ini = initials($s['full_name']);
        ?>
        <tr>
          <td>
            <div class="av av-sm" style="background:<?= $col ?>"><?= $ini ?></div>
          </td>
          <td style="font-weight:600"><?= htmlspecialchars($s['full_name']) ?></td>
          <td class="mono"><?= htmlspecialchars($s['matric_no']) ?></td>
          <td><?= htmlspecialchars($s['gender']) ?></td>
          <td style="font-size:12px;color:var(--muted)">
            <?= htmlspecialchars($s['email'] ?? '—') ?><br>
            <?= htmlspecialchars($s['phone'] ?? '') ?>
          </td>
          <td>
            <?php if ($s['has_fp']): ?>
              <span class="badge badge-green"><i class="fa-solid fa-fingerprint"></i> Registered</span>
            <?php else: ?>
              <span class="badge badge-amber"><i class="fa-solid fa-triangle-exclamation"></i> Not yet</span>
            <?php endif; ?>
          </td>
          <td class="text-center" style="font-weight:700;color:var(--brand)"><?= $s['att_count'] ?></td>
          <td><?= $s['is_active'] ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-gray">Inactive</span>' ?></td>
          <td>
            <div style="display:flex;gap:5px;flex-wrap:wrap">
              <button class="btn btn-ghost btn-sm btn-icon" title="Edit"
                onclick='editStudent(<?= htmlspecialchars(json_encode($s)) ?>)'>
                <i class="fa-solid fa-pencil"></i></button>
              <?php if ($s['has_fp']): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="reset_fp">
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                <button class="btn btn-ghost btn-sm btn-icon" title="Reset fingerprint"
                  onclick="return confirm('Reset fingerprint for <?= htmlspecialchars(addslashes($s['full_name'])) ?>?')">
                  <i class="fa-solid fa-fingerprint" style="color:var(--amber)"></i></button>
              </form>
              <?php endif; ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                <button class="btn btn-ghost btn-sm btn-icon" title="Toggle active">
                  <i class="fa-solid fa-power-off"></i></button>
              </form>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                <button class="btn btn-danger btn-sm btn-icon" title="Delete"
                  onclick="return confirm('Delete student permanently?')">
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

<!-- Add Modal -->
<div class="modal-overlay" id="add-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add Student</span>
      <button class="modal-close" onclick="closeModal('add-modal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST">
    <div class="modal-body">
      <input type="hidden" name="action" value="add">
      <div class="form-row">
        <div class="form-group"><label>Matric Number *</label><input name="matric_no" placeholder="CSC/2021/001" required></div>
        <div class="form-group"><label>Gender</label>
          <select name="gender"><option>Male</option><option>Female</option><option>Other</option></select>
        </div>
      </div>
      <div class="form-group"><label>Full Name *</label><input name="full_name" placeholder="Adebayo Olamide" required></div>
      <div class="form-row">
        <div class="form-group"><label>Email</label><input type="email" name="email" placeholder="student@uni.edu.ng"></div>
        <div class="form-group"><label>Phone</label><input name="phone" placeholder="0812 345 6789"></div>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" onclick="closeModal('add-modal')">Cancel</button>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Add Student</button>
    </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="edit-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Student</span>
      <button class="modal-close" onclick="closeModal('edit-modal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST">
    <div class="modal-body">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="e-id">
      <div class="form-row">
        <div class="form-group"><label>Matric Number *</label><input name="matric_no" id="e-matric" required></div>
        <div class="form-group"><label>Gender</label>
          <select name="gender" id="e-gender"><option>Male</option><option>Female</option><option>Other</option></select>
        </div>
      </div>
      <div class="form-group"><label>Full Name *</label><input name="full_name" id="e-name" required></div>
      <div class="form-row">
        <div class="form-group"><label>Email</label><input type="email" name="email" id="e-email"></div>
        <div class="form-group"><label>Phone</label><input name="phone" id="e-phone"></div>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" onclick="closeModal('edit-modal')">Cancel</button>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save</button>
    </div>
    </form>
  </div>
</div>

<script>
function editStudent(s){
  document.getElementById('e-id').value=s.id;
  document.getElementById('e-matric').value=s.matric_no;
  document.getElementById('e-name').value=s.full_name;
  document.getElementById('e-gender').value=s.gender;
  document.getElementById('e-email').value=s.email||'';
  document.getElementById('e-phone').value=s.phone||'';
  openModal('edit-modal');
}
</script>
<?php layout_foot(); ?>
