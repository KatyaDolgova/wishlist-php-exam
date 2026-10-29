<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit();
}

require_once __DIR__ . '/../includes/db.php';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $holidayName = trim($_POST['holiday_name']);
    $date = $_POST['date'];
    $itemTitle = trim($_POST['item_title']);
    $description = trim($_POST['description']);
    $url = trim($_POST['url']);
    $price = ($_POST['price'] ?? '') !== '' ? (float)$_POST['price'] : null;
    $status = $_POST['status'] ?? 'expected';
    $itemType = $_POST['item_type'] ?? 'universal';

    // Загрузка фото
    $photoPath = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $allowedImageTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedType = finfo_file($finfo, $_FILES['photo']['tmp_name']);
        finfo_close($finfo);

        if (isset($allowedImageTypes[$detectedType]) && $_FILES['photo']['size'] <= 5 * 1024 * 1024) {
            $uploadDir = '../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileName = uniqid() . '.' . $allowedImageTypes[$detectedType];
            $targetFile = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetFile)) {
                $photoPath = 'uploads/' . $fileName;
            }
        }
    }

    // Создаем праздник
    $stmt = $pdo->prepare("INSERT INTO holidays (name, date, owner_user_id) VALUES (?, ?, ?)");
    $stmt->execute([$holidayName, $date ?: null, $_SESSION['user_id']]);
    $holidayId = $pdo->lastInsertId();

    // Создаем желание
    $stmt = $pdo->prepare("
        INSERT INTO wishlist_items (title, description, url, price, photo_path, status, item_type, holiday_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$itemTitle, $description, $url, $price, $photoPath, $status, $itemType, $holidayId]);

    $message = 'Список создан!';
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создать список желаний</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Верхняя панель -->
    <div class="header-actions">
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="../pages/profile.php" class="header-btn">Профиль</a>
            <a href="../pages/logout.php" class="header-btn logout">Выйти</a>
        <?php endif; ?>
    </div>

    <div class="container">
        <button class="btn-back" onclick="location.href='profile.php'">← Назад</button>
        <h2>Добавить желание</h2>
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- Форма -->
        <form method="POST" enctype="multipart/form-data" class="fade-in">
            <div style="margin-bottom: 15px;">
                <label for="holiday_name">Праздник:</label>
                <select name="holiday_name" required>
                    <option value="">Выберите праздник</option>
                    <option value="Новый год">Новый год</option>
                    <option value="День рождения">День рождения</option>
                    <option value="8 Марта">8 Марта</option>
                    <option value="23 Февраля">23 Февраля</option>
                    <option value="День Святого Валентина">День Святого Валентина</option>
                    <option value="Другое">Другое</option>
                </select>
            </div>
            <div style="margin-bottom: 15px;">
                <label for="date">Дата (необязательно):</label>
                <input type="date" name="date">
            </div>
            <div style="margin-bottom: 15px;">
                <label for="item_title">Название подарка:</label>
                <input type="text" name="item_title" placeholder="Например: Ноутбук" required>
            </div>
            <div style="margin-bottom: 15px;">
                <label for="description">Описание (необязательно):</label>
                <textarea name="description" placeholder="Расскажите подробнее о том, что вы хотите"></textarea>
            </div>
            <div style="margin-bottom: 15px;">
                <label for="url">Ссылка на товар (необязательно):</label>
                <input type="url" name="url" placeholder="https://example.com/product">
            </div>
            <div style="margin-bottom: 15px;">
                <label for="price">Цена, руб. (необязательно):</label>
                <input type="number" name="price" min="0" step="0.01" placeholder="Например: 2500">
            </div>
            <div style="margin-bottom: 15px;">
                <label for="status">Статус:</label>
                <select name="status" required>
                    <option value="expected">Ожидается</option>
                    <option value="purchased">Куплено</option>
                    <option value="presented">Подарено</option>
                </select>
            </div>
            <div style="margin-bottom: 15px;">
                <label for="item_type">Тип желания:</label>
                <select name="item_type" required>
                    <option value="for_me">Хочу себе</option>
                    <option value="for_others">Хочу подарить</option>
                    <option value="universal">Универсальное</option>
                </select>
            </div>
            <div style="margin-bottom: 15px;">
                <label for="photo">Фото товара (необязательно):</label>
                <input type="file" name="photo" accept="image/*">
            </div>
            <button type="submit" class="btn-action">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width: 18px; height: 18px; margin-right: 5px;"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Создать список
            </button>
        </form>
    </div>

    <!-- Нижняя панель навигации -->
    <div class="bottom-nav">
        <a href="../index.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            Главная
        </a>
        <a href="profile.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            Профиль
        </a>
        <a href="create_wishlist.php" class="active">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            Создать
        </a>
        <a href="logout.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L13 5.83V11h-2V5.83L8.41 8.41 7 7l5-5 5 5zM17 17v-2h-2v-6h-2v6H8v2h8z"/></svg>
            Выйти
        </a>
    </div>
</body>
</html>