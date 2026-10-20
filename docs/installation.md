# Установка

## Требования

- PHP 8.2+
- Laravel 12 или 13
- коробочный Bitrix24 с inbound webhook
- Composer 2

## VCS-репозиторий

Пакет не публикуется в Packagist.

```bash
composer config repositories.nbcom-bitrix24 vcs https://github.com/SmoKE585/NBCom-Bitrix24-Laravel-Client.git
composer require nbcom/laravel-bitrix24:^1.0
```

Если тега ещё нет:

```bash
composer require nbcom/laravel-bitrix24:dev-main
```

Для CI можно добавить в `composer.json` вручную:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/SmoKE585/NBCom-Bitrix24-Laravel-Client.git"
        }
    ]
}
```

## Настройка

```dotenv
BITRIX24_ENDPOINT=https://bitrix.example.ru/rest/1/ab12cd34ef56gh78/
BITRIX24_CLIENT_ID=nbcom.laravel-bitrix24
```

`BITRIX24_ENDPOINT` — полный URL webhook, включая user id и токен.
Не коммить его.

## Проверка

```bash
php artisan bitrix24:install
```

или:

```bash
php artisan vendor:publish --tag=bitrix24-config
php artisan bitrix24:check --refresh
php artisan bitrix24:check --smoke
```

Если команда вернула код 1 — включи недостающие scopes в настройках webhook
и повтори `bitrix24:check --refresh`.

## Публикация гайда для агентов

```bash
php artisan bitrix24:agent-docs
```

Файл появится в `.ai/bitrix24.md`. Добавь ссылку на него в `AGENTS.md`.
