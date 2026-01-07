# Multi-Language Support Documentation

## Overview

The TeleTrade Hub backend now supports 11 languages for product catalog and category information. The system fetches localized data from the vendor API and stores it in language-specific database columns for fast retrieval.

## Supported Languages

| ID | Code | Language   |
|----|------|------------|
| 0  | en   | Default (English) |
| 1  | en   | English    |
| 3  | de   | German     |
| 4  | fr   | French     |
| 5  | es   | Spanish    |
| 6  | ru   | Russian    |
| 7  | it   | Italian    |
| 8  | tr   | Turkish    |
| 9  | ro   | Romanian   |
| 10 | sk   | Slovakian  |
| 11 | pl   | Polish     |

## Architecture

### How It Works

1. **Vendor API Integration**: The vendor API (TRIEL) accepts a `lang_id` parameter and returns product/category data already localized in that language.

2. **Database Storage**: Each translatable field has language-specific columns:
   - Products: `name_en`, `name_de`, `name_fr`, etc.
   - Products: `description_en`, `description_de`, `description_fr`, etc.
   - Categories: `name_en`, `name_de`, `name_fr`, etc.
   - Brands: `description_en`, `description_de`, `description_fr`, etc.

3. **Product Sync**: The sync process calls the vendor API multiple times (once per language) and stores each translation:
   - First syncs in English (base language) to create/update products
   - Then syncs in other languages to populate translation columns

4. **API Responses**: The API automatically serves the appropriate language based on:
   - `lang` or `language` query parameter (code or ID)
   - `Accept-Language` HTTP header
   - Falls back to English if not specified

### Language Fallback

The system uses a fallback chain to ensure content is always displayed:
1. Requested language (e.g., French)
2. English (default fallback)
3. Base field (if no translations available)

## Database Schema

### Migration Script

Run the migration to add language columns:

```bash
mysql -u your_user -p your_database < database/add_languages_migration.sql
```

This adds:
- Language columns to `products` table (name_*, description_*)
- Language columns to `categories` table (name_*)
- Language columns to `brands` table (description_*)
- Updates fulltext indexes to include all languages
- Creates a `languages` reference table
- Updates the `product_list_view` with all language fields

## API Usage

### 1. Get Supported Languages

```http
GET /languages
```

**Response:**
```json
{
  "success": true,
  "data": {
    "languages": [
      {"id": 1, "code": "en", "name": "English"},
      {"id": 3, "code": "de", "name": "German"},
      {"id": 4, "code": "fr", "name": "French"},
      ...
    ],
    "current": {
      "id": 1,
      "code": "en",
      "name": "English"
    }
  }
}
```

### 2. Request Products in Specific Language

**Using Language Code:**
```http
GET /products?lang=de
GET /products?lang=fr
GET /products?lang=es
```

**Using Language ID:**
```http
GET /products?lang=3   # German
GET /products?lang=4   # French
GET /products?lang=5   # Spanish
```

**Using Accept-Language Header:**
```http
GET /products
Accept-Language: de-DE,de;q=0.9,en;q=0.8
```

**Response:**
```json
{
  "success": true,
  "data": {
    "products": [
      {
        "id": 1,
        "name": "iPhone 15 Pro 256 Go", // French name
        "description": "...",
        "price": 1199.99,
        "language": "fr"
      }
    ],
    "pagination": {...},
    "language": {
      "id": 4,
      "code": "fr",
      "name": "French"
    }
  }
}
```

### 3. All Endpoints Support Language Parameter

- `GET /products?lang=de` - Get products in German
- `GET /products/{id}?lang=fr` - Get specific product in French
- `GET /products/search?q=phone&lang=es` - Search in Spanish
- `GET /categories?lang=it` - Get categories in Italian
- `GET /brands?lang=ru` - Get brands in Russian

## Product Synchronization

### Sync All Languages

To sync products in all supported languages:

```http
GET /sync-products.php?key=SECURE_KEY_12345
```

This will:
1. Sync products in English (base language)
2. Sync translations for all other languages (German, French, Spanish, etc.)
3. Display real-time progress in the browser
4. Show summary with per-language statistics

### Sync Specific Languages

To sync only specific languages:

```http
GET /sync-products.php?key=SECURE_KEY_12345&languages=1,3,4
```

This syncs only English (1), German (3), and French (4).

### Sync Performance

- **Single language sync**: ~30-60 seconds
- **All languages sync**: ~5-8 minutes (depends on product count)
- Syncs run sequentially to avoid API rate limits

## Code Examples

### 1. Using Language Utility

```php
require_once 'app/Utils/Language.php';

// Convert language ID to code
$code = Language::getCodeFromId(3); // Returns 'de'

// Convert language code to ID
$id = Language::getIdFromCode('fr'); // Returns 4

// Validate language
$isValid = Language::isValidCode('es'); // Returns true

// Get all languages
$languages = Language::getAllLanguages();

// Normalize input (handles both ID and code)
$normalized = Language::normalize(3);      // Returns 'de'
$normalized = Language::normalize('de');   // Returns 'de'
$normalized = Language::normalize('invalid'); // Returns 'en' (default)
```

### 2. Using Language Middleware

```php
require_once 'app/Middlewares/LanguageMiddleware.php';

// Initialize language from request
$lang = LanguageMiddleware::handle();

// Get current language
$currentLang = LanguageMiddleware::getCurrentLanguage();

// Validate language parameter
$error = LanguageMiddleware::validate($_GET['lang']);
if ($error) {
    Response::error($error['message'], 400);
}

// Get language info for response
$langInfo = LanguageMiddleware::getLanguageInfo();
```

