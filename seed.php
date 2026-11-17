<?php
// Bật hiển thị lỗi để dễ dàng debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Nạp autoload của Composer
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Nạp các biến môi trường từ file .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? '3306';
$dbname = $_ENV['DB_NAME'] ?? 'huit_db';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? '';

try {
    // Khởi tạo kết nối PDO trực tiếp
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Lỗi kết nối cơ sở dữ liệu MySQL: " . $e->getMessage());
}

// Khởi tạo Faker tiếng Việt
$faker = Faker\Factory::create('vi_VN');

try {
    // Bắt đầu Transaction để đảm bảo tính toàn vẹn dữ liệu
    $pdo->beginTransaction();

    // 1. Sinh dữ liệu mẫu cho bảng co_so (Cơ sở)
    echo "Đang thêm dữ liệu mẫu cho bảng co_so...\n";
    $coSoIds = [];
    $danhSachCoSo = [
        ['ten_co_so' => 'Cơ sở chính - Quận Tân Phú', 'dia_chi' => '140 Lê Trọng Tấn, P. Tây Thạnh, Q. Tân Phú, TP.HCM'],
        ['ten_co_so' => 'Cơ sở 2 - Quận 4', 'dia_chi' => '25 Tôn Thất Thuyết, P. 15, Q. 4, TP.HCM'],
    ];

    foreach ($danhSachCoSo as $cs) {
        $stmt = $pdo->prepare("INSERT INTO co_so (branch_name, address) VALUES (?, ?)");
        $stmt->execute([$cs['ten_co_so'], $cs['dia_chi']]);
        $coSoIds[] = $pdo->lastInsertId();
    }

    // 2. Sinh dữ liệu mẫu cho bảng giang_vien (Giảng viên) - Tạo 5 giảng viên
    echo "Đang thêm dữ liệu mẫu cho bảng giang_vien...\n";
    $giangVienIds = [];
    $chuyenMons = ['Công nghệ Web', 'Cơ sở dữ liệu nâng cao', 'Trí tuệ nhân tạo', 'Lập trình hướng đối tượng', 'Mạng máy tính'];

    for ($i = 0; $i < 5; $i++) {
        $fullName = $faker->name;
        $email = $faker->unique()->safeEmail;
        $phone = $faker->phoneNumber;
        $expertise = $chuyenMons[array_rand($chuyenMons)];

        $stmt = $pdo->prepare("INSERT INTO giang_vien (full_name, email, phone, expertise) VALUES (?, ?, ?, ?)");
        $stmt->execute([$fullName, $email, $phone, $expertise]);
        $giangVienIds[] = $pdo->lastInsertId();
    }

    // 3. Sinh dữ liệu mẫu cho bảng lich_day (Lịch dạy) - Tạo 10 lịch dạy
    echo "Đang thêm dữ liệu mẫu cho bảng lich_day...\n";
    $monHocs = ['Lập trình PHP', 'Hệ quản trị CSDL MySQL', 'Nhập môn Trí tuệ nhân tạo', 'Phân tích thiết kế hệ thống', 'Lập trình .NET'];
    $thuTrongTuan = ['Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
    $caHocs = ['Ca sáng (Tiết 1-3)', 'Ca sáng (Tiết 4-6)', 'Ca chiều (Tiết 7-9)', 'Ca chiều (Tiết 10-12)'];

    for ($i = 0; $i < 10; $i++) {
        $giangVienId = $giangVienIds[array_rand($giangVienIds)];
        $coSoId = $coSoIds[array_rand($coSoIds)];
        $subjectName = $monHocs[array_rand($monHocs)];
        $room = 'Phòng ' . $faker->randomElement(['A', 'B', 'C']) . '.' . $faker->numberBetween(101, 405);
        $dayOfWeek = $thuTrongTuan[array_rand($thuTrongTuan)];
        $shiftOrPeriods = $caHocs[array_rand($caHocs)];

        $stmt = $pdo->prepare("INSERT INTO lich_day (giang_vien_id, co_so_id, subject_name, room, day_of_week, shift_or_periods) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$giangVienId, $coSoId, $subjectName, $room, $dayOfWeek, $shiftOrPeriods]);
    }

    // Hoàn tất Transaction
    $pdo->commit();
    echo "Đã sinh dữ liệu mẫu thành công vào các bảng tiếng Việt!\n";

} catch (Exception $e) {
    // Rollback nếu có lỗi xảy ra
    $pdo->rollBack();
    echo "Lỗi khi chạy Seeder: " . $e->getMessage() . "\n";
}