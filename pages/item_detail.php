<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$itemId = $_GET['item_id'] ?? null;
$holidayId = $_GET['holiday_id'] ?? null;

if (!$itemId || !$holidayId) {
    die("Не указан ID желания или праздника.");
}

// Получаем информацию о желании
$stmt = $pdo->prepare("
    SELECT h.name as holiday_name, h.date, h.owner_user_id, u.name as owner_name,
           wi.title, wi.description, wi.url, wi.price, wi.status, wi.photo_path, wi.item_type
    FROM wishlist_items wi
    JOIN holidays h ON wi.holiday_id = h.id
    JOIN users u ON h.owner_user_id = u.id
    WHERE wi.id = ? AND h.id = ?
");
$stmt->execute([$itemId, $holidayId]);
$item = $stmt->fetch();

if (!$item) {
    die("Желание не найдено.");
}

// Получаем праздники текущего пользователя для добавления
$myHolidays = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT DISTINCT name FROM holidays WHERE owner_user_id = ? ORDER BY name");
    $stmt->execute([$_SESSION['user_id']]);
    $myHolidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$message = '';
$error = '';

// Обработка добавления в свои списки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_my_list'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
    $holidayName = trim($_POST['new_holiday_name']);
    // Если выбрано "Другое", используем введенное значение
    if ($holidayName === 'Другое' && !empty($_POST['custom_holiday_name'])) {
        $holidayName = trim($_POST['custom_holiday_name']);
    }
    // Проверяем, существует ли уже такой праздник у пользователя
    $stmt = $pdo->prepare("SELECT id FROM holidays WHERE name = ? AND owner_user_id = ?");
    $stmt->execute([$holidayName, $_SESSION['user_id']]);
    $existingHoliday = $stmt->fetch();
    if ($existingHoliday) {
        $holidayId = $existingHoliday['id'];
    } else {
        // Создаем новый праздник
        $stmt = $pdo->prepare("INSERT INTO holidays (name, owner_user_id) VALUES (?, ?)");
        $stmt->execute([$holidayName, $_SESSION['user_id']]);
        $holidayId = $pdo->lastInsertId();
    }
    // Создаем копию желания в своих списках
    $stmt = $pdo->prepare("
        INSERT INTO wishlist_items (title, description, url, price, photo_path, status, item_type, holiday_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $item['title'],
        $item['description'],
        $item['url'],
        $item['price'],
        $item['photo_path'],
        $item['status'],
        $item['item_type'],
        $holidayId
    ]);
    $message = 'Желание добавлено в ваш список!';
}

