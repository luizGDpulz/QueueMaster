# QueueMaster Test Suite

This directory contains PHPUnit tests for the QueueMaster queue and appointment management system.

## Test Files

### 1. QueueConcurrencyTest.php
Tests concurrent queue operations to ensure thread-safety and position uniqueness:
- `testConcurrentJoin()` - Multiple users joining simultaneously
- `testPositionUniqueness()` - Ensures unique, monotonic positions
- `testRapidSequentialJoins()` - High-frequency join operations
- `testPriorityQueueUniqueness()` - Priority queue handling
- `testAnonymousUserJoins()` - Anonymous user support

### 2. CallNextConcurrencyTest.php
Tests concurrent call-next operations with FOR UPDATE locking:
- `testConcurrentCallNext()` - Simultaneous attendant calls
- `testOnlyOneWins()` - Ensures no duplicate calls
- `testTransactionProtection()` - Rollback safety
- `testEmptyQueueCallNext()` - Empty queue handling
- `testCallNextWithPriority()` - Priority-based calling
- `testSequentialCallNext()` - Sequential operations
- `testForUpdateLocking()` - Database locking verification

### 3. AppointmentConflictTest.php
Tests appointment scheduling and conflict detection:
- `testNoDoubleBooking()` - Prevents overlapping appointments
- `testValidAppointment()` - Non-overlapping appointments succeed
- `testTimeValidation()` - Start/end time calculation
- `testOverlapAtStart()` - Start boundary conflicts
- `testOverlapAtEnd()` - End boundary conflicts
- `testExactBoundaryAppointments()` - Back-to-back appointments
- `testCancelledAppointmentsDontBlock()` - Cancelled slot reuse
- `testMultipleAppointmentsInDay()` - Daily scheduling
- `testDifferentProfessionalsNoConflict()` - Multi-professional handling
- `testInvalidDatetimeFormat()` - Input validation

## Running Tests

### Run all tests:
```bash
php vendor/bin/phpunit
```

### Run specific test file:
```bash
php vendor/bin/phpunit tests/phpunit/QueueConcurrencyTest.php
php vendor/bin/phpunit tests/phpunit/CallNextConcurrencyTest.php
php vendor/bin/phpunit tests/phpunit/AppointmentConflictTest.php
```

### Run specific test method:
```bash
php vendor/bin/phpunit --filter testConcurrentJoin
```

### Run with coverage:
```bash
php vendor/bin/phpunit --coverage-html coverage/html
```

## Configuration

Tests use `phpunit.xml.dist` configuration which:
- Bootstraps `vendor/autoload.php`
- Sets test database environment variables
- Configures code coverage reporting
- Uses database transactions for isolation

## Database Setup

Tests require a MySQL/MariaDB test database. Configure in `phpunit.xml.dist`:
- `DB_NAME`: queue_system_test
- `DB_HOST`: 127.0.0.1
- `DB_PORT`: 3306
- `DB_USER`: root
- `DB_PASS`: rootpassword

Each test uses transactions that are rolled back after execution, ensuring isolation.

## Sample Data

For manual testing and development, use the seed script:
```bash
mysql -u root -p < ../scripts/seed_sample_data.sql
# Or from repository root:
mysql -u root -p < scripts/seed_sample_data.sql
```

This creates:
- 3 users (admin, attendant, client) - password: password123
- 1 establishment
- 2 services
- 2 professionals
- 1 open queue with entries
- 3 sample appointments

## Test Approach

All tests:
- Extend `PHPUnit\Framework\TestCase`
- Use database transactions in setUp/tearDown
- Create isolated test data
- Test both positive and negative cases
- Verify transaction safety and concurrency handling
