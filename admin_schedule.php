<?php
// Bật hiển thị lỗi để debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname = $_ENV['DB_NAME'] ?? 'huit_db';
    $username_db = $_ENV['DB_USER'] ?? 'root';
    $password_db = $_ENV['DB_PASS'] ?? '';

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $success_msg = '';
    $error_msg = '';

    // Xử lý thêm mới lịch dạy (Chống trùng lịch)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
        $giang_vien_id = $_POST['giang_vien_id'];
        $co_so_id = $_POST['co_so_id'];
        $subject_name = trim($_POST['subject_name']);
        $room = trim($_POST['room']);
        $day_of_week = $_POST['day_of_week'];
        $shift_or_periods = $_POST['shift_or_periods'];

        // Kiểm tra trùng lịch giảng viên
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM lich_day WHERE giang_vien_id = ? AND day_of_week = ? AND shift_or_periods = ?");
        $checkStmt->execute([$giang_vien_id, $day_of_week, $shift_or_periods]);
        $isConflict = $checkStmt->fetchColumn();

        if ($isConflict > 0) {
            $error_msg = 'Lỗi: Giảng viên này đã có lịch dạy vào ' . $day_of_week . ' (' . $shift_or_periods . ') rồi, bị trùng lịch!';
        } else {
            $insertStmt = $pdo->prepare("INSERT INTO lich_day (giang_vien_id, co_so_id, subject_name, room, day_of_week, shift_or_periods) VALUES (?, ?, ?, ?, ?, ?)");
            $insertStmt->execute([$giang_vien_id, $co_so_id, $subject_name, $room, $day_of_week, $shift_or_periods]);
            $success_msg = 'Thêm phân lịch giảng dạy thành công!';
        }
    }

    // Xử lý lọc dữ liệu chuẩn xác
    $filter_coso = !empty($_GET['co_so_id']) ? $_GET['co_so_id'] : '';
    $filter_gv = !empty($_GET['giang_vien_id']) ? $_GET['giang_vien_id'] : '';

    $sql = "SELECT lich_day.*, giang_vien.full_name, giang_vien.expertise, co_so.branch_name 
            FROM lich_day 
            JOIN giang_vien ON lich_day.giang_vien_id = giang_vien.id 
            JOIN co_so ON lich_day.co_so_id = co_so.id WHERE 1=1";
    $params = [];

    if ($filter_coso !== '') {
        $sql .= " AND lich_day.co_so_id = ?";
        $params[] = $filter_coso;
    }
    if ($filter_gv !== '') {
        $sql .= " AND lich_day.giang_vien_id = ?";
        $params[] = $filter_gv;
    }

    $sql .= " ORDER BY lich_day.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lấy danh sách Cơ sở và Giảng viên
    $listCoSo = $pdo->query("SELECT * FROM co_so")->fetchAll(PDO::FETCH_ASSOC);
    $listGiangVien = $pdo->query("SELECT * FROM giang_vien WHERE role='teacher'")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Lỗi CSDL: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Phân Lịch - Admin</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Select2 CSS (Chỉ dùng cho chọn cơ sở & giảng viên có hỗ trợ tìm kiếm) -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-primary mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1">⚙️ Trang Quản Trị Người Xếp Lịch (Admin)</span>
            <a href="logout.php" class="btn btn-outline-light btn-sm">Đăng xuất</a>
        </div>
    </nav>

    <div class="container">
        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?= $success_msg ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-danger"><?= $error_msg ?></div>
        <?php endif; ?>

        <!-- Form Thêm Lịch Dạy Mới -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white">➕ Thêm Phân Lịch Giảng Dạy Mới</div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="add_schedule" value="1">
                    
                    <div class="col-md-3">
                        <label class="form-label">Chọn Giảng Viên</label>
                        <select name="giang_vien_id" class="form-select select2" required>
                            <option value="">-- Chọn giảng viên --</option>
                            <?php foreach ($listGiangVien as $gv): ?>
                                <option value="<?= $gv['id'] ?>"><?= htmlspecialchars($gv['full_name']) ?> (<?= $gv['expertise'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Chọn Cơ Sở</label>
                        <select name="co_so_id" class="form-select select2" required>
                            <option value="">-- Chọn cơ sở --</option>
                            <?php foreach ($listCoSo as $cs): ?>
                                <option value="<?= $cs['id'] ?>"><?= htmlspecialchars($cs['branch_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Đã đổi thành Dropbox (chọn sẵn, không cho gõ) -->
                    <div class="col-md-3">
                        <label class="form-label">Tên Học Phần / Môn Học</label>
                        <select name="subject_name" class="form-select" required>
                            <option value="">-- Chọn môn học --</option>
                            <option value="Lập trình PHP">Lập trình PHP</option>
                            <option value="Hệ quản trị CSDL MySQL">Hệ quản trị CSDL MySQL</option>
                            <option value="Nhập môn Trí tuệ nhân tạo">Nhập môn Trí tuệ nhân tạo</option>
                            <option value="Phân tích thiết kế hệ thống">Phân tích thiết kế hệ thống</option>
                            <option value="Lập trình .NET">Lập trình .NET</option>
                        </select>
                    </div>

                    <!-- Đã đổi thành Dropbox (chọn sẵn, không cho gõ) -->
                    <div class="col-md-3">
                        <label class="form-label">Phòng Học</label>
                        <select name="room" class="form-select" required>
                            <option value="">-- Chọn phòng học --</option>
                            <option value="Phòng A.201">Phòng A.201</option>
                            <option value="Phòng A.202">Phòng A.202</option>
                            <option value="Phòng B.101">Phòng B.101</option>
                            <option value="Phòng B.102">Phòng B.102</option>
                            <option value="Phòng C.301">Phòng C.301</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Thứ trong tuần</label>
                        <select name="day_of_week" class="form-select" required>
                            <option value="Thứ Hai">Thứ Hai</option>
                            <option value="Thứ Ba">Thứ Ba</option>
                            <option value="Thứ Tư">Thứ Tư</option>
                            <option value="Thứ Năm">Thứ Năm</option>
                            <option value="Thứ Sáu">Thứ Sáu</option>
                            <option value="Thứ Bảy">Thứ Bảy</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Ca hoặc Tiết học</label>
                        <select name="shift_or_periods" class="form-select" required>
                            <option value="Ca sáng (Tiết 1-3)">Ca sáng (Tiết 1-3)</option>
                            <option value="Ca sáng (Tiết 4-6)">Ca sáng (Tiết 4-6)</option>
                            <option value="Ca chiều (Tiết 7-9)">Ca chiều (Tiết 7-9)</option>
                            <option value="Ca chiều (Tiết 10-12)">Ca chiều (Tiết 10-12)</option>
                        </select>
                    </div>

                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100">Xếp lịch & Kiểm tra trùng</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bộ lọc quản lý chung -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-secondary text-white">🔍 Lọc Nhanh Theo Cơ Sở & Giảng Viên</div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <select name="co_so_id" class="form-select select2">
                            <option value="">-- Tất cả cơ sở --</option>
                            <?php foreach ($listCoSo as $cs): ?>
                                <option value="<?= $cs['id'] ?>" <?= $filter_coso == $cs['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cs['branch_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <select name="giang_vien_id" class="form-select select2">
                            <option value="">-- Tất cả giảng viên --</option>
                            <?php foreach ($listGiangVien as $gv): ?>
                                <option value="<?= $gv['id'] ?>" <?= $filter_gv == $gv['id'] ? 'selected' : '' ?>><?= htmlspecialchars($gv['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">Lọc lịch</button>
                        <a href="admin_schedule.php" class="btn btn-outline-secondary">Xóa lọc</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bảng hiển thị danh sách lịch dạy -->
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">📋 Danh Sách Phân Lịch Giảng Dạy</h5>
                <span class="badge bg-light text-dark">Tổng số: <?= count($schedules) ?></span>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-dark text-center">
                        <tr>
                            <th>STT</th>
                            <th>Giảng Viên</th>
                            <th>Môn Học</th>
                            <th>Phòng</th>
                            <th>Thứ & Ca Học</th>
                            <th>Cơ Sở</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($schedules)): ?>
                            <?php $stt = 1; foreach ($schedules as $row): ?>
                                <tr>
                                    <td class="text-center"><?= $stt++ ?></td>
                                    <td><strong><?= htmlspecialchars($row['full_name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($row['expertise']) ?></small></td>
                                    <td><?= htmlspecialchars($row['subject_name']) ?></td>
                                    <td class="text-center text-danger fw-bold"><?= htmlspecialchars($row['room']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($row['day_of_week']) ?><br><span class="badge bg-info text-dark"><?= htmlspecialchars($row['shift_or_periods']) ?></span></td>
                                    <td><?= htmlspecialchars($row['branch_name']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted">Chưa có lịch dạy nào phù hợp với điều kiện lọc.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- jQuery & Select2 JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Chỉ áp dụng select2 cho các thẻ chọn cơ sở và giảng viên
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
        });
    </script>
</body>
</html>