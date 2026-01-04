.PHONY: help test test-unit test-functional test-all coverage clean install

# Default target
help:
	@echo "Available targets:"
	@echo "  make install          - Install dependencies"
	@echo "  make test            - Run all Codeception tests"
	@echo "  make test-unit       - Run unit tests only"
	@echo "  make test-functional - Run functional tests only"
	@echo "  make test-all        - Run all tests (alias for test)"
	@echo "  make coverage        - Generate HTML coverage report"
	@echo "  make clean           - Remove test artifacts and caches"

# Install dependencies
install:
	composer install

# Run all Codeception tests
test:
	vendor/bin/codecept run

# Run unit tests only
test-unit:
	vendor/bin/codecept run Unit

# Run functional tests only
test-functional:
	vendor/bin/codecept run Functional

# Run all tests (alias)
test-all: test

# Generate HTML coverage report
coverage:
	vendor/bin/codecept run --coverage --coverage-html --coverage-xml
	@echo ""
	@echo "Coverage report generated in tests/_output/coverage/"

# Clean generated files
clean:
	rm -rf tests/_output/
	rm -rf coverage/*
	@echo "Cleaned test artifacts and caches"
