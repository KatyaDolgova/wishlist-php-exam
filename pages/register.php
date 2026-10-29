<?php
require_once __DIR__ . '/../includes/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (strlen($password) < 6) {
        $error = 'Пароль должен содержать не менее 6 символов';
    }
    if (!$error) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $hashed_password]);
            header('Location: login.php');
            exit();
        } catch (PDOException $e) {
            $error = 'Пользователь с таким email уже существует';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Верхняя панель -->
    <div class="header-actions">
        <a href="../pages/login.php" class="header-btn">Вход</a>
    </div>
    <div class="container">
        <button class="btn-back" onclick="location.href='../index.php'">← Назад</button>
        <h2>Создайте аккаунт</h2>
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Форма регистрации -->
        <form method="POST" class="fade-in">
            <div style="margin-bottom: 15px;">
                <label for="name">Имя:</label>
                <input type="text" name="name" placeholder="Ваше имя" required>
            </div>
            <div style="margin-bottom: 15px;">
                <label for="email">Email:</label>
                <input type="email" name="email" placeholder="your@email.com" required>
            </div>
            <div style="margin-bottom: 15px;">
                <label for="password">Пароль (мин. 6 символов):</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-action">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width: 18px; height: 18px; margin-right: 5px;"><path d="M12 6c-3.76 0-7 2.24-7 5c0 2.88 2.88 7 7 7c3.76 0 7-2.24 7-5c0-2.88-2.88-7-7-7zm0 10c-1 0-1.91-.3-2.74-.85C8.93 15.5 8.43 15 8 15H4v3h16v-3h-4c-.43 0-.93.5-1.26.85C13.91 16.3 13 16.6 12 16.6z"/></svg>
                Зарегистрироваться
            </button>
        </form>
        <!-- Ссылка на вход -->
        <p style="text-align: center; margin-top: 20px;">
            Уже есть аккаунт? <a href="login.php">Войдите</a>
        </p>
    </div>

    <!-- Нижняя панель навигации -->
    <div class="bottom-nav">
        <a href="../index.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            Главная
        </a>
        <a href="login.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M11 7l-5 5 5 5v3H8v-2H3V8h5V6h3v1zm4 10h3v2h5v-5h-2v-3h-3v3h-2v3z"/></svg>
            Вход
        </a>
        <a href="register.php" class="active">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 6c-3.76 0-7 2.24-7 5c0 2.88 2.88 7 7 7c3.76 0 7-2.24 7-5c0-2.88-2.88-7-7-7zm0 10c-1 0-1.91-.3-2.74-.85C8.93 15.5 8.43 15 8 15H4v3h16v-3h-4c-.43 0-.93.5-1.26.85C13.91 16.3 13 16.6 12 16.6z"/></svg>
            Регистрация
        </a>
    </div>
</body>
</html>