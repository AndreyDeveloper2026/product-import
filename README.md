# Импорт/экспорт товаров (Slim + Doctrine + Angular)

Загрузка `.xlsx` → **асинхронный** импорт через очередь (Symfony Messenger + RabbitMQ) → список товаров с серверной
пагинацией/фильтрами → карточка товара (поля, доп. атрибуты, изображения, прогресс и отчёт последнего импорта).

> ⚠️ **Статус проверки.** Код написан в окружении без PHP и сети: `composer install`, `npm install`, сборка и
> тесты **не запускались**. Перед сдачей выполните `make up && make test && make stan` и поправьте возможные
> мелочи (опечатки, версии зависимостей). Архитектура и тесты — полные.

## Быстрый старт

```bash
make up          # создаст .env из .env.example, соберёт образы, поднимет сервисы, применит миграции
make seed        # (опционально) демо-данные из fixtures
```

| Что | Где |
|-----|-----|
| UI (логин `admin` / `admin123`) | http://localhost:8080 |
| Swagger UI / OpenAPI JSON | http://localhost:8080/api/docs · `/api/openapi.json` |
| Health check | http://localhost:8080/api/health |
| RabbitMQ UI | http://localhost:15672 (guest / guest) |

Команды: `make test` · `make stan` · `make cs` / `make cs-fix` · `make migrate` · `make logs` · `make e2e-ui`.

## Стек и структура

```
backend/    Slim 4, PHP-DI, Doctrine ORM 3 + Migrations, Symfony Messenger (AMQP), OpenSpout, JWT
frontend/   Angular 19 (standalone, lazy routes), NgRx Store + Effects, Angular Material
docker/     php (multi-stage), nginx (multi-stage: сборка Angular → nginx)
```

Сервисы docker-compose: `app` (php-fpm), `worker` (Messenger consumer), `nginx` (SPA + прокси `/api` → fpm, раздача `/uploads`),
`db` (MySQL 8.4), `rabbitmq`.

### Backend (слои)

```
Controller/        только принимает запрос → делегирует → отдаёт JSON
Service/Import/    ProductXlsxParser (потоковый парсинг), ProductImportService (оркестрация),
                   ImportSubmissionService (валидация файла + создание задачи + dispatch)
Service/Image/     ImageDownloaderInterface + HttpImageDownloader (SSRF-защита, лимит размера, проверка content-type)
Repository/        ProductRepository, ImportJobRepository — все запросы к БД
Entity/            Product, ProductAttribute, ProductImage, ImportJob
Message/           ImportProductsMessage + Handler (вызывается воркером)
Http/              JWT-middleware, rate limit, единый JSON-обработчик ошибок
```

### Как работает импорт

1. `POST /api/imports` (multipart `file`) — проверка расширения `.xlsx`, размера, MIME (`finfo`) и структуры zip
   (`xl/workbook.xml`); создаётся `ImportJob`, в RabbitMQ уходит `ImportProductsMessage`; ответ **202** с `id` задачи.
2. Воркер (`php bin/worker.php`) парсит файл потоково, строка за строкой:
   * невалидная строка → попадает в отчёт (`level=error`), импорт **продолжается**;
   * картинка не скачалась → предупреждение (`level=warning`), товар сохраняется;
   * товар + атрибуты + изображения пишутся **в одной транзакции**; скачивание картинок — вне транзакции БД;
   * **upsert по `external_code`**: повторный импорт обновляет товар, заменяет атрибуты, синхронизирует изображения
     (существующие по URL не перекачиваются, пропавшие — удаляются вместе с файлом).
3. Фронт опрашивает `GET /api/imports/{id}` каждые 1.5 с (polling) → прогресс-бар и отчёт. Прогресс пишется каждые 20 строк
   прямым DBAL-апдейтом (не зависит от состояния EntityManager).

`discount = (price − закупочная) / price × 100`, округление до 2 знаков; если колонки/значения закупочной цены нет — `0`.

### Формат xlsx (допущение)

Файлы-примеры из задания в архив не попали, поэтому колонки распознаются по заголовкам (регистр не важен, есть синонимы):

| Поле | Заголовки |
|------|-----------|
| external_code *(обяз.)* | `Код`, `Внешний код`, `external_code` |
| name *(обяз.)* | `Наименование`, `Название`, `name` |
| price *(обяз.)* | `Цена продажи`, `Цена`, `price` |
| закупочная цена | `Закупочная цена`, `purchase_price` |
| description | `Описание`, `description` |
| изображения | `Изображения`, `Фото`, `images` — ссылки через перевод строки / запятую / `;` |
| атрибуты | любые колонки `Доп. поле: <Имя>` → `product_attributes(key=<Имя>, value=ячейка)` |

Если в вашем файле заголовки другие — правятся в `ProductXlsxParser::ALIASES` (одно место).

## API

| Метод | Путь | Описание |
|-------|------|----------|
| GET | `/api/health` | health check (проверяет БД) |
| POST | `/api/auth/login` | `{username, password}` → JWT |
| GET | `/api/products?page=&limit=&name=&priceMin=&priceMax=` | серверная пагинация, поиск, диапазон цены |
| GET | `/api/products/{id}` | карточка: поля, `attributes`, `images` |
| POST | `/api/imports` | загрузка xlsx (202, rate limit → 429 + `Retry-After`) |
| GET | `/api/imports/{id}` · `/api/imports/latest` | статус, прогресс, отчёт |

Все, кроме health/login/docs, требуют `Authorization: Bearer <jwt>`. Полная схема — в Swagger.

## Frontend

* Отдельные сервисы `ProductService`, `ImportService`, `AuthService`; модели в `src/app/models/`; `strict: true`.
* NgRx для списка: actions `loadProducts/Success/Failure`, effect (`switchMap` → `ProductService`), selectors
  `selectAllProducts`, `selectProductsLoading`, `selectTotalPages`; компонент использует `store.select()` + `async` pipe.
* `authInterceptor` (Bearer, 401 → `/login`, 5xx → snackbar), `authGuard` (`CanActivateFn`), lazy `loadComponent`.

## Тесты и качество

* `backend/tests`: unit (парсер), integration (CRUD, fixtures, пагинация/фильтры, импорт/upsert/отчёт на SQLite in-memory),
  **API e2e** (весь Slim-стек: JWT, пагинация, загрузка → очередь → «воркер» → статус → карточка, валидация файла, rate limit).
* Сидеры: `App\Fixtures\ProductFixtures` (products + attributes + images), `make seed`.
* `frontend/e2e`: Playwright smoke (login → список → карточка) с мокированием API.
* PHPStan level 5 (`phpstan.neon`), PHP CS Fixer (`.php-cs-fixer.dist.php`), GitHub Actions (`.github/workflows/ci.yml`:
  cs + stan + phpunit, сборка и e2e фронта, сборка docker-образов).

## Решения и ограничения (осознанные)

* **Kafka не реализован** — выбран RabbitMQ (допускается заданием). Смена транспорта — `MESSENGER_TRANSPORT_DSN`.
* **Индикатор прогресса** показан на странице импорта и в карточке товара (последний импорт, polling), WebSocket не используется.
* Единственный пользователь — из env (`ADMIN_USER` / `ADMIN_PASSWORD`); для реального проекта — таблица пользователей и хэши.
* SSRF-защита загрузчика проверяет IP исходного хоста; редиректы (до 3) отдельно не проверяются — для production стоит
  добавить проверку каждого hop или исходящий прокси.
* Если EntityManager закрылся из-за ошибки БД посреди импорта, задача помечается `failed` (длины полей валидируются заранее,
  чтобы такое было редкостью).
* В `app`-сервисе по умолчанию `target: dev` (в образе phpunit/phpstan для `make test`); для продакшена `target: prod`.
* PHPStan настроен на level 5 (требование ≥ 5); поднять до 6+ можно после первого прогона.
