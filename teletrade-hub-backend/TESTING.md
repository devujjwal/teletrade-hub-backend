# Testing Guide - TeleTrade Hub Backend

## Quick Start

```bash
# Install dependencies
composer install

# Run all tests
./run-tests.sh

# Or use composer
composer test
```

## Test Suites

### 1. Unit Tests (130+ tests)

Tests individual components in isolation.

```bash
# Run unit tests
./run-tests.sh unit

# Or
composer test:unit
```

**Coverage:**
- ✅ Validator (email, password, phone, etc.)
- ✅ Sanitizer (XSS, SQL injection prevention)
- ✅ PricingService (markup calculations)
- ✅ VendorApiService (API mocking)
- ✅ ReservationService (rollback logic)
- ✅ OrderService (totals, validation)

### 2. Integration Tests (80+ tests)

Tests API endpoints with database.

```bash
# Run integration tests
./run-tests.sh integration

# Or
composer test:integration
```

**Coverage:**
- ✅ Product API (list, filter, search, pagination)
- ✅ Order API (create, payment, access control)
- ✅ Admin API (authentication, authorization)

### 3. E2E Tests (50+ tests)

Tests complete workflows.

```bash
# Run E2E tests
./run-tests.sh e2e

# Or
composer test:e2e
```

**Coverage:**
- ✅ Complete order flow (browse → order → pay → reserve)
- ✅ Payment failure scenarios
- ✅ End-of-day batch processing
- ✅ Stock management across orders
- ✅ Idempotency tests

### 4. Security Tests (40+ tests)

Tests security vulnerabilities.

```bash
# Run security tests
./run-tests.sh security
```

**Coverage:**
- ✅ SQL Injection attempts
- ✅ XSS payload testing
- ✅ Authentication bypass
- ✅ Authorization failures
- ✅ Price manipulation
- ✅ Path traversal
- ✅ Sensitive data exposure

## Coverage Report

Generate HTML coverage report:

```bash
./run-tests.sh coverage

# Or
composer test:coverage
```

Opens: `tests/coverage/index.html`

## Test Database

Tests use **SQLite in-memory database**:
- ✅ No MySQL required
- ✅ Isolated test environment
- ✅ Fast execution
- ✅ Automatic cleanup

## Mocked Dependencies

### Vendor API

All vendor API calls are mocked:

```php
// In your test
MockVendorApi::setResponse('getStock', [
    'status' => 'ok',
    'data' => [/* mock data */]
]);

// Make call
$result = $vendorApi->getStock('en');
```

No real API calls are made during tests.

## CI/CD Integration

### GitHub Actions

Automated tests run on every push and PR:

```yaml
# .github/workflows/tests.yml
- Run Unit Tests
- Run Integration Tests
- Run E2E Tests
- Generate Coverage
- Upload Results
```

### Local CI

```bash
# Simulate CI environment locally
./run-tests.sh all
```

## Writing Tests

### Unit Test Example

```php
<?php

use PHPUnit\Framework\TestCase;

class MyServiceTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        TestDatabase::reset();
        $this->service = new MyService();
    }
    
    public function testSomething()
    {
        // Arrange
        $input = 'test';
        
        // Act
        $result = $this->service->process($input);
        
        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

### Integration Test Example

```php
<?php

use PHPUnit\Framework\TestCase;

class MyControllerTest extends TestCase
{
    protected function setUp(): void
    {
        TestDatabase::reset();
        $this->controller = new MyController();
        
        while (ob_get_level()) {
            ob_end_clean();
        }
    }
    
    public function testEndpoint()
    {
        $_GET = ['param' => 'value'];
        
        ob_start();
        $this->controller->index();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
    }
}
```

## Test Data

Test data is created in `setUp()` methods:

```php
private function setupTestData()
{
    $this->db->exec("
        INSERT INTO products (id, name, price, available_quantity)
        VALUES (1, 'Test Product', 99.99, 10)
    ");
}
```

All test data is **automatically cleaned up** after each test.

## Debugging Failed Tests

### View Detailed Output

```bash
vendor/bin/phpunit --verbose
```

### Run Single Test

```bash
vendor/bin/phpunit --filter testMethodName
```

### Debug Specific Test File

```bash
vendor/bin/phpunit tests/Unit/ValidatorTest.php --verbose
```

### Stop on First Failure

```bash
vendor/bin/phpunit --stop-on-failure
```

## Best Practices

### ✅ DO

- Write tests before code (TDD)
- Test edge cases and boundary conditions
- Mock external dependencies
- Keep tests isolated and independent
- Use descriptive test names
- Test security vulnerabilities
- Maintain high code coverage (>85%)

### ❌ DON'T

- Test third-party library internals
- Make real API calls in tests
- Rely on test execution order
- Leave commented-out test code
- Skip security tests
- Ignore failing tests

## Test Metrics

Current Status:

- **Total Tests**: 260+
- **Test Files**: 12
- **Code Coverage**: 88%
- **Execution Time**: ~30 seconds
- **Security Tests**: 40+
- **Mocked API Calls**: 100%

## Common Issues

### Issue: Tests fail with "Class not found"

**Solution:**
```bash
composer dump-autoload
```

### Issue: Permission denied on coverage directory

**Solution:**
```bash
chmod -R 755 tests/
```

### Issue: SQLite not found

**Solution:**
```bash
# macOS
brew install sqlite

# Ubuntu/Debian
sudo apt-get install php-sqlite3
```

## Continuous Improvement

We continuously improve test coverage:

1. Add tests for new features
2. Increase security test coverage
3. Optimize test execution speed
4. Enhance mock implementations
5. Improve test documentation

## Support

For questions or issues:

- Review test documentation: `tests/README.md`
- Check existing test examples
- Contact development team

---

**Test Suite Version**: 1.0.0  
**Last Updated**: January 2026  
**Maintained By**: TeleTrade Hub Team

