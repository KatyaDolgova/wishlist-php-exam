<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Обработка добавления чужого желания в свои
if (isset($_POST['add_shared_item']) && isset($_POST['original_item_id']) && isset($_POST['new_holiday_id'])) {
    $originalItemId = (int)$_POST['original_item_id'];
    $newHolidayId = (int)$_POST['new_holiday_id'];
    // Получаем оригинальное желание
    $stmt = $pdo->prepare("
        SELECT title, description, url, price, photo_path, status, item_type
        FROM wishlist_items
        WHERE id = ?
    ");
    $stmt->execute([$originalItemId]);
    $originalItem = $stmt->fetch();
    if ($originalItem) {
        // Создаем копию желания в своих списках
        $stmt = $pdo->prepare("
            INSERT INTO wishlist_items (title, description, url, price, photo_path, status, item_type, holiday_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $originalItem['title'],
            $originalItem['description'],
            $originalItem['url'],
            $originalItem['price'],
            $originalItem['photo_path'],
            $originalItem['status'],
            $originalItem['item_type'],
            $newHolidayId
        ]);
    }
    // Перенаправляем обратно
    $redirectUrl = $_SERVER['HTTP_REFERER'] ?? 'profile.php';
    header("Location: $redirectUrl");
    exit();
}

// Обработка добавления/удаления из избранного
if (isset($_POST['toggle_favorite']) && isset($_POST['item_id'])) {
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
    $redirectUrl = $_SERVER['HTTP_REFERER'] ?? 'profile.php';
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
SELECT h.id as holiday_id, h.name as holiday_name, h.date,
       wi.id as item_id, wi.title, wi.description, wi.url, wi.price, wi.status, wi.photo_path, wi.item_type,
       f.id as favorite_id
FROM holidays h
LEFT JOIN wishlist_items wi ON h.id = wi.holiday_id
LEFT JOIN favorite_items f ON f.user_id = ? AND f.item_id = wi.id
WHERE h.owner_user_id = ?
";
$params = [$_SESSION['user_id'], $_SESSION['user_id']];
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

// Группируем данные по типам
$groupedData = [
    'for_me' => [],
    'for_others' => [],
    'universal' => []
];
foreach ($allData as $row) {
    $holidayId = $row['holiday_id'];
    $itemType = $row['item_type'] ?? 'universal';
    if (!isset($groupedData[$itemType][$holidayId])) {
        $groupedData[$itemType][$holidayId] = [
            'id' => $row['holiday_id'],
            'name' => $row['holiday_name'],
            'date' => $row['date'],
            'items' => []
        ];
    }
    if ($row['title']) {
        $groupedData[$itemType][$holidayId]['items'][] = [
            'id' => $row['item_id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'url' => $row['url'],
            'price' => $row['price'],
            'status' => $row['status'],
            'photo_path' => $row['photo_path'],
            'is_favorite' => (bool)$row['favorite_id']
        ];
    }
}

// Получаем избранные желания
$favSql = "
SELECT h.name as holiday_name, h.date,
       wi.id as item_id, wi.title, wi.description, wi.url, wi.price, wi.status, wi.photo_path, wi.item_type
FROM favorite_items f
JOIN wishlist_items wi ON f.item_id = wi.id
JOIN holidays h ON wi.holiday_id = h.id
WHERE f.user_id = ?
ORDER BY f.created_at DESC
";
$favStmt = $pdo->prepare($favSql);
$favStmt->execute([$_SESSION['user_id']]);
$favoriteItems = $favStmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем уникальные праздники для фильтра
$stmt = $pdo->prepare("SELECT DISTINCT name FROM holidays WHERE owner_user_id = ? ORDER BY name");
$stmt->execute([$_SESSION['user_id']]);
$holidays = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Получаем уникальные статусы для фильтра
$stmt = $pdo->prepare("SELECT DISTINCT status FROM wishlist_items wi JOIN holidays h ON wi.holiday_id = h.id WHERE h.owner_user_id = ? AND status IS NOT NULL ORDER BY status");
$stmt->execute([$_SESSION['user_id']]);
$statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль</title>
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
        <div class="profile-header">
            <div class="profile-avatar">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </div>
            <h1 class="profile-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h1>
            <p>Добро пожаловать в ваш личный кабинет!</p>
        </div>
        <!-- Кнопки действий -->
        <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
            <a href="create_wishlist.php" class="btn-action">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width: 18px; height: 18px; margin-right: 5px;"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Добавить желание
            </a>
            <a href="logout.php" class="btn-action logout">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width: 18px; height: 18px; margin-right: 5px;"><path d="M17 7l-1.41 1.41L13 5.83V11h-2V5.83L8.41 8.41 7 7l5-5 5 5zM17 17v-2h-2v-6h-2v6H8v2h8z"/></svg>
                Выйти
            </a>
        </div>
        <!-- Избранные желания -->
        <?php if ($favoriteItems): ?>
            <div class="favorite-section">
                <h3><span>❤️</span> Избранное</h3>
                <div class="items-grid">
                    <?php foreach ($favoriteItems as $item): ?>
                        <div class="item-card fade-in">
                            <a href="item_detail.php?item_id=<?php echo $item['item_id']; ?>&holiday_id=<?php echo $item['holiday_id']; ?>">
                                <?php if ($item['photo_path']): ?>
                                    <img src="../<?php echo $item['photo_path']; ?>" alt="Фото товара">
                                <?php else: ?>
                                    <div class="no-photo">📷</div>
                                <?php endif; ?>
                                <div class="item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                            </a>
                            <div class="action-icons">
                                <form method="POST" style="margin: 0; padding: 0;">
                                    <input type="hidden" name="toggle_favorite" value="1">
                                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                    <button type="submit" class="favorite-btn favorited">
                                        <svg class="icon-heart filled" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.5 11.54L12 21.35z"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

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
                    <button type="button" onclick="location.href='profile.php'" class="btn-action">Сбросить</button>
                </div>
            </form>
        </div>

        <!-- Раздел "Хочу себе" -->
        <?php if (!empty($groupedData['for_me'])): ?>
            <h3>🎁 Хочу себе</h3>
            <?php foreach ($groupedData['for_me'] as $holiday): ?>
                <div class="holiday-card fade-in">
                    <h4><?php echo htmlspecialchars($holiday['name']); ?> (<?php echo $holiday['date'] ?? 'без даты'; ?>)</h4>
                    <?php if ($holiday['items']): ?>
                        <div class="items-grid">
                            <?php foreach ($holiday['items'] as $item): ?>
                                <div class="item-card">
                                    <a href="item_detail.php?item_id=<?php echo $item['id']; ?>&holiday_id=<?php echo $holiday['id']; ?>">
                                        <?php if ($item['photo_path']): ?>
                                            <img src="../<?php echo $item['photo_path']; ?>" alt="Фото товара">
                                        <?php else: ?>
                                            <div class="no-photo">📷</div>
                                        <?php endif; ?>
                                        <div class="item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                    </a>
                                    <div class="action-icons">
                                        <form method="POST" style="margin: 0; padding: 0;">
                                            <input type="hidden" name="toggle_favorite" value="1">
                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="favorite-btn <?php echo $item['is_favorite'] ? 'favorited' : ''; ?>">
                                                <svg class="icon-heart <?php echo $item['is_favorite'] ? 'filled' : ''; ?>" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.5 11.54L12 21.35z"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>Пустой список.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Раздел "Хочу подарить" -->
        <?php if (!empty($groupedData['for_others'])): ?>
            <h3>🎄 Хочу подарить</h3>
            <?php foreach ($groupedData['for_others'] as $holiday): ?>
                <div class="holiday-card fade-in">
                    <h4><?php echo htmlspecialchars($holiday['name']); ?> (<?php echo $holiday['date'] ?? 'без даты'; ?>)</h4>
                    <?php if ($holiday['items']): ?>
                        <div class="items-grid">
                            <?php foreach ($holiday['items'] as $item): ?>
                                <div class="item-card">
                                    <a href="item_detail.php?item_id=<?php echo $item['id']; ?>&holiday_id=<?php echo $holiday['id']; ?>">
                                        <?php if ($item['photo_path']): ?>
                                            <img src="../<?php echo $item['photo_path']; ?>" alt="Фото товара">
                                        <?php else: ?>
                                            <div class="no-photo">📷</div>
                                        <?php endif; ?>
                                        <div class="item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                    </a>
                                    <div class="action-icons">
                                        <form method="POST" style="margin: 0; padding: 0;">
                                            <input type="hidden" name="toggle_favorite" value="1">
                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="favorite-btn <?php echo $item['is_favorite'] ? 'favorited' : ''; ?>">
                                                <svg class="icon-heart <?php echo $item['is_favorite'] ? 'filled' : ''; ?>" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.5 11.54L12 21.35z"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>Пустой список.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Раздел "Универсальные желания" -->
        <?php if (!empty($groupedData['universal'])): ?>
            <h3>✨ Универсальные желания</h3>
            <?php foreach ($groupedData['universal'] as $holiday): ?>
                <div class="holiday-card fade-in">
                    <h4><?php echo htmlspecialchars($holiday['name']); ?> (<?php echo $holiday['date'] ?? 'без даты'; ?>)</h4>
                    <?php if ($holiday['items']): ?>
                        <div class="items-grid">
                            <?php foreach ($holiday['items'] as $item): ?>
                                <div class="item-card">
                                    <a href="item_detail.php?item_id=<?php echo $item['id']; ?>&holiday_id=<?php echo $holiday['id']; ?>">
                                        <?php if ($item['photo_path']): ?>
                                            <img src="../<?php echo $item['photo_path']; ?>" alt="Фото товара">
                                        <?php else: ?>
                                            <div class="no-photo">📷</div>
                                        <?php endif; ?>
                                        <div class="item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                    </a>
                                    <div class="action-icons">
                                        <form method="POST" style="margin: 0; padding: 0;">
                                            <input type="hidden" name="toggle_favorite" value="1">
                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="favorite-btn <?php echo $item['is_favorite'] ? 'favorited' : ''; ?>">
                                                <svg class="icon-heart <?php echo $item['is_favorite'] ? 'filled' : ''; ?>" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.5 11.54L12 21.35z"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>Пустой список.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Нижняя панель навигации -->
    <div class="bottom-nav">
        <a href="../index.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            Главная
        </a>
        <a href="profile.php" class="active">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            Профиль
        </a>
        <a href="create_wishlist.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            Создать
        </a>
        <a href="logout.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L13 5.83V11h-2V5.83L8.41 8.41 7 7l5-5 5 5zM17 17v-2h-2v-6h-2v6H8v2h8z"/></svg>
            Выйти
        </a>
    </div>
    <!-- Кнопка добавления (плавающая) -->
    <a href="create_wishlist.php" class="add-button">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
    </a>
</body>
</html>