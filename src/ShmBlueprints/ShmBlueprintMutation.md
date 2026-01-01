## ShmBlueprintMutation — описание параметров и методов

`ShmBlueprintMutation` — вспомогательный класс для удобного создания RPC‑мутаций, которые работают с MongoDB через `StructureType`.

Ниже описаны основные методы/параметры, которые вы можете настраивать.

---

### Конструктор

- **`__construct(StructureType $structure)`**
  - **$structure**: схема (коллекция), над которой выполняются операции (insert / update / delete / move и т.д.).

---

### Режим работы с одной строкой

- **`oneRow(bool $oneRow): static`**  
  Управляет тем, работает ли мутация с **одной** найденной записью или с произвольной:
  - `true` — мутация сама через `pipeline` находит одну запись, `_id` берётся из результата.
  - `false` (по умолчанию) — ожидается `_id` в аргументах мутации.

Используется для безопасных «одиночных» операций, где `_id` нельзя передавать напрямую.

---

### Возможность удаления

- **`delete(bool $delete): static`**  
  Включает/отключает поддержку удаления:
  - `true` (по умолчанию) — добавляется аргумент `delete` и можно удалять запись по `_id`.
  - `false` — удалить через эту мутацию нельзя.

> Применяется только когда `oneRow(false)`, т.к. для `oneRow(true)` удаление не допускается логикой.

---

### Подготовка аргументов до основной логики

- **`prepareArgs(callable $callback): static`**  
  Регистрирует колбэк, который вызывается **перед нормализацией аргументов** и основной логикой обновления.

  Сигнатура колбэка:

  ```php
  function (array $args): ?array
  ```

  - На вход: исходный массив `$args`.
  - На выход: можно вернуть **новый массив args** (он заменит старый) или `null`, чтобы оставить как есть.

  Вызывается из:

  - `executeUpdateOperation()` через `callPrepareArgs()`.

---

### Хук перед выполнением мутации

- **`beforeResolve(callable $callback): static`**  
  Колбэк, который вызывается **самым первым** внутри `resolve`, до работы с БД.

  Сигнатура:

  ```php
  function (mixed $root, array &$args): void
  ```

  - Можно менять `$args` по ссылке.
  - Удобно для авторизации/валидации на уровне RPC.

---

### Хук после вставки/обновления (afterSave)

- **`afterSave(callable $callback): static`**  
  Единый колбэк, который вызывается:

  - **после успешной вставки** документа;
  - **после успешного обновления** документа.

  Сигнатура:

  ```php
  function (mixed $id, array $args, ShmBlueprintMutation $mutation): void
  ```

  - **`$id`** — `_id` вставленного или обновлённого документа  
    (объект `ObjectId` или строка, в зависимости от места использования).
  - **`$args`** — исходные аргументы мутации (до нормализации).
  - **`$mutation`** — сам объект `ShmBlueprintMutation` (можно дергать дополнительные методы).

  Вызовы:

  - В `handleInsertOperation()` — после `insertOne`, до возврата `findOne`.
  - В `executeUpdateOperation()` — после всех `update/delete` операций и до возврата обновлённой записи.

---

### Настройка pipeline (ограничения доступа/фильтрация)

- **`pipeline(array|callable|null $pipeline): static`**  
  Задаёт MongoDB aggregation pipeline, который будет:

  - использоваться для поиска записей;
  - проверять права доступа.

  Варианты:

  - **`array`** — обычный MongoDB pipeline:
    ```php
    $mutation->pipeline([
        ['$match' => ['ownerId' => $userId]],
    ]);
    ```
  - **`callable`** — функция, возвращающая pipeline:
    ```php
    $mutation->pipeline(function () use ($userId) {
        return [
            ['$match' => ['ownerId' => $userId]],
        ];
    });
    ```
  - **`null`** — pipeline не используется.

  Внутри:

  - хранится как функция (`$pipelineFunction`);
  - при вызове `getPipeline()`:
    - выполняется колбэк;
    - вызывается `mDB::validatePipeline($pipeline)`.

---

### Структура аргументов мутации

Метод **`make()`** собирает описание RPC‑мутации:

```php
[
    "type"  => StructureType,      // структура результата
    "args"  => StructureType,      // структура аргументов
    "resolve" => callable,         // основная логика
]
```

Аргументы строятся в **`buildMutationArgs()`**:

- **`_id`**

  - Добавляется, если `oneRow(false)` — идентификатор записи для обновления/удаления.

- **`delete`**

  - Добавляется, если `delete(true)` и `oneRow(false)` — флаг удаления записи.

- **`fields`**

  - Структура редактируемых полей, берётся из `$this->structure->editableThis()`.
  - Используется при вставке и обновлении.

- **`unset`**

  - Enum по ключам полей, которые можно удалить (по `editable`).

- **`addToSet` / `pull`**

  - Структуры для операций с массивами (тип `IDs`):
    - `addToSet` — добавить элементы в массив (без дублей).
    - `pull` — удалить элементы из массива.

- **`move`**
  - Структура для ручной сортировки (`manualSort = true`):
    - `aboveId`
    - `belowId`

---

### Вспомогательные методы

- **`flattenObject(mixed $data, string $parentKey = '', array &$result = []): array`**  
  Рекурсивно разворачивает вложенный объект/массив в flat‑вид с dot‑нотацией:
  - Специально обрабатывает `ObjectId` и массивы `ObjectId[]`.

---

### Основные сценарии работы

1. **Вставка (insert)**

   - Если в аргументах **нет `_id`**, вызывается `handleInsertOperation()`:
     - берётся `fields`;
     - нормализуется;
     - `insertOne`;
     - вызывается `afterSave($insertedId, $args, $this)`;
     - возвращается `findOne` по `_id`.

2. **Обновление (update)**

   - Если `_id` есть:
     - вызывается `executeUpdateOperation()`:
       - `prepareArgs` (если есть);
       - нормализация `$args`;
       - проверка доступа через pipeline;
       - `delete` (если флаг установлен);
       - `$set` по `fields`, `addToSet`, `pull`, `move`;
       - вызывается `afterSave($_id, $originalArgs, $this)`;
       - возвращается обновлённый документ.

3. **Режим `oneRow(true)`**
   - Через `pipeline` выбирается одна запись;
   - `_id` подставляется автоматически;
   - далее выполняется логика обновления как обычно (без опции delete).

---

### Пример использования с afterSave

```php
$mutation = (new ShmBlueprintMutation($structure))
    ->delete(true)
    ->afterSave(function ($id, array $args, ShmBlueprintMutation $mutation) {
        // Логика после вставки/обновления
        // Например: логирование, пересчёт статистики, очередь задач и т.п.
    });
```
