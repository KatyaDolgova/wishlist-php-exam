<?php
session_start();
require_once __DIR__ . '/config.php';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}

// Обработка добавления/удаления из избранного
if (isset($_POST['toggle_favorite']) && isset($_POST['item_id']) && isset($_SESSION['user_id'])) {
    $itemId = (int)$_POST['item_id'];
    // Проверяем, есть ли уже в избранном
    $stmt = $pdo->prepare("SELECT id FROM favorite_items WHERE user_id = ? AND item_id = ?");
    $stmt->execute([$_SESSION['user_id'], $itemId]);
    $existing = $stmt->fetch();
    if ($existing) {
        // Удаляем из избранного
        $stmt = $pdo->prepare("DELETE FROM favorite_items WHERE user_id = ? AND item_id = ?");
        $stmt->execute([$_SESSION['user_id'], $itemId]);
    } else {
        // Добавляем в избранное
        $stmt = $pdo->prepare("INSERT INTO favorite_items (user_id, item_id) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $itemId]);
    }
    // Перенаправляем обратно
    $redirectUrl = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header("Location: $redirectUrl");
    exit();
}

// Получаем параметры фильтрации
$search = $_GET['search'] ?? '';
$holiday_filter = $_GET['holiday'] ?? '';
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';

// Формируем SQL-запрос с фильтрами
$sql = "
SELECT h.id as holiday_id, h.name as holiday_name, h.date, u.name as owner_name,
       wi.id as item_id, wi.title, wi.description, wi.url, wi.price, wi.status, wi.photo_path, wi.item_type,
       f.id as favorite_id
FROM holidays h
JOIN users u ON h.owner_user_id = u.id
LEFT JOIN wishlist_items wi ON h.id = wi.holiday_id
LEFT JOIN favorite_items f ON f.user_id = ? AND f.item_id = wi.id
WHERE 1=1
";
$currentUserId = $_SESSION['user_id'] ?? 0;
$params = [$currentUserId];
if ($search) {
    $sql .= " AND (wi.title LIKE ? OR wi.description LIKE ? OR h.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($holiday_filter) {
    $sql .= " AND h.name = ?";
    $params[] = $holiday_filter;
}
if ($status_filter) {
    $sql .= " AND wi.status = ?";
    $params[] = $status_filter;
}
if ($type_filter) {
    $sql .= " AND wi.item_type = ?";
    $params[] = $type_filter;
}
$sql .= " ORDER BY h.date DESC, h.id, wi.id";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Группируем данные по праздникам
$groupedData = [];
foreach ($allData as $row) {
    $holidayId = $row['holiday_id'];
    if (!isset($groupedData[$holidayId])) {
        $groupedData[$holidayId] = [
            'id' => $row['holiday_id'],
            'name' => $row['holiday_name'],
            'date' => $row['date'],
            'owner_name' => $row['owner_name'],
            'items' => []
        ];
    }
    if ($row['title']) {
        $groupedData[$holidayId]['items'][] = [
            'id' => $row['item_id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'url' => $row['url'],
            'price' => $row['price'],
            'status' => $row['status'],
            'photo_path' => $row['photo_path'],
            'item_type' => $row['item_type'],
            'is_favorite' => (bool)$row['favorite_id']
        ];
    }
}

// Получаем уникальные праздники для фильтра
$stmt = $pdo->query("SELECT DISTINCT name FROM holidays ORDER BY name");
$holidays = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Получаем уникальные статусы для фильтра
$stmt = $pdo->query("SELECT DISTINCT status FROM wishlist_items WHERE status IS NOT NULL ORDER BY status");
$statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Получаем уникальные типы для фильтра
$stmt = $pdo->query("SELECT DISTINCT item_type FROM wishlist_items WHERE item_type IS NOT NULL ORDER BY item_type");
$types = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Главная - Публичные списки желаний</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Верхняя панель -->
    <div class="header-actions">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <a href="pages/login.php" class="header-btn">Войти</a>
            <a href="pages/register.php" class="header-btn">Регистрация</a>
        <?php else: ?>
            <a href="pages/profile.php" class="header-btn">Профиль</a>
            <a href="pages/logout.php" class="header-btn logout">Выйти</a>
        <?php endif; ?>
    </div>

    <div class="container">
        <!-- Заголовок -->
        <h1>Идеи подарков</h1>
        <!-- Фильтры -->
        <div class="filters-container">
            <form method="GET">
                <h4>Фильтры</h4>
                <div class="filter-group">
                    <div class="filter-item">
                        <label for="search">Поиск:</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Поиск по названию, описанию...">
                    </div>
                    <div class="filter-item">
                        <label for="holiday">Праздник:</label>
                        <select id="holiday" name="holiday">
                            <option value="">Все праздники</option>
                            <?php foreach ($holidays as $holiday): ?>
                                <option value="<?php echo htmlspecialchars($holiday); ?>" <?php echo ($holiday_filter == $holiday ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($holiday); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label for="status">Статус:</label>
                        <select id="status" name="status">
                            <option value="">Все статусы</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo htmlspecialchars($status); ?>" <?php echo ($status_filter == $status ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label for="type">Тип желания:</label>
                        <select id="type" name="type">
                            <option value="">Все типы</option>
                            <option value="for_me" <?php echo ($type_filter == 'for_me' ? 'selected' : ''); ?>>Хочу себе</option>
                            <option value="for_others" <?php echo ($type_filter == 'for_others' ? 'selected' : ''); ?>>Хочу подарить</option>
                            <option value="universal" <?php echo ($type_filter == 'universal' ? 'selected' : ''); ?>>Универсальное</option>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-action">Применить</button>
                    <button type="button" onclick="location.href='index.php'" class="btn-action">Сбросить</button>
                </div>
            </form>
        </div>
        <!-- Список товаров -->
        <?php if ($groupedData): ?>
            <div class="items-grid">
                <?php foreach ($groupedData as $holiday): ?>
                    <?php foreach ($holiday['items'] as $item): ?>
                        <div class="item-card fade-in">
                            <a href="pages/item_detail.php?item_id=<?php echo $item['id']; ?>&holiday_id=<?php echo $holiday['id']; ?>">
                                <?php if ($item['photo_path']): ?>
                                    <img src="<?php echo $item['photo_path']; ?>" alt="Фото товара">
                                <?php else: ?>
                                    <div class="no-photo">📷</div>
                                <?php endif; ?>
                                <div class="item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                            </a>
                            <div class="action-icons">
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <form method="POST" style="margin: 0; padding: 0;">
                                        <input type="hidden" name="toggle_favorite" value="1">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="favorite-btn <?php echo $item['is_favorite'] ? 'favorited' : ''; ?>">
                                            <svg class="icon-heart <?php echo $item['is_favorite'] ? 'filled' : ''; ?>" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.5 11.54L12 21.35z"/></svg>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width: 80px; height: 80px; fill: var(--text-secondary); margin-bottom: 20px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                <h3>Пока нет публичных списков</h3>
                <p>Создайте свой первый список желаний и поделитесь им с друзьями!</p>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="pages/create_wishlist.php" class="btn-action" style="margin-top: 20px;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width: 18px; height: 18px; margin-right: 5px;"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                        Создать список
                    </a>
                <?php else: ?>
                    <a href="pages/login.php" class="btn-action" style="margin-top: 20px;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width: 18px; height: 18px; margin-right: 5px;"><path d="M11 7l-5 5 5 5v3H8v-2H3V8h5V6h3v1zm4 10h3v2h5v-5h-2v-3h-3v3h-2v3z"/></svg>
                        Войти, чтобы создать
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Нижняя панель навигации -->
    <div class="bottom-nav">
        <a href="index.php" class="active">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            Главная
        </a>
        <a href="pages/profile.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            Профиль
        </a>
        <a href="pages/create_wishlist.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            Создать
        </a>
    </div>
</body>
</html>