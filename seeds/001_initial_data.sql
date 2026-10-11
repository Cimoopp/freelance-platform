-- =====================================================================
--  Honer Freelance — начальные данные
--  Импорт:  mysql -u root -p freelance < seeds/001_initial_data.sql
--  Администратор создаётся отдельно:  php tools/create_admin.php
--  (bcrypt-хеш нельзя сгенерировать средствами MySQL 5.7)
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Корневые категории
-- ---------------------------------------------------------------------
INSERT INTO `categories` (`id`,`parent_id`,`name`,`slug`,`description`,`commission_rate`,`sort_order`,`status`) VALUES
  (1, NULL, 'Разработка и IT',    'razrabotka-i-it',    'Веб, мобильные приложения, боты, автоматизация.', NULL, 10, 'active'),
  (2, NULL, 'Дизайн',             'dizayn',             'Логотипы, брендинг, интерфейсы, иллюстрации.',     NULL, 20, 'active'),
  (3, NULL, 'Тексты и переводы',  'teksty-i-perevody',  'Копирайтинг, редактура, локализация.',            NULL, 30, 'active'),
  (4, NULL, 'Маркетинг',          'marketing',          'Реклама, SEO, SMM, аналитика.',                   NULL, 40, 'active'),
  (5, NULL, 'Аудио и видео',      'audio-i-video',      'Монтаж, озвучка, музыка, анимация.',              NULL, 50, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `slug` = VALUES(`slug`);

-- ---------------------------------------------------------------------
-- Подкатегории (демонстрация вложенности для админки)
-- ---------------------------------------------------------------------
INSERT INTO `categories` (`id`,`parent_id`,`name`,`slug`,`description`,`commission_rate`,`sort_order`,`status`) VALUES
  (6, 1, 'Веб-разработка',        'web-razrabotka',    'Сайты, лендинги, веб-приложения.',   NULL, 11, 'active'),
  (7, 1, 'Мобильные приложения',  'mobilnye-prilo',    'Android, iOS, кроссплатформа.',      NULL, 12, 'active'),
  (8, 2, 'UI/UX дизайн',          'ui-ux-dizayn',      'Прототипы, макеты, дизайн-системы.', NULL, 21, 'active'),
  (9, 2, 'Логотипы и брендинг',   'logo-i-brending',   'Знаки, айдентика, гайдлайны.',       NULL, 22, 'active'),
  (10, 4, 'SEO продвижение',      'seo-prodvizhenie',  'Оптимизация, семантика, ссылки.',    NULL, 41, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `parent_id` = VALUES(`parent_id`);

-- ---------------------------------------------------------------------
-- Настройки платформы
-- ---------------------------------------------------------------------
INSERT INTO `settings` (`key`,`value`) VALUES
  ('site_name',              'Honer Freelance'),
  ('site_description',       'Биржа фриланса: услуги, проекты, безопасные сделки.'),
  ('currency',               'RUB'),
  ('currency_symbol',        '₽'),
  ('contact_email',          'support@example.com'),
  ('contact_phone',          '+7 000 000-00-00'),
  ('global_commission_rate', '10.00'),
  ('auto_confirm_days',      '5'),
  ('min_withdrawal',         '500.00'),
  ('registration_open',      '1')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
