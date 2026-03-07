# MyCompany Google Feed — Посібник користувача

## Зміст

1. [Встановлення та початкове налаштування](#1-встановлення-та-початкове-налаштування)
2. [Налаштування модуля](#2-налаштування-модуля)
3. [Робота з Google Product Categories](#3-робота-з-google-product-categories)
4. [Призначення категорій для категорій Magento](#4-призначення-категорій-для-категорій-magento)
5. [Робота з фідом](#5-робота-з-фідом)
6. [Налаштування мультимагазину](#6-налаштування-мультимагазину)
7. [Автоматична генерація фіду (Cron)](#7-автоматична-генерація-фіду-cron)
8. [Маппінг атрибутів продуктів](#8-маппінг-атрибутів-продуктів)
9. [Вирішення проблем](#9-вирішення-проблем)
10. [Структура XML-фіду](#10-структура-xml-фіду)

---

## 1. Встановлення та початкове налаштування

### Крок 1 — Увімкнення модуля

Виконайте наступні команди всередині Docker-контейнера (або на сервері):

```bash
php bin/magento module:enable MyCompany_GoogleFeed
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:flush
```

### Крок 2 — Імпорт таксономії Google Product Category

Це **обов'язковий крок** перед тим, як ви зможете призначати Google Product Categories до категорій або продуктів.

```bash
php bin/magento mycompany:googlefeed:import-taxonomy
```

Що робить ця команда:
- Сканує всі store view у вашій інсталяції Magento
- Визначає унікальні локалі (наприклад `en_US`, `uk_UA`, `de_DE`)
- Завантажує офіційну таксономію Google Product Category для кожної мови з серверів Google
- Зберігає таксономію в базі даних

Приклад виводу:

```
Starting Google Product Category Taxonomy import...

Found 2 store view(s) to analyze

[1/2] Store: English Store (ID: 1, Code: en)
        Locale: en_US
        Normalized locale: en-US
        Download URL: https://www.google.com/basepages/producttype/taxonomy-with-ids.en-US.txt
        Categories found: 5627
        Saving to database... Done

[2/2] Store: Ukrainian Store (ID: 2, Code: uk)
        Locale: uk_UA
        Normalized locale: uk-UA
        Download URL: https://www.google.com/basepages/producttype/taxonomy-with-ids.uk-UA.txt
        Categories found: 4832
        Saving to database... Done

Import completed!
Processed 2 unique locale(s): en-US, uk-UA
```

> **Повторно запускайте цю команду**, коли додаєте новий store view з іншою мовою, або для оновлення таксономії до актуальної версії від Google.

---

## 2. Налаштування модуля

Перейдіть до: **Stores → Configuration → MyCompany → Google Feed**

### Загальні налаштування

| Налаштування | Опис |
|---|---|
| Feed URL | Поле лише для читання з прямими URL фіду для store view |
| Enable Google Feed | Вмикає/вимикає генерацію фіду для цього store view |
| Feed Title | Заголовок, що відображається в XML-каналі |
| Feed Description | Опис, що відображається в XML-каналі |
| Enable HTTP Basic Authentication | Захищає feed endpoint логіном і паролем |
| Authentication Username | Ім'я користувача для доступу до фіду, якщо автентифікацію увімкнено |
| Authentication Password | Зашифрований пароль для доступу до фіду, якщо автентифікацію увімкнено |

### Налаштування фіду

| Налаштування | Опис | За замовчуванням |
|---|---|---|
| Products Limit | Максимальна кількість продуктів у фіді | 1000 |
| Include Out of Stock | Включати товари не в наявності | Ні |
| Image Size | Розмір зображення продукту в пікселях | 800 |
| Currency | Валюта фіду (порожньо = валюта магазину) | — |
| Default Product Condition | `new` / `refurbished` / `used` | new |

### Фільтри продуктів

| Налаштування | Опис |
|---|---|
| Include Categories | Експортувати лише продукти з обраних категорій |
| Exclude Categories | Виключити продукти з обраних категорій |
| Minimum Price | Пропускати продукти дешевші за вказану ціну |
| Maximum Price | Пропускати продукти дорожчі за вказану ціну |

> **Примітка:** Якщо встановлено і Include, і Exclude, Include є основним фільтром, а Exclude прибирає товари з уже вибраного набору.

### Збереження налаштувань

Після внесення змін натисніть **Save Config** у правому верхньому куті. Потім очистіть кеш:

```bash
php bin/magento cache:flush
```

---

## 3. Робота з Google Product Categories

Google Product Categories — це офіційна таксономія Google з тисячами ієрархічних категорій (наприклад *Електроніка > Засоби зв'язку > Телефонія > Мобільні телефони*). Включення їх у фід покращує видимість продуктів і таргетинг реклами в Google Shopping.

### Як зберігається таксономія

Після запуску `import-taxonomy` категорії зберігаються в таблиці бази даних `mycompany_googlefeed_taxonomy`:
- Числовий ID (наприклад `267`)
- Повний шлях назви (наприклад `Electronics > Communications > Telephony > Mobile Phones`)
- Локаль (наприклад `en-US`, `uk-UA`)

### Пріоритет визначення категорії

Модуль автоматично визначає, яку Google Product Category використовувати для кожного продукту, за таким пріоритетом:

```
1. Атрибут продукту `mycompany_google_product_category`
        ↓ (якщо не встановлено)
2. Google Product Category категорії продукту
        ↓ (якщо не встановлено)
3. Батьківська категорія → ... → коренева категорія
        ↓ (якщо нічого не знайдено)
4. Поле не включається у фід
```

**Приклад:**

```
Електроніка [Google Category: 222 — Electronics]
  └─ Телефони [Google Category: 267 — Mobile Phones]
      └─ Смартфони [Не встановлено → успадковує 267]
          └─ Продукт A [Не встановлено → успадковує 267]
          └─ Продукт B [Встановлено атрибут продукту → використовується власне значення]
```

---

## 4. Призначення категорій для категорій Magento

Це встановлює Google Product Category на рівні категорії. Всі продукти в цій категорії (та дочірніх категоріях без власного призначення) успадкують її.

### Покрокова інструкція

1. Перейдіть до **Catalog → Categories** в адмін-панелі
2. Оберіть потрібну категорію в лівому дереві
3. Прокрутіть вниз до секції **Display Settings** і розгорніть її
4. Знайдіть поле **Google Product Category**
5. Натисніть кнопку **Select Category** — праворуч відкриється панель із повним деревом таксономії Google
6. Скористайтесь **рядком пошуку** вгорі для швидкого знаходження категорії (наприклад, введіть `mobile`)
7. Натисніть **Select** поруч з потрібною категорією
8. У полі з'явиться назва категорії; ID зберігається автоматично
9. Натисніть **Save** для збереження категорії Magento

### Робота з вибірником категорій

- **Пошук**: введіть будь-яке ключове слово в рядок пошуку — результати підсвічуються та фільтруються в реальному часі
- **Перегляд**: натисніть стрілку біля батьківської категорії, щоб розгорнути дочірні
- **Вибір**: натисніть кнопку **Select** поруч з будь-якою категорією
- **Очищення**: натисніть кнопку **Clear** поруч з полем, щоб видалити призначення

---

## 5. Робота з фідом

### Перегляд фіду (пряме посилання)

Доступ до живого фіду для будь-якого store view:

```
https://yourstore.com/googlefeed/feed/index
https://yourstore.com/googlefeed/feed/index?store=en
https://yourstore.com/googlefeed/feed/index?store=uk
```

Замініть `en` або `uk` на код свого магазину.

### Генерація та збереження файлів фіду

1. Перейдіть до **Marketing → Google Feed → Feed Management**
2. Ви побачите список усіх store view та їхній статус
3. Натисніть **Generate & Save Feed Files** — це створить XML-файли для всіх увімкнених магазинів
4. Файли зберігаються у `pub/media/googlefeed/` з описовими іменами:
   - `feed_english_store_en_en.xml`
   - `feed_ukrainian_store_uk_uk.xml`
   - Формат: `feed_{назва_магазину}_{код_магазину}_{код_мови}.xml`

### Додавання фіду до Google Merchant Center

1. Увійдіть до [Google Merchant Center](https://merchants.google.com)
2. Перейдіть до **Products → Feeds**
3. Натисніть **+** для додавання нового фіду
4. Вкажіть країну, мову та назву фіду
5. Оберіть **Scheduled fetch** або **Upload**
6. Для Scheduled fetch введіть URL збереженого файлу фіду:
   ```
   https://yourstore.com/media/googlefeed/feed_english_store_en_en.xml
   ```
7. Встановіть частоту завантаження (рекомендується щодня)
8. Збережіть і дочекайтесь обробки Google

### Автентифікація фіду

Якщо увімкнути **HTTP Basic Authentication** у налаштуваннях модуля, прямий URL фіду вимагатиме облікові дані.

- Використовуйте це лише якщо отримувач фіду підтримує basic auth
- Якщо Google Merchant Center повертає помилки автентифікації, вимкніть захист фіду і перевірте ще раз

---

## 6. Налаштування мультимагазину

Кожен store view отримує власний фід з локалізованим вмістом (назви продуктів, описи, URL, ціни).

### Покрокова інструкція для мультимагазину

1. **Імпортуйте таксономію** — запускається один раз, автоматично охоплює всі локалі:
   ```bash
   php bin/magento mycompany:googlefeed:import-taxonomy
   ```

2. **Налаштуйте кожен store view окремо:**
   - У лівому верхньому куті адмін-панелі переключіться на потрібний store view
   - Перейдіть до **Stores → Configuration → MyCompany → Google Feed**
   - Зніміть прапорці `Use Website` / `Use Default` для налаштувань, які хочете змінити
   - Увімкніть фід, встановіть фільтри, специфічні для цього магазину
   - Збережіть

3. **Призначайте Google Product Categories** у локалі кожного магазину:
   - При редагуванні категорії або продукту спочатку переключіться на потрібний store view
   - Вибірник показує категорії мовою цього store view

4. **Генеруйте фіди:**
   - Через cron (рекомендовано): налаштуйте в секції **Automatic Generation**
   - Через адмін: **Marketing → Google Feed → Feed Management → Generate & Save Feed Files**

5. **Зареєструйте кожен фід у Google Merchant Center** з правильною мовою/країною:
   - Англійська: `https://yourstore.com/media/googlefeed/feed_english_store_en_en.xml`
   - Українська: `https://yourstore.com/media/googlefeed/feed_ukrainian_store_uk_uk.xml`

---

## 7. Автоматична генерація фіду (Cron)

Налаштування: **Stores → Configuration → MyCompany → Google Feed → Automatic Generation**

| Налаштування | Опис |
|---|---|
| Enable Automatic Generation | Вмикає/вимикає планову генерацію |
| Generation Frequency | `daily` / `twice daily` / `every 6h` / `hourly` / `weekly` |
| Generation Time | Час запуску у форматі `HH:MM` (наприклад `03:00`) |
| Generate Feeds for Stores | Оберіть конкретні магазини або залиште порожнім для всіх |
| Generated Feed Files | Показує згенеровані файли, збережені у `pub/media/googlefeed/` |

### Як перевірити роботу cron

```bash
php bin/magento cron:run
```

Потім перевірте `var/log/system.log` на наявність записів, пов'язаних з `GoogleFeed`.

---

## 8. Маппінг атрибутів продуктів

Налаштування: **Stores → Configuration → MyCompany → Google Feed → Product Attributes Mapping**

Всі поля використовують **вибір зі списку** — ви вибираєте з існуючих атрибутів продуктів Magento.

| Поле фіду | Налаштування маппінгу | XML-тег у фіді |
|---|---|---|
| Бренд | Brand Attribute | `g:brand` |
| GTIN/UPC/EAN | GTIN Attribute | `g:gtin` |
| MPN | MPN Attribute | `g:mpn` |
| Обробка відсутнього GTIN | Set Identifier Exists to No When GTIN Is Missing | `g:identifier_exists` |
| Стан | Condition Attribute | `g:condition` |
| Колір | Color Attribute | `g:color` |
| Розмір | Size Attribute | `g:size` |
| Стать | Gender Attribute | `g:gender` |
| Вікова група | Age Group Attribute | `g:age_group` |

### Як налаштувати маппінг атрибута

1. Перейдіть до **Stores → Configuration → MyCompany → Google Feed → Product Attributes Mapping**
2. Для кожного поля відкрийте список і виберіть відповідний атрибут з вашого каталогу
3. Якщо атрибут не існує — спочатку створіть його в **Stores → Attributes → Product**
4. Збережіть конфігурацію та очистіть кеш

Коли **Set Identifier Exists to No When GTIN Is Missing** увімкнено, товари без GTIN експортуються з `g:identifier_exists`, встановленим у `no`.

---

## 9. Вирішення проблем

### Фід порожній
- Перевірте, що **Enable Google Feed** встановлено в **Yes** для цього store view
- Переконайтесь, що продукти **увімкнені** та **видимі** в каталозі
- Перевірте фільтр **Include Categories** — якщо його не налаштовано, модуль за замовчуванням не експортує товари
- Перевірте фільтри **Minimum/Maximum Price** — можливо вони виключають всі продукти

### Google Product Category не відображається у формі категорії
```bash
php bin/magento cache:flush
php bin/magento mycompany:googlefeed:import-taxonomy
```

### Вибірник категорій не показує результати / порожній
- Таксономія для локалі цього магазину не була імпортована
- Запустіть: `php bin/magento mycompany:googlefeed:import-taxonomy`
- Перевірте базу даних: `SELECT COUNT(*) FROM mycompany_googlefeed_taxonomy;`

### Збережена Google Product Category не відображається при повторному відкритті форми категорії
- Очистіть кеш: `php bin/magento cache:flush`

### Фід показує неправильну мову
- Переконайтесь, що ви звертаєтесь до фіду з правильним кодом магазину: `?store=uk`
- Продукти повинні мати локалізований вміст, збережений для конкретного store view
- Перевірте, що `Use Default Value` знятий для назви/опису продукту в цьому store view

### Файли фіду не генеруються
- Перевірте, що директорія `pub/media/googlefeed/` існує і доступна для запису:
  ```bash
  chmod 775 pub/media/googlefeed/
  ```
- Переконайтесь, що фід увімкнено для потрібного store view
- Переконайтесь, що **Enable Automatic Generation** встановлено в **Yes**
- Перевірте роботу cron: `php bin/magento cron:run`
- Перегляньте логи: `var/log/system.log`

### Фід повертає помилку автентифікації
- Перевірте, чи увімкнено **Enable HTTP Basic Authentication** у конфігурації
- Перевірте ім'я користувача та пароль
- Якщо Google Merchant Center не може отримати фід, вимкніть автентифікацію і протестуйте ще раз

### Проблеми з продуктивністю при великому каталозі
- Зменшіть **Products Limit** у конфігурації
- Увімкніть кеш фіду: `php bin/magento cache:enable googlefeed`
- Використовуйте генерацію через cron (запускається в години низького навантаження) замість ручної
- Використовуйте фільтри категорій для експорту лише релевантних продуктів

---

## 10. Структура XML-фіду

Модуль генерує XML у форматі Google Shopping з усіма обов'язковими та рекомендованими полями:

### Обов'язкові поля

| XML-тег | Опис | Джерело |
|---|---|---|
| `g:id` | Унікальний ідентифікатор | SKU продукту |
| `g:title` | Назва продукту | Назва продукту в store view |
| `g:description` | Опис продукту | Короткий/повний опис |
| `g:link` | URL продукту | URL продукту в store view |
| `g:image_link` | URL основного зображення | Головне зображення продукту |
| `g:price` | Ціна з валютою | Ціна в store view |
| `g:availability` | Наявність | Статус складу |
| `g:condition` | Стан товару | Атрибут або за замовчуванням |

### Рекомендовані поля

| XML-тег | Опис | Джерело |
|---|---|---|
| `g:brand` | Бренд | Маппінг атрибута |
| `g:gtin` | GTIN/UPC/EAN | Маппінг атрибута |
| `g:mpn` | Номер виробника | Маппінг атрибута |
| `g:identifier_exists` | Прапорець наявності ідентифікатора | Налаштування обробки GTIN |
| `g:google_product_category` | Google категорія | Продукт → Категорія → Батьківська категорія |
| `g:product_type` | Тип продукту | Шлях категорії в магазині |
| `g:additional_image_link` | Додаткові зображення | Галерея продукту |

### Поля для одягу

| XML-тег | Опис |
|---|---|
| `g:color` | Колір |
| `g:size` | Розмір |
| `g:gender` | Стать |
| `g:age_group` | Вікова група |
