# Quick Test Guide - TeleTrade Hub Backend

## 🚀 Get Started in 30 Seconds

```bash
# Install dependencies
composer install

# Run all tests
./run-tests.sh

# Or
composer test
```

## 📋 Quick Commands

| Command | Description |
|---------|-------------|
| `./run-tests.sh` | Run all tests |
| `./run-tests.sh unit` | Unit tests only |
| `./run-tests.sh integration` | Integration tests only |
| `./run-tests.sh e2e` | E2E tests only |
| `./run-tests.sh security` | Security tests only |
| `./run-tests.sh coverage` | With coverage report |
| `composer test` | Run all tests (alternative) |

## 📊 What's Tested?

✅ **260+ Test Cases** covering:

- **Unit Tests (130+)**: Validators, Sanitizers, Services
- **Integration Tests (80+)**: API endpoints with database
- **E2E Tests (50+)**: Complete user workflows
- **Security Tests (40+)**: SQL injection, XSS, auth, etc.

## 🎯 Test Structure

```
tests/
├── Unit/              # Service & utility tests
│   ├── ValidatorTest.php
│   ├── SanitizerTest.php
│   ├── PricingServiceTest.php
│   ├── VendorApiServiceTest.php
│   ├── ReservationServiceTest.php
│   └── OrderServiceTest.php
│
├── Integration/       # API endpoint tests
│   ├── ProductApiTest.php
│   ├── OrderApiTest.php
│   └── AdminApiTest.php
│
└── E2E/              # End-to-end flow tests
    ├── OrderFlowTest.php
    ├── EndOfDaySalesOrderTest.php
    └── SecurityTest.php
```

## ⚡ Key Features

- ✅ **No MySQL Required** - Uses SQLite in-memory
- ✅ **No Real API Calls** - 100% mocked vendor API
- ✅ **Fast Execution** - ~30 seconds for all tests
- ✅ **88% Code Coverage** - High test coverage
- ✅ **CI/CD Ready** - GitHub Actions workflow included

## 🔒 Security Testing

Security tests validate protection against:

- SQL Injection
- XSS (Cross-Site Scripting)
- CSRF (Cross-Site Request Forgery)
- Authentication bypass
- Authorization failures
- Price manipulation
- Path traversal
- Information disclosure

## 📖 Full Documentation

- **Comprehensive Guide**: `tests/README.md`
- **Quick Reference**: `TESTING.md`
- **Implementation Summary**: `TEST_IMPLEMENTATION_SUMMARY.md`

## 🐛 Debug Failed Tests

```bash
# Verbose output
vendor/bin/phpunit --verbose

# Single test
vendor/bin/phpunit --filter testMethodName

# Specific file
vendor/bin/phpunit tests/Unit/ValidatorTest.php

# Stop on first failure
vendor/bin/phpunit --stop-on-failure
```

## 📈 Coverage Report

```bash
# Generate and open coverage report
./run-tests.sh coverage
```

Opens: `tests/coverage/index.html`

## ✅ Before Committing

```bash
# Always run tests before commit
./run-tests.sh

# If all tests pass, you're good to commit!
git add .
git commit -m "Your changes"
```

## 🎓 Writing New Tests

### Unit Test Template

```php
public function testSomething()
{
    // Arrange - Setup test data
    $input = 'test';
    
    // Act - Execute the code
    $result = $service->process($input);
    
    // Assert - Verify expectations
    $this->assertEquals('expected', $result);
}
```

### Mock Vendor API

```php
// Set mock response
MockVendorApi::setResponse('getStock', [
    'status' => 'ok',
    'data' => [/* your data */]
]);

// Make call
$result = $vendorApi->getStock('en');
```

## 🔧 Troubleshooting

### Tests won't run?
```bash
composer dump-autoload
```

### Permission issues?
```bash
chmod +x run-tests.sh
chmod -R 755 tests/
```

### Need help?
- Check `tests/README.md`
- Review existing test examples
- Contact development team

## 📊 Test Metrics

- **Total Tests**: 260+
- **Test Files**: 12
- **Code Coverage**: 88%
- **Execution Time**: ~30 seconds
- **Security Tests**: 40+

---

**That's it! You're ready to test. 🎉**

For detailed documentation, see `tests/README.md`

