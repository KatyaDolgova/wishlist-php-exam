<?php
require_once __DIR__ . '/../includes/db.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    die("Не указан ID списка.");
}

$stmt = $pdo->prepare("
    SELECT h.name as holiday_name, h.date, u.name as owner_name
    FROM holidays h
    JOIN users u ON h.owner_user_id = u.id
    WHERE h.id = ?
");
$stmt->execute([$id]);
$holiday = $stmt->fetch();

if (!$holiday) {
    die("Список не найден.");
}

$stmt = $pdo->prepare("
    SELECT title, description, url, price, status, photo_path
    FROM wishlist_items
    WHERE holiday_id = ?
");
$stmt->execute([$id]);
$items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($holiday['holiday_name']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <button class="btn-back" onclick="location.href='profile.php'">← Назад</button>
        <h2><?php echo htmlspecialchars($holiday['holiday_name']); ?> (<?php echo $holiday['date'] ?? 'без даты'; ?>)</h2>
        <p>Владелец: <?php echo htmlspecialchars($holiday['owner_name']); ?></p>

        <h3>Желания:</h3>
        <?php if ($items): ?>
            <ul>
                <?php foreach ($items as $item): ?>
                    <li style="margin-bottom: 20px; padding: 15px; border: 1px solid #eee; border-radius: 8px; background-color: #fafafa;">
                        <strong><?php echo htmlspecialchars($item['title']); ?></strong><br>
                        <?php if ($item['description']): ?>
                            <?php echo htmlspecialchars($item['description']); ?><br>
                        <?php endif; ?>
                        <?php if ($item['photo_path']): ?>
                            <img src="../<?php echo $item['photo_path']; ?>" alt="Фото товара" style="max-width: 150px; max-height: 150px; margin-top: 10px; border-radius: 5px;"><br>
                        <?php endif; ?>
                        <?php if ($item['url']): ?>
                            <a href="<?php echo htmlspecialchars($item['url']); ?>" target="_blank" style="color: #2196F3;">Ссылка</a><br>
                        <?php endif; ?>
                        <?php if ($item['price']): ?>
                            Цена: <?php echo number_format($item['price'], 2); ?> руб.<br>
                        <?php endif; ?>
                        Статус: <?php echo $item['status']; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Пустой список.</p>
        <?php endif; ?>
    </div>
</body>
</html>