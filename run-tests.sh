#!/bin/bash

###############################################################################
# TeleTrade Hub Backend - Test Runner Script
# 
# Usage:
#   ./run-tests.sh              # Run all tests
#   ./run-tests.sh unit         # Run unit tests only
#   ./run-tests.sh integration  # Run integration tests only
#   ./run-tests.sh e2e          # Run E2E tests only
#   ./run-tests.sh coverage     # Run with coverage report
###############################################################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
print_header() {
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    print_error "Composer not found. Please install Composer first."
    exit 1
fi

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    print_warning "Dependencies not installed. Running composer install..."
    composer install
fi

# Create results directory if it doesn't exist
mkdir -p tests/results

# Parse command line arguments
TEST_SUITE="${1:-all}"

print_header "TeleTrade Hub Backend - Test Suite"
echo "Test Suite: $TEST_SUITE"
echo ""

case $TEST_SUITE in
    unit)
        print_header "Running Unit Tests"
        vendor/bin/phpunit --testsuite "Unit Tests" --colors=always
        ;;
    
    integration)
        print_header "Running Integration Tests"
        vendor/bin/phpunit --testsuite "Integration Tests" --colors=always
        ;;
    
    e2e)
        print_header "Running E2E Tests"
        vendor/bin/phpunit --testsuite "E2E Tests" --colors=always
        ;;
    
    coverage)
        print_header "Running Tests with Coverage"
        vendor/bin/phpunit --coverage-html tests/coverage --coverage-text --colors=always
        
        if [ $? -eq 0 ]; then
            print_success "Coverage report generated at tests/coverage/index.html"
            
            # Try to open coverage report in browser (macOS)
            if [[ "$OSTYPE" == "darwin"* ]]; then
                open tests/coverage/index.html
            fi
        fi
        ;;
    
    security)
        print_header "Running Security Tests Only"
        vendor/bin/phpunit tests/E2E/SecurityTest.php --colors=always
        ;;
    
    quick)
        print_header "Running Quick Test Suite (Unit + Integration)"
        vendor/bin/phpunit --testsuite "Unit Tests" --colors=always
        vendor/bin/phpunit --testsuite "Integration Tests" --colors=always
        ;;
    
    all|*)
        print_header "Running All Tests"
        
        echo ""
        print_header "1/3: Unit Tests"
        vendor/bin/phpunit --testsuite "Unit Tests" --colors=always
        UNIT_EXIT=$?
        
        echo ""
        print_header "2/3: Integration Tests"
        vendor/bin/phpunit --testsuite "Integration Tests" --colors=always
        INTEGRATION_EXIT=$?
        
        echo ""
        print_header "3/3: E2E Tests"
        vendor/bin/phpunit --testsuite "E2E Tests" --colors=always
        E2E_EXIT=$?
        
        echo ""
        print_header "Test Summary"
        
        if [ $UNIT_EXIT -eq 0 ]; then
            print_success "Unit Tests: PASSED"
        else
            print_error "Unit Tests: FAILED"
        fi
        
        if [ $INTEGRATION_EXIT -eq 0 ]; then
            print_success "Integration Tests: PASSED"
        else
            print_error "Integration Tests: FAILED"
        fi
        
        if [ $E2E_EXIT -eq 0 ]; then
            print_success "E2E Tests: PASSED"
        else
            print_error "E2E Tests: FAILED"
        fi
        
        if [ $UNIT_EXIT -eq 0 ] && [ $INTEGRATION_EXIT -eq 0 ] && [ $E2E_EXIT -eq 0 ]; then
            echo ""
            print_success "ALL TESTS PASSED! 🎉"
            exit 0
        else
            echo ""
            print_error "SOME TESTS FAILED"
            exit 1
        fi
        ;;
esac

# Check exit code
if [ $? -eq 0 ]; then
    echo ""
    print_success "Tests completed successfully! 🎉"
    exit 0
else
    echo ""
    print_error "Tests failed!"
    exit 1
fi

