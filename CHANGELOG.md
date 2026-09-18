# CHANGELOG

## [7.4.20] - 2026-09-18

### Добавлено
- Новый тестовый файл `Messenger/AddOrdersPackageByPartCompleted/Tests/AddOrdersPackageByPartCompletedDispatcherDebugTest.php` для отладки обработчика добавления заказов в упаковку
- Обновлены copyright даты в файлах на 2026 год

### Изменено
- В `Controller/Admin/FbsController.php`:
  - Обновлена дата copyright с 2023 на 2026 год
  - Добавлен дополнительный пробел в комментарии
  - Удален импорт класса `BaksDev\Manufacture\Part\Type\Complete\ManufacturePartComplete`

- В `Messenger/AddOrdersPackageByPartCompleted/AddOrdersPackageByPartCompletedDispatcher.php`:
  - Обновлена дата copyright с 2023 на 2026 год
  - Добавлен дополнительный пробел в комментарии
  - Класс изменен с `final class` на `final readonly class`
  - Изменено сообщение лога с 'ozon-manufacture: не найдено активное событие заказа' на 'wildberries-manufacture: не найдено активное событие заказа'
  - Изменен тип логирования с `error` на `warning` для сообщений о неверном типе доставки и завершенном заказе

- В `Messenger/NewSupplyByPartCompletedDispatcher.php`:
  - Обновлена дата copyright с 2025 на 2026 год
  - Добавлен дополнительный пробел в комментарии
  - Удален импорт класса `BaksDev\Manufacture\Part\Repository\ManufacturePartEvent\ManufacturePartEventInterface`

### Исправлено
- Ошибки в логгировании при обработке заказов для Wildberries FBS

### Улучшения
- Улучшена стабильность обработки сообщений при завершении производственных партий
- Оптимизирована логика проверки статусов заказов
- Добавлена более точная обработка ошибок в системе упаковки заказов

[7.4.20]: https://github.com/baks-dev/wildberries-manufacture/compare/v7.4.19...v7.4.20