<?php
include('db.php');
session_start();

// Проверка авторизации администратора
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}

// Обработка выхода
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// Допустимые статусы
$valid_statuses = ['Новая', 'Идет обучение', 'Обучение завершено'];
$status_updated = false;

// Обработка изменения статуса заявки
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_id'])) {
    $request_id = (int)$_POST['request_id'];
    $status = $_POST['status'] ?? '';

    // Валидация статуса
    if (!in_array($status, $valid_statuses, true)) {
        die('Недопустимый статус заявки');
    }

    // Использование подготовленных выражений
    $stmt = $con->prepare("UPDATE request SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $request_id);

    if (!$stmt->execute()) {
        die('Ошибка обновления: ' . $con->error);
    } else {
        $status_updated = true;
    }
}

// Получение заявок с пагинацией (10 заявок на страницу)
$page = (int)($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

$query = $con->query("
    SELECT request.*, users.login, users.fullname,
           COUNT(*) OVER() as total_count
    FROM request
    INNER JOIN users ON request.user_id = users.id
    ORDER BY request.date DESC
    LIMIT $limit OFFSET $offset
");

if (!$query) die('Ошибка запроса: ' . $con->error);

// Подсчёт статистики одним запросом
$stats_query = $con->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Новая' THEN 1 ELSE 0 END) as new_requests,
        SUM(CASE WHEN status = 'Идет обучение' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'Обучение завершено' THEN 1 ELSE 0 END) as completed
    FROM request
");
$stats = $stats_query->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора — Учусь.РФ</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #007bff;
            --primary-dark: #0d47a1;
            --success: #28a745;
            --warning: #ffc107;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
            --border-radius: 12px;
            --shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: #fff;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        /* Шапка */
        .header {
            background: white;
            padding: 25px 30px;
            border-bottom: 1px solid #e0e0e0;
        }

        .header h1 {
            color: var(--dark);
            font-size: 28px;
            margin-bottom: 5px;
        }

        .subtitle {
            color: #666;
            font-size: 16px;
        }

        /* Навигация */
        .nav-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
            background: var(--light);
            border-bottom: 1px solid #e0e0e0;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* Статистика */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            padding: 25px 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary);
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        /* Список заявок */
        .requests-container {
            padding: 0 30px 30px;
        }

        .request-item {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-left: 4px solid;
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2
                    .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .user-info h3 {
            color: var(--dark);
            margin-bottom: 5px;
        }

        .request-id {
            background: var(--light);
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            color: #666;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-new {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }

        .status-in-progress {
            background: rgba(23, 162, 184, 0.2);
            color: #17a2b8;
        }

        .status-completed {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }

        .request-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .detail-item {
            padding: 10px;
            background: var(--light);
            border-radius: var(--border-radius);
        }

        .detail-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 16px;
            color: var(--dark);
            word-break: break-word;
        }

        /* Форма изменения статуса */
        .status-form {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px dashed #e0e0e0;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: var(--border-radius);
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .btn-save {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--success), #20c997);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        /* Пагинация */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }

        .page-link {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s ease;
        }

        .page-link:hover,
        .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Пустое состояние */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--light);
            border-radius: var(--border-radius);
        }

        .empty-state i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 15px;
        }

        .empty-state h3 {
            color: var(--dark);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
        }

        /* Уведомление */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            background: linear-gradient(135deg, var(--success), #20c997);
            color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            z-index: 1000;
            animation: slideInRight 0.5s ease-out, fadeOut 0.5s ease-out 2.5s forwards;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
                visibility: hidden;
            }
        }

        /* Адаптивность */
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 8px;
            }

            .nav-bar {
                flex-direction: column;
                gap: 10px;
                padding: 15px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                padding: 20px;
            }

            .request-item {
                padding: 20px;
            }

            .request-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Панель администратора</h1>
            <p class="subtitle">Управление заявками пользователей</p>
        </div>

        <div class="nav-bar">
            <a href="index.php" class="btn btn-outline">
                <i class="fas fa-home"></i> Главная
            </a>
            <a href="?logout=1" class="btn btn-outline" onclick="return confirm('Выйти из аккаунта?')">
                <i class="fas fa-sign-out-alt"></i> Выход
            </a>
        </div>

        <!-- Статистика -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Всего заявок</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: var(--warning);"><?= $stats['new_requests'] ?></div>
                <div class="stat-label">Новые</div>
            </div>
            <div class="stat-card">
                <div
                                <div class="stat-number" style="color: var(--info);"><?= $stats['in_progress'] ?></div>
                <div class="stat-label">Идёт обучение</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: var(--success);"><?= $stats['completed'] ?></div>
                <div class="stat-label">Обучение завершено</div>
            </div>
        </div>

        <!-- Список заявок -->
        <div class="requests-container">
            <?php
            if ($query->num_rows === 0) {
            ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Заявок пока нет</h3>
                    <p>Когда пользователи оставят заявки, они появятся здесь</p>
                </div>
            <?php } else {
                while ($request = $query->fetch_assoc()) {
                    // Определяем класс для статуса
            $status_class = match($request['status']) {
                'Новая' => 'status-new',
                'Идет обучение' => 'status-in-progress',
                'Обучение завершено' => 'status-completed',
                default => 'status-new'
            };
            ?>
                <div class="request-item <?= $status_class ?>">
                    <div class="request-header">
                        <div class="user-info">
                            <h3><?= htmlspecialchars($request['login']) ?></h3>
                            <p><?= htmlspecialchars($request['fullname']) ?></p>
                        </div>
                        <div>
                            <span class="request-id">Заявка №<?= htmlspecialchars($request['id']) ?></span>
                            <span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($request['status']) ?></span>
                        </div>
                    </div>

                    <div class="request-details">
                <div class="detail-item">
                    <div class="detail-label">Дата подачи</div>
            <div class="detail-value"><?= htmlspecialchars($request['date']) ?></div>
                </div>
                <div class="detail-item">
            <div class="detail-label">Услуга</div>
            <div class="detail-value"><?= htmlspecialchars($request['curses'] ?? '—') ?></div>
                </div>
                <div class="detail-item">
            <div class="detail-label">Оплата</div>
            <div class="detail-value"><?= htmlspecialchars($request['payment'] ?? '—') ?></div>
                </div>
                <div class="detail-item">
            <div class="detail-label">Комментарий</div>
            <div class="detail-value"><?= htmlspecialchars($request['review'] ?? '—') ?></div>
                </div>
            </div>

            <!-- Форма изменения статуса -->
            <div class="status-form">
                <form method="POST" class="status-update-form">
                    <input type="hidden" name="request_id" value="<?= $request['id'] ?>">

            <div class="form-group">
                <label class="form-label" for="status_<?= $request['id'] ?>">
                    <i class="fas fa-tag"></i> Изменить статус:
                </label>
                <select name="status" id="status_<?= $request['id'] ?>" class="form-select">
                    <option value="Новая" <?= $request['status'] == 'Новая' ? 'selected' : '' ?>>
                        🆕 Новая
                    </option>
            <option value="Идет обучение" <?= $request['status'] == 'Идет обучение' ? 'selected' : '' ?>>
                        📖 Идёт обучение
                    </option>
            <option value="Обучение завершено" <?= $request['status'] == 'Обучение завершено' ? 'selected' : '' ?>>
                        ✅ Обучение завершено
            </option>
                </select>
            </div>

            <button type="submit" class="btn btn-save">
                <i class="fas fa-save"></i> Сохранить изменения
            </button>
                </form>
            </div>
        </div>
    <?php
        }
    }
    ?>
        </div>

        <!-- Пагинация -->
        <?php if ($stats['total'] > $limit): ?>
            <div class="pagination">
                <?php
                $total_pages = ceil($stats['total'] / $limit);
                for ($i = 1; $i <= $total_pages; $i++):
                ?>
                    <a href="?page=<?= $i ?>"
               class="page-link <?= $page === $i ? 'active' : '' ?>">
                <?= $i ?>
            </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Уведомление об успехе -->
    <?php if ($status_updated): ?>
        <div class="notification">
            ✅ Статус заявки успешно обновлён!
        </div>
    <?php endif; ?>

    <script>
        // Обработка отправки форм статуса
        document.querySelectorAll('.status-update-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('.btn-save');
                const originalText = submitBtn.innerHTML;

                // Блокировка кнопки на время обработки
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Сохранение...';

                // Восстановление через 2 секунды (можно заменить на обработку ответа сервера)
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 2000);
            });
        });

        // Плавная прокрутка к уведомлениям
        const notification = document.querySelector('.notification');
        if (notification) {
            notification.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });

            // Автоматическое скрытие через 3 секунды
            setTimeout(() => {
                if (notification) {
                    notification.style.opacity = '0';
                    setTimeout(() => notification.remove(), 500);
                }
            }, 3000);
        }

        // Подсветка активной страницы в пагинации
        document.querySelectorAll('.page-link').forEach(link => {
            if (link.getAttribute('href') === window.location.pathname + window.location.search) {
                link.classList.add('active');
            }
        });
    </script>
</body>
</html>