### 3. Model Usage with Language

```php
// Get products in specific language
$products = $productModel->getAll($filters, $page, $limit, 'de');

// Get category in specific language
$category = $categoryModel->getById($id, 'fr');

// Get brand in specific language
$brand = $brandModel->getById($id, 'es');
```

## Frontend Integration

### JavaScript Example

```javascript
// Get user's preferred language
const userLang = navigator.language.split('-')[0]; // 'en', 'de', 'fr', etc.

// Fetch products in user's language
async function getProducts(lang = 'en') {
  const response = await fetch(
    `https://api.vs-mjrinfotech.com/products?lang=${lang}`
  );
  const data = await response.json();
  return data;
}

// Get supported languages
async function getLanguages() {
  const response = await fetch(
    'https://api.vs-mjrinfotech.com/languages'
  );
  const data = await response.json();
  return data.data.languages;
}

// Usage
const products = await getProducts('de'); // Get German products
const languages = await getLanguages();   // Get all supported languages
```

### React Example

```jsx
import { useState, useEffect } from 'react';

function ProductList() {
  const [products, setProducts] = useState([]);
  const [language, setLanguage] = useState('en');

  useEffect(() => {
    fetch(`https://api.vs-mjrinfotech.com/products?lang=${language}`)
      .then(res => res.json())
      .then(data => setProducts(data.data.products));
  }, [language]);

  return (
    <div>
      <select value={language} onChange={(e) => setLanguage(e.target.value)}>
        <option value="en">English</option>
        <option value="de">German</option>
        <option value="fr">French</option>
        <option value="es">Spanish</option>
        <option value="it">Italian</option>
      </select>
      
      {products.map(product => (
        <div key={product.id}>
          <h3>{product.name}</h3>
          <p>{product.description}</p>
        </div>
      ))}
    </div>
  );
}
```

## Admin Panel Integration

### Sync Interface

Add to your admin panel:

```html
<button onclick="syncAllLanguages()">Sync All Languages</button>
<button onclick="syncLanguage('de')">Sync German Only</button>

<script>
async function syncAllLanguages() {
  window.open(
    'https://api.vs-mjrinfotech.com/sync-products.php?key=SECURE_KEY_12345',
    '_blank'
  );
}

async function syncLanguage(langId) {
  window.open(
    `https://api.vs-mjrinfotech.com/sync-products.php?key=SECURE_KEY_12345&languages=${langId}`,
    '_blank'
  );
}
</script>
```

## Testing

### Test Language Support

```bash
# Test English
curl "https://api.vs-mjrinfotech.com/products?lang=en"

# Test German
curl "https://api.vs-mjrinfotech.com/products?lang=de"

# Test French with ID
curl "https://api.vs-mjrinfotech.com/products?lang=4"

# Test with Accept-Language header
curl -H "Accept-Language: de-DE,de;q=0.9" \
  "https://api.vs-mjrinfotech.com/products"

# Get supported languages
curl "https://api.vs-mjrinfotech.com/languages"
```

### Test Product Sync

```bash
# Sync all languages
curl "https://api.vs-mjrinfotech.com/sync-products.php?key=SECURE_KEY_12345"

# Sync specific languages
curl "https://api.vs-mjrinfotech.com/sync-products.php?key=SECURE_KEY_12345&languages=1,3,4"
```

## Performance Considerations

### Caching

Consider implementing caching for language-specific responses:

```php
// Example: Cache products per language for 1 hour
$cacheKey = "products_{$lang}_{$page}_{$limit}";
$cachedData = $cache->get($cacheKey);

if ($cachedData) {
    return $cachedData;
}

$products = $productModel->getAll($filters, $page, $limit, $lang);
$cache->set($cacheKey, $products, 3600);
```

### Database Indexing

The migration script already adds fulltext indexes for all language columns:

```sql
ALTER TABLE products ADD FULLTEXT KEY search_name (
    name, name_en, name_de, name_sk, name_fr, name_es, 
    name_ru, name_it, name_tr, name_ro, name_pl
);
```

### Sync Scheduling

Set up automatic syncs using cron:

```bash
# Sync all languages daily at 2 AM
0 2 * * * curl -s "https://api.vs-mjrinfotech.com/sync-products.php?key=SECURE_KEY_12345" > /dev/null
```

## Troubleshooting

### Common Issues

1. **Missing Translations**
   - Check if the language was included in the sync
   - Verify vendor API returns data for that language ID
   - Check database columns exist for that language

2. **Wrong Language Returned**
   - Verify the `lang` parameter is being sent
   - Check language code/ID mapping
   - Verify LanguageMiddleware is initialized

3. **Slow Sync**
   - Normal for first full sync (5-8 minutes for all languages)
   - Check vendor API response times
   - Consider syncing only changed languages

4. **Database Errors**
   - Run the migration script if columns are missing
   - Check column names match language codes
   - Verify fulltext indexes were created

## Future Enhancements

- [ ] Add language-specific SEO metadata
- [ ] Support language-specific product descriptions from vendor
- [ ] Add translation management interface in admin panel
- [ ] Implement incremental sync per language
- [ ] Add language-specific pricing if needed
- [ ] Cache layer for frequently accessed languages

## Support

For issues or questions, refer to:
- API Documentation: `API_DOCUMENTATION.md`
- Vendor API Docs: https://documenter.getpostman.com/view/2725979/2sB3HksMQt


