# Wildberries Manufacture - Техническая спецификация

## Обзор

Wildberries Manufacture - это Symfony Bundle для интеграции с API Wildberries, реализующий управление производственными процессами по заказам и остаткам товаров. Проект построен на современных архитектурных паттернах Symfony и следующих принципах:

## Архитектурные паттерны

### Dependency Injection (DI)
- Автовнедрение через PSR-11 контейнер
- Автоконфигурация сервисов через Symfony DI
- Конфигурация в `Resources/config/services.php`

### UseCase Pattern
- DTO (Data Transfer Object) для передачи данных
- Handler для реализации бизнес-логики
- Использование AbstractHandler для общих операций

### Message Bus Pattern
- Асинхронная обработка через Symfony Messenger
- Отдельные транспорты для каждого профиля пользователя
- Использование Message Handlers для обработки событий

### Scheduler Pattern
- Периодические задачи через Symfony Scheduler
- Использование AsCronTask и ScheduleInterface
- Крон-подобное расписание задач

### Repository Pattern
- Интерфейсы для работы с данными
- Реализации через ORM и DBAL
- Поддержка Generator для обработки больших объемов данных

## Структура проекта

```
BaksDev\Wildberries\Manufacture\
├── Api/                          # Обертки для Wildberries API
│   ├── Orders/                   # API заказов (GetWbOrdersRequest)
│   │   └── GetWbOrdersRequest    # Получение заказов с пагинацией
│   ├── Stocks/                   # API остатков
│   │   ├── GetWbStocksRequest    # Получение остатков (FBO)
│   │   └── WbStocksRequestDTO    # DTO для остатков
│   ├── Warehouses/               # API складов
│   │   └── GetWbFbsWarehousesRequest
│   └── Fbs/                      # API для FBS-модели
│       └── PutWbFbsStocksRequest # Обновление FBS-остатков (заблокировано)
├── Commands/                     # CLI команды
│   ├── GetWbOrdersLastCommand    # baks:wildberries-manufacture:orders-last
│   ├── GetWbStocksFboCommand     # baks:wildberries-manufacture:stocks-fbo
│   └── UpdateWbStocksFbsCommand  # baks:wildberries-manufacture:stocks-fbs
├── Controller/Admin/             # Админ-контроллеры
│   ├── FbsController             # Список сборочных заданий FBS
│   └── FboController             # Список товаров для пополнения FBO
├── Entity/                       # Doctrine сущности
│   ├── WbOrder                   # Заказ Wildberries (table: wb_order)
│   └── WbStock                   # Остаток на складе WB (table: wb_stock)
├── Messenger/                    # Обработка сообщений
│   ├── UpdateWbOrders/           # Обновление заказов (Message, Dispatcher, Handler)
│   ├── UpdateWbStocks/           # Обновление остатков (Message)
│   ├── Schedules/
│   │   ├── GetWbOrders/          # Сообщения для получения заказов
│   │   ├── GetWbStocks/          # Сообщения для получения остатков
│   │   └── ResetWbFbsStocks/     # Сообщения для сброса FBS-остатков
│   ├── AddOrdersPackageByPartCompleted/  # Открытие новой поставки после завершения производства
│   └── NewSupplyByPartCompletedDispatcher  # Создание поставки при завершении FBS-производства
├── Repository/                   # Репозитории
│   ├── OrdersDataUpdate/         # Обновление данных заказов (ORM)
│   ├── StocksDataUpdate/         # Обновление данных остатков (ORM)
│   ├── AllWbOrdersGroup/         # Аналитика заказов для FBS (DBAL)
│   ├── AllWbOrdersAnalytics/     # Аналитика заказов для FBO (DBAL)
│   ├── AllWbStocksBarcodes/      # Штрихкоды остатков (DBAL, Generator)
│   └── DeleteAllOrders/          # Очистка старых заказов (DBAL)
├── Schedule/                     # Планировщик задач (cron-like)
│   ├── WbNewOrders/              # Периодическое обновление заказов (каждые 3 часа)
│   ├── WbNewStocks/              # Периодическое обновление остатков
│   ├── WbFbsStocksRefresh/       # Обновление FBS-остатков (ежедневно)
│   └── ClearWbOrders/            # Очистка старых заказов (ежедневно в 00:00)
├── Security/                     # Безопасность
│   ├── Fbs/                      # Роли и Voter для FBS
│   └── Fbo/                      # Роли и Voter для FBO
└── UseCase/                      # Бизнес-логика
    ├── WbOrders/New/             # Создание/обновление заказа (DTO + Handler)
    └── WbStocks/New/             # Создание/обновление остатка (DTO + Handler)
```

## Технические детали

### Сущности

#### WbOrder (Заказ Wildberries)
```php
#[ORM\Entity]
#[ORM\Table(name: 'wb_order')]
class WbOrder extends EntityState
{
    private string $id;              // Уникальный ID заказа
    private ProductInvariableUid $invariable;
    private DateTimeImmutable $date; // Время получения заказа
}
```