// Обработка редактирования (только если принадлежит пользователю)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    // Проверяем, принадлежит ли желание текущему пользователю
    $stmt = $pdo->prepare("
        SELECT h.owner_user_id
        FROM wishlist_items wi
        JOIN holidays h ON wi.holiday_id = h.id
        WHERE wi.id = ? AND h.id = ?
    ");
    $stmt->execute([$itemId, $holidayId]);
    $owner = $stmt->fetch();
    if ($owner && isset($_SESSION['user_id']) && $owner['owner_user_id'] == $_SESSION['user_id']) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $url = trim($_POST['url']);
        $status = $_POST['status'];
        $itemType = $_POST['item_type'];
        $holiday_name = trim($_POST['holiday_name']);
        $date = $_POST['date'] ?: null;

        // Обновляем желание
        $stmt = $pdo->prepare("
            UPDATE wishlist_items
            SET title = ?, description = ?, url = ?, status = ?, item_type = ?
            WHERE id = ?
        ");
        $stmt->execute([$title, $description, $url, $status, $itemType, $itemId]);

        // Обновляем праздник
        $stmt = $pdo->prepare("
            UPDATE holidays
            SET name = ?, date = ?
            WHERE id = ?
        ");
        $stmt->execute([$holiday_name, $date, $holidayId]);

        // Перезагружаем данные
        $stmt = $pdo->prepare("
            SELECT h.name as holiday_name, h.date, h.owner_user_id, u.name as owner_name,
                   wi.title, wi.description, wi.url, wi.price, wi.status, wi.photo_path, wi.item_type
            FROM wishlist_items wi
            JOIN holidays h ON wi.holiday_id = h.id
            JOIN users u ON h.owner_user_id = u.id
            WHERE wi.id = ? AND h.id = ?
        ");
        $stmt->execute([$itemId, $holidayId]);
        $item = $stmt->fetch();
    } else {
        $error = 'У вас нет прав редактировать это желание.';
    }
}

// Обработка удаления (только если принадлежит пользователю)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    // Проверяем, принадлежит ли желание текущему пользователю
    $stmt = $pdo->prepare("
        SELECT h.owner_user_id
        FROM wishlist_items wi
        JOIN holidays h ON wi.holiday_id = h.id
        WHERE wi.id = ? AND h.id = ?
    ");
    $stmt->execute([$itemId, $holidayId]);
    $owner = $stmt->fetch();
    if ($owner && isset($_SESSION['user_id']) && $owner['owner_user_id'] == $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM wishlist_items WHERE id = ?");
        $stmt->execute([$itemId]);
        header('Location: profile.php');
        exit();
    } else {
        $error = 'У вас нет прав удалить это желание.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($item['title']); ?></title>
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
        <button class="btn-back" onclick="goBack()">← Назад</button>
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Основной контент -->
        <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px;">
            <!-- Изображение -->
            <div style="flex: 1; min-width: 300px;">
                <?php if ($item['photo_path']): ?>
                    <img src="../<?php echo $item['photo_path']; ?>" alt="Фото товара" class="item-detail-img">
                <?php else: ?>
                    <div style="width: 100%; max-width: 400px; height: 300px; display: flex; align-items: center; justify-content: center; background-color: #222222; border-radius: 8px; font-size: 50px;">📷</div>
                <?php endif; ?>
            </div>

            <!-- Информация -->
            <div style="flex: 2; min-width: 300px;">
                <h3>Информация о желании</h3>
                <div class="item-info">
                    <p><strong>Праздник:</strong> <?php echo htmlspecialchars($item['holiday_name']); ?></p>
                    <p><strong>Дата:</strong> <?php echo $item['date'] ?? 'без даты'; ?></p>
                    <p><strong>Владелец:</strong> <?php echo htmlspecialchars($item['owner_name']); ?></p>
                    <?php if ($item['description']): ?>
                        <p><strong>Описание:</strong> <?php echo htmlspecialchars($item['description']); ?></p>
                    <?php endif; ?>
                    <?php if ($item['url']): ?>
                        <p><strong>Ссылка:</strong> <a href="<?php echo htmlspecialchars($item['url']); ?>" target="_blank"><?php echo htmlspecialchars($item['url']); ?></a></p>
                    <?php endif; ?>
                    <?php if ($item['price']): ?>
                        <p><strong>Цена:</strong> <?php echo number_format($item['price'], 2); ?> руб.</p>
                    <?php endif; ?>
                    <p><strong>Статус:</strong> <?php echo $item['status']; ?></p>
                    <p><strong>Тип желания:</strong>
                        <?php
                        switch($item['item_type']) {
                            case 'for_me': echo 'Хочу себе'; break;
                            case 'for_others': echo 'Хочу подарить'; break;
                            case 'universal': echo 'Универсальное'; break;
                            default: echo $item['item_type'];
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <!-- Действия -->
        <!-- Форма добавления в свои списки -->
        <?php if (isset($_SESSION['user_id']) && $item['owner_user_id'] != $_SESSION['user_id']): ?>
            <div class="add-to-my-list-form">
                <h4>Добавить в свои списки</h4>
                <form method="POST">
                    <input type="hidden" name="add_to_my_list" value="1">
                    <div style="margin-bottom: 15px;">
                        <label for="new_holiday_name">Праздник:</label>
                        <select id="new_holiday_name" name="new_holiday_name" required onchange="toggleCustomHoliday()">
                            <option value="">Выберите праздник</option>
                            <option value="Новый год">Новый год</option>
                            <option value="День рождения">День рождения</option>
                            <option value="8 Марта">8 Марта</option>
                            <option value="23 Февраля">23 Февраля</option>
                            <option value="День Святого Валентина">День Святого Валентина</option>
                            <option value="Другое">Другое</option>
                            <?php foreach ($myHolidays as $holiday): ?>
                                <option value="<?php echo htmlspecialchars($holiday['name']); ?>"><?php echo htmlspecialchars($holiday['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="custom_holiday_div" style="display:none; margin-bottom: 15px;">
                        <label for="custom_holiday_name">Введите название праздника:</label>
                        <input type="text" id="custom_holiday_name" name="custom_holiday_name" placeholder="Название праздника">
                    </div>
                    <button type="submit" class="btn-action">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width: 18px; height: 18px; margin-right: 5px;"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                        Добавить в свой список
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Форма редактирования и удаления -->
        <?php if (isset($_SESSION['user_id']) && $item['owner_user_id'] == $_SESSION['user_id']): ?>
            <div class="edit-form">
                <h4>Редактировать желание</h4>
                <form method="POST">
                    <input type="hidden" name="edit" value="1">
                    <div style="margin-bottom: 15px;">
                        <label for="title">Название:</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($item['title']); ?>" required>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="description">Описание:</label>
                        <textarea id="description" name="description"><?php echo htmlspecialchars($item['description']); ?></textarea>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="url">Ссылка:</label>
                        <input type="url" id="url" name="url" value="<?php echo htmlspecialchars($item['url']); ?>">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="status">Статус:</label>
                        <select id="status" name="status">
                            <option value="expected" <?php echo ($item['status'] == 'expected' ? 'selected' : ''); ?>>Ожидается</option>
                            <option value="purchased" <?php echo ($item['status'] == 'purchased' ? 'selected' : ''); ?>>Куплено</option>
                            <option value="presented" <?php echo ($item['status'] == 'presented' ? 'selected' : ''); ?>>Подарено</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="item_type">Тип желания:</label>
                        <select id="item_type" name="item_type">
                            <option value="for_me" <?php echo ($item['item_type'] == 'for_me' ? 'selected' : ''); ?>>Хочу себе</option>
                            <option value="for_others" <?php echo ($item['item_type'] == 'for_others' ? 'selected' : ''); ?>>Хочу подарить</option>
                            <option value="universal" <?php echo ($item['item_type'] == 'universal' ? 'selected' : ''); ?>>Универсальное</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="holiday_name">Праздник:</label>
                        <input type="text" id="holiday_name" name="holiday_name" value="<?php echo htmlspecialchars($item['holiday_name']); ?>" required>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="date">Дата:</label>
                        <input type="date" id="date" name="date" value="<?php echo $item['date'] ?? ''; ?>">
                    </div>
                    <button type="submit" class="btn-action">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width: 18px; height: 18px; margin-right: 5px;"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                        Сохранить изменения
                    </button>
                </form>

                <!-- Форма удаления -->
                <form method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="delete" value="1">
                    <button type="submit" class="btn-action logout">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width: 18px; height: 18px; margin-right: 5px;"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                        Удалить желание
                    </button>
                </form>
            </div>
        <?php endif; ?>
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
        <a href="create_wishlist.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            Создать
        </a>
        <a href="logout.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L13 5.83V11h-2V5.83L8.41 8.41 7 7l5-5 5 5zM17 17v-2h-2v-6h-2v6H8v2h8z"/></svg>
            Выйти
        </a>
    </div>

    <script>
        function toggleCustomHoliday() {
            var select = document.getElementById('new_holiday_name');
            var customDiv = document.getElementById('custom_holiday_div');
            var customInput = document.getElementById('custom_holiday_name');
            if (select.value === 'Другое') {
                customDiv.style.display = 'block';
                customInput.required = true;
            } else {
                customDiv.style.display = 'none';
                customInput.required = false;
            }
        }
        function goBack() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                var defaultUrl = '<?php echo isset($_SESSION['user_id']) ? 'profile.php' : '../index.php'; ?>';
                window.location.href = defaultUrl;
            }
        }
    </script>
</body>
</html>