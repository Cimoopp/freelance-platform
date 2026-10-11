# Honer Freelance — фриланс-биржа (MVP)

Лёгкая фриланс-платформа в духе **Kwork + FL.ru**: услуги («кворки»), проекты со ставками,
заказы с эскроу-логикой, внутренний баланс, споры и админ-панель с **мгновенным управлением
комиссиями через AJAX**.

## Стек

- **PHP 7.4** — без синтаксиса PHP 8 (никаких `match`, `str_contains`, union-типов,
  promotion, named arguments, nullsafe, `readonly`, enum, `never`, fibers).
- **MySQL 5.7** — без CTE, оконных функций, CHECK, генерируемых столбцов с подзапросами,
  JSON-типов. JSON хранится в `TEXT`.
- Собственный микро-MVC, PDO, подготовленные выражения.
- Фронтенд: HTML5 + CSS3 (Flexbox/Grid) + vanilla JS (ES5/ES6). Без сборщиков и фреймворков.

## Требования к серверу

| Компонент | Версия |
|:---|:---|
| PHP | 7.4 (расширения: pdo_mysql, mbstring, fileinfo, json, gd — по желанию) |
| MySQL | 5.6+ / 5.7 |
| Apache | с `mod_rewrite` (или Nginx + правила из README) |

## Установка

### 1. Клонировать и подготовить права

```bash
git clone https://github.com/Cimoopp/freelance-platform.git
cd freelance-platform
mkdir -p public/uploads/avatars public/uploads/services logs
chmod -R 775 public/uploads logs
```

### 2. Создать базу данных

```bash
mysql -u root -p -e "CREATE DATABASE freelance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p freelance < migrations/001_initial_schema.sql
mysql -u root -p freelance < seeds/001_initial_data.sql
```

### 3. Настроить подключение

Открой `app/Config/config.php` и укажи свои параметры:

```php
'db' => [
    'host' => '127.0.0.1',
    'port' => '3306',
    'name' => 'freelance',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4',
],
```

### 4. Создать администратора

Bcrypt-хеш нельзя «вписать руками» в SQL, поэтому админ создаётся скриптом
(пароль по умолчанию — `admin123`):

```bash
php tools/create_admin.php        # admin@example.com / admin123
php tools/create_admin.php второй@mail.ru МойПароль123 МоёИмя
```

Скрипт идемпотентный: повторный запуск обновляет пароль и роль, а не создаёт дубль.

### 5. Указать корень сайта на `public/`

**Apache.** Либо `DocumentRoot` на папку `public/`, либо корневой `.htaccess` уже перенаправляет
запросы (файл в корне репозитория). Убедитесь, что `AllowOverride All` включён.

**Nginx:**

```nginx
server {
    listen 80;
    server_name freelance.local;
    root /var/www/freelance-platform/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php7.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### 6. Cron для авто-подтверждения заказов

Заказ, висящий в статусе «выполнен фрилансером», через 5 дней подтверждается автоматически.
Логика работает и «на горячую» при заходе на страницу заказа, но для надёжности добавьте:

```
*/15 * * * * php /var/www/freelance-platform/tools/cron.php >> /var/www/freelance-platform/logs/cron.log 2>&1
```

### 7. Первый вход

- Админ: `admin@example.com` / `admin123`
- Панель: `/admin`

## Структура проекта

```
public/          точка входа, ассеты, загрузки
app/Core/        ядро (Router, Database, Auth, View и др.)
app/Controllers/ публичные и админские контроллеры
app/Models/      модели таблиц
app/Views/       шаблоны
migrations/      SQL-схема
seeds/           начальные данные
tools/           cli-утилиты (create_admin, cron)
logs/            журналы ошибок
```

## Комиссии (главная функция)

Приоритет расчёта при завершении заказа:

1. персональная комиссия фрилансера (`users.commission_rate`);
2. комиссия категории (`categories.commission_rate`);
3. глобальная комиссия (`settings.global_commission_rate`).

`commission_amount = amount * rate / 100`; фрилансеру зачисляется `amount - commission_amount`,
комиссия пишется в `transactions` (`type='commission'`) на аккаунт администратора.

Страница `/admin/commissions` позволяет менять глобальную ставку, комиссию каждой категории
и персональные ставки фрилансеров **inline, без перезагрузок, с toast-уведомлениями**.

## Безопасность

- Все запросы — PDO prepare/execute.
- Вывод — `htmlspecialchars` через хелпер `e()`.
- CSRF-токен на каждой POST-форме (`Csrf::field()` / `Csrf::check()`).
- Пароли — `password_hash(PASSWORD_DEFAULT)`.
- Загрузки: проверка MIME + расширения, рандомное имя, запрет выполнения PHP через
  `public/uploads/.htaccess` (`php_flag engine off`).
- Сессии: `httponly`, `samesite=Lax`, `secure` при HTTPS, регенерация ID при входе.
- Rate-limit на вход: счётчик попыток в сессии.
- Ошибки — в `logs/error.log`, пользователю не показываются.

## Лицензия

MIT.
