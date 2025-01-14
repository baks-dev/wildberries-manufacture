# BaksDev Wildberries Manufacture

[![Version](https://img.shields.io/badge/version-7.1.13-blue)](https://github.com/baks-dev/wildberries-manufacture/releases)
![php 8.3+](https://img.shields.io/badge/php-min%208.3-red.svg)

Модуль производства продукции заказов Wildberries

## Установка

``` bash
$ composer require baks-dev/wildberries-manufacture
```

## Дополнительно

Установка конфигурации и файловых ресурсов:

``` bash
$ php bin/console baks:assets:install
```

Изменения в схеме базы данных с помощью миграции

``` bash
$ php bin/console doctrine:migrations:diff
$ php bin/console doctrine:migrations:migrate
```

## Тестирование

``` bash
$ php bin/phpunit --group=wildberries-manufacture
```

## Лицензия ![License](https://img.shields.io/badge/MIT-green)

The MIT License (MIT). Обратитесь к [Файлу лицензии](LICENSE.md) за дополнительной информацией.