#### WbStock (Остаток на складе)
```php
#[ORM\Entity]
#[ORM\Table(name: 'wb_stock')]
class WbStock extends EntityState
{
    private ProductInvariableUid $invariable;
    private int $quantity;           // Количество на складе
}
```

Обе сущности наследуются от `EntityState`, что включает `id`, `created_at`, `updated_at`.

### API обертки

#### GetWbOrdersRequest
- Эндпоинт: `/api/v1/supplier/orders`
- Пагинация через `lastChangeDate`
- Кэширование на 1 час
- Ограничение: 80 000 строк на запрос
- Обработка блокировок: HTTP 429 - повторная попытка
- Макс. частота: 1 запрос в 30 минут

#### GetWbStocksRequest
- Эндпоинт: `/api/v1/supplier/stocks`
- Ограничение: 60 000 строк на запрос
- Макс. частота: 1 запрос в минуту
- Обработка блокировок: sleep(60) при превышении лимита

#### PutWbFbsStocksRequest
- Эндпоинт: `/api/v3/stocks/{warehouseId}`
- Тип запроса: PUT
- Статус: Временно заблокирован (возвращает true без изменений)

#### GetWbFbsWarehousesRequest
- Эндпоинт: `/api/v3/warehouses`

### Messenger Transports
Каждый профиль пользователя имеет свой транспорт Messenger:
- `$profile.'-low'` - Низкий приоритет для фоновых задач
- `(string) $profile` - Обычный транспорт для профиля

Это позволяет обрабатывать данные разных профилей параллельно и изолированно.

### Cache
API обертки используют Symfony Cache для кэширования ответов:
```php
$cache = $this->getCacheInit('wildberries-manufacture');
$key = md5($profileUid.$dateFrom->format(DateTimeInterface::ATOM).$flag.self::class);
$content = $cache->get($key, function(ItemInterface $item) { ... });
```

### Logger
Используются контекстные логгеры через сервисный тег:
```php
#[Target('wildberriesSupportLogger')]
private LoggerInterface $logger;

#[Target('wildberriesManufactureLogger')]
private LoggerInterface $logger;
```

## Зависимости

- `baks-dev/core`: ^7.4 - Ядро фреймворка
- `baks-dev/manufacture-part`: ^7.4 - Модуль производства
- `baks-dev/wildberries-package`: ^7.4 - API обертки Wildberries
- `baks-dev/wildberries-orders`: ^7.4 - API обертки заказов Wildberries

### Системные требования
- PHP 8.4+
- Symfony Framework 6.4+ или 7.0+
- Doctrine ORM 2.17+ / DBAL 3.8+
- Composer 2.5+
- Расширения PHP: `ext-xml`, `ext-json`, `ext-curl`

## CLI команды

### Получение данных о заказах и остатках

```bash
# Получить последние заказы за N дней (интерактивный выбор профиля)
php bin/console baks:wildberries-manufacture:orders-last

# Обновить FBO-остатки (все профили или по выбору)
php bin/console baks:wildberries-manufacture:stocks-fbo

# Обновить FBS-остатки (все профили или по выбору)
php bin/console baks:wildberries-manufacture:stocks-fbs
```

Команды поддерживают:
- Интерактивный выбор профиля пользователя
- Флаг `+` для асинхронного выполнения на всех профилях
- Флаг `-` или `Выйти` для отмены

## Планировщик задач

| Задача | Интервал | Класс |
|--------|----------|-------|
| Обновление заказов | 3 часа | `RefreshWbOrdersSchedule::ORDER_REFRESH_PERIOD` |
| Обновление остатков | 3 часа | `WbNewStocks` |
| Обновление FBS-остатков | 1 день | `RefreshWbFbsStocksSchedule::FBS_STOCK_REFRESH_PERIOD` |
| Очистка старых заказов | Ежедневно 00:00 | `ClearWbOrdersDispatcher` (CronTask) |

## Безопасность (ACL)

### FBS Security
- Роль: `ROLE_WB_MANUFACTURE_FBS`
- Voters: `Fbs\VoterIndex`, `VoterNew`, `VoterEdit`, `VoterDelete`

### FBO Security
- Роль: `ROLE_WB_MANUFACTURE_FBO`
- Voters: `Fbo\VoterIndex`, `VoterNew`, `VoterEdit`, `VoterDelete`

## Установка и настройка

### Установка через Composer
```bash
composer require baks-dev/wildberries-manufacture
```

### Подключение bundle в ядро Symfony
```php
// config/bundles.php
return [
    BaksDev\Wildberries\Manufacture\BaksDevWildberriesManufactureBundle::class => ['all' => true],
];
```

## Тестирование

Тесты расположены в папках с суффиксом `Tests`:
- `UseCase/WbStocks/New/Tests/`
- `UseCase/WbOrders/New/Tests/`

Для модульных тестов используются стандартные PHPUnit аннотации.

```bash
# Запустить все тесты
php bin/phpunit --group=wildberries-manufacture

# Запустить конкретный тест
php bin/phpunit --filter=WbStockNewHandlerTest
```

## Переводы

Переводы находятся в `Resources/translations/`:
- `admin/admin.wb.manufacture.{ru,en}.yaml` - административный интерфейс
- `marketplace/manufacture.marketplace.{ru,en}.yaml` - маркетплейс
- `security/security.{ru,en}.yaml` - сообщения безопасности
- `complete/manufacture.complete.{ru,en}.yaml` - сообщения статусов

## Основные принципы разработки

### Автозагрузка PSR-4
Все классы должны находиться в пространстве имен `BaksDev\Wildberries\Manufacture\` и соответствовать структуре папок.

### DTO Pattern
Для всех UseCase создаются DTO классы с свойствами и геттерами/сеттерами. DTO не должны содержать бизнес-логику.

### AbstractHandler
UseCase Handlers наследуются от `AbstractHandler`, который предоставляет:
- `getRepository($entityClass)` - получение репозитория
- `persist($entity)` - сохранение сущности
- `flush()` - коммит транзакции
- `validatorCollection` - сбор валидаторов

### DBAL vs ORM
- **DBAL**: Используется для сложных аналитических запросов
- **ORM**: Используется для простых операций сущностей

### Генераторы
Некоторые репозитории возвращают `Generator` для эффективной обработки больших объемов данных.

### Дедупликация
Некоторые обработчики используют механизм дедупликации для предотвращения дублирования обработки одинаковых данных.

## Обработка ошибок

### HTTP 429 (Too Many Requests)
При получении HTTP 429 модуль выполняет повторную попытку через заданное время (по умолчанию 30 секунд). Максимальное количество повторных попыток: 3.

### Исключения
Все исключения логируются через контекстные логгеры и обрабатываются в соответствии с типом ошибки:
- `ApiException` - ошибки API Wildberries
- `ValidationException` - ошибки валидации данных
- `DatabaseException` - ошибки работы с базой данных

### Откат транзакций
При ошибках в UseCase Handler транзакция автоматически откатывается, обеспечивая согласованность данных.

## Жизненный цикл данных

### Получение данных из API
1. Запрос к API Wildberries через обертки
2. Кэширование ответа (если настроено)
3. Десериализация данных в DTO
4. Валидация данных
5. Передача в UseCase Handler
6. Сохранение в базу данных

### Обработка ошибок интеграции
- При ошибке API данные не сохраняются
- Ошибка логируется с контекстом
- Сообщение попадает в очередь на повторную обработку

### Синхронизация с внешними системами
- Периодические задачи запускаются через Scheduler
- Каждая задача изолирована и имеет свой транспорт
- При ошибке задача повторяется через заданное время

### Обработка дубликатов
- Используется механизм дедупликации на основе хеша данных
- Дубликаты игнорируются без ошибок
- Логируется информация о дубликатах

## Безопасность

### Защита API ключей
- API ключи хранятся в переменных окружения
- Не хранятся в репозитории
- Используется Symfony DotEnv для загрузки

### Защита от XSS
- Все данные экранируются в Twig шаблонах
- Используется Symfony Validator для валидации ввода

### Защита CSRF
- Все формы используют CSRF токены
- Токены генерируются для каждой сессии

### Роли и права
- `ROLE_WB_MANUFACTURE_FBS` - доступ к FBS функционалу
- `ROLE_WB_MANUFACTURE_FBO` - доступ к FBO функционалу
- `ROLE_ADMIN` - полный доступ ко всем функциям

## Мониторинг и логирование

### Логирование
- `wildberriesSupportLogger` - логи поддержки
- `wildberriesManufactureLogger` - логи модуля производственных процессов

### Метрики
- Количество успешно обработанных заказов
- Количество ошибок API
- Время выполнения команд
- Использование памяти

## Рекомендации по производительности

### Оптимизация запросов
- Использование DBAL для сложных запросов
- Индексация часто используемых полей
- Оптимизация join операций

### Кэширование
- Кэширование ответов API (1 час)
- Кэширование конфигурации
- Кэширование результатов аналитики

### Асинхронная обработка
- Все тяжелые операции выполняются асинхронно
- Использование очередей для балансировки нагрузки
- Мониторинг очередей на переполнение

## Часто задаваемые вопросы

### Как настроить несколько профилей?
```yaml
# config/packages/baks_wildberries_manufacture.yaml
baks_wildberries_manufacture:
    profiles:
        profile1:
            api_key: '%env(API_KEY_1)%'
        profile2:
            api_key: '%env(API_KEY_2)%'
```

### Как отключить кэширование?
```yaml
# config/packages/baks_wildberries_manufacture.yaml
baks_wildberries_manufacture:
    cache_enabled: false
```

### Как настроить повторные попытки?
```yaml
# config/packages/baks_wildberries_manufacture.yaml
baks_wildberries_manufacture:
    max_retries: 5
    retry_delay: 60
```
