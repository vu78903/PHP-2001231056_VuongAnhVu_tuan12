<?php
// Bật hiển thị lỗi để dễ dàng debug nếu có sự cố
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    // Sử dụng giá trị mặc định để tránh lỗi Undefined array key
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname = $_ENV['DB_NAME'] ?? 'huit_db';
    $username_db = $_ENV['DB_USER'] ?? 'root';
    $password_db = $_ENV['DB_PASS'] ?? '';

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $teacher_id = $_SESSION['user_id'];

    // Lấy lịch dạy riêng của giảng viên đang đăng nhập
    $stmt = $pdo->prepare("SELECT lich_day.*, co_so.branch_name, co_so.address 
                           FROM lich_day 
                           JOIN co_so ON lich_day.co_so_id = co_so.id 
                           WHERE lich_day.giang_vien_id = ?");
    $stmt->execute([$teacher_id]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Lỗi kết nối hoặc truy vấn CSDL: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Cổng thông tin Giảng viên - HUIT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="#">Cổng Giảng Viên: <?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></a>
            <a href="logout.php" class="btn btn-outline-light btn-sm">Đăng xuất</a>
        </div>
    </nav>

    <div class="container">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">📅 Lịch Giảng Dạy Cá Nhân Của Bạn</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark text-center">
                        <tr>
                            <th>STT</th>
                            <th>Môn Học</th>
                            <th>Phòng Học</th>
                            <th>Thứ</th>
                            <th>Ca / Tiết</th>
                            <th>Cơ Sở & Địa Chỉ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($schedules)): ?>
                            <?php $stt = 1; foreach ($schedules as $row): ?>
                                <tr>
                                    <td class="text-center"><?= $stt++ ?></td>
                                    <td><strong><?= htmlspecialchars($row['subject_name']) ?></strong></td>
                                    <td class="text-center text-danger fw-bold"><?= htmlspecialchars($row['room']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($row['day_of_week']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($row['shift_or_periods']) ?></td>
                                    <td><?= htmlspecialchars($row['branch_name']) ?> <br><small class="text-muted"><?= htmlspecialchars($row['address']) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted">Bạn chưa có lịch dạy nào được phân công.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>