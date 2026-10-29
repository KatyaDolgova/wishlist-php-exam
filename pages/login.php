<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, name, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        header('Location: profile.php');
        exit();
    } else {
        $error = 'Неверный email или пароль';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Верхняя панель -->
    <div class="header-actions">
        <a href="../pages/register.php" class="header-btn">Регистрация</a>
    </div>

    <div class="container">
        <button class="btn-back" onclick="location.href='../index.php'">← Назад</button>
        <h2>Вход в аккаунт</h2>
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Форма входа -->
        <form method="POST" class="fade-in">
            <div style="margin-bottom: 15px;">
                <label for="email">Email:</label>
                <input type="email" name="email" placeholder="your@email.com" required>
            </div>
            <div style="margin-bottom: 15px;">
                <label for="password">Пароль:</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-action">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width: 18px; height: 18px; margin-right: 5px;"><path d="M11 7l-5 5 5 5v3H8v-2H3V8h5V6h3v1zm4 10h3v2h5v-5h-2v-3h-3v3h-2v3z"/></svg>
                Войти
            </button>
        </form>
        <!-- Ссылка на регистрацию -->
        <p style="text-align: center; margin-top: 20px;">
            Нет аккаунта? <a href="register.php">Зарегистрируйтесь</a>
        </p>
    </div>
    <!-- Нижняя панель навигации -->
    <div class="bottom-nav">
        <a href="../index.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            Главная
        </a>
        <a href="login.php" class="active">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M11 7l-5 5 5 5v3H8v-2H3V8h5V6h3v1zm4 10h3v2h5v-5h-2v-3h-3v3h-2v3z"/></svg>
            Вход
        </a>
        <a href="register.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 6c-3.76 0-7 2.24-7 5c0 2.88 2.88 7 7 7c3.76 0 7-2.24 7-5c0-2.88-2.88-7-7-7zm0 10c-1 0-1.91-.3-2.74-.85C8.93 15.5 8.43 15 8 15H4v3h16v-3h-4c-.43 0-.93.5-1.26.85C13.91 16.3 13 16.6 12 16.6z"/></svg>
            Регистрация
        </a>
    </div>
</body>
</html>