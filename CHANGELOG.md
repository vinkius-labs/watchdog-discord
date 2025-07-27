# Changelog

All notable changes to `watchdog-discord` will be documented in this file.

## [2.0.0] - 2025-01-XX

### Added
- **Laravel Facade** - Added `WatchdogDiscord` facade for cleaner API usage
- **Queue Support** - Send notifications asynchronously via Laravel queues
- **Rate Limiting** - Built-in rate limiting to prevent notification spam
- **Events System** - Dispatch Laravel events when notifications are sent
- **Artisan Commands** - `watchdog-discord:test` command for testing integration
- **Middleware Support** - Optional middleware for request monitoring
- **Environment Filtering** - Only send notifications in specific environments
- **Exception Filtering** - Ignore specific exception types
- **Enhanced Configuration** - Extensive configuration options with environment variables
- **Internationalization** - Multi-language support (EN, PT-BR)
- **Request Context** - Include request data in error notifications
- **Stack Trace Support** - Optional stack trace inclusion
- **Field Truncation** - Automatic field length management for Discord limits
- **Helper Methods** - Convenient methods for all PSR-3 log levels
- **Docker Improvements** - Better Docker configuration and multi-service setup
- **Code Quality Tools** - PHPStan, Pint, and comprehensive testing

### Changed
- **BREAKING**: Minimum PHP version increased to 8.1
- **BREAKING**: Service provider registration improved with better Laravel integration
- **BREAKING**: Configuration structure updated with new options and environment variables
- **Enhanced Error Handling** - Better error handling, logging, and failure recovery
- **Improved Performance** - Optimized HTTP requests, caching, and async processing
- **Better Testing** - Comprehensive test suite with multiple test scenarios

### Enhanced
- **Discord Message Formatting** - Rich embeds with colors, emojis, and better field organization
- **Configuration Management** - Environment-based configuration with validation and defaults
- **Documentation** - Comprehensive documentation with examples and best practices
- **Error Context** - More detailed error information including request data and user context
- **Service Provider** - Better Laravel integration with proper binding and alias registration

### Fixed
- **Memory Issues** - Fixed potential memory issues with large payloads and long-running processes
- **HTTP Timeouts** - Proper timeout handling for webhook requests with retry mechanisms
- **Concurrent Requests** - Better handling of concurrent notification requests and rate limiting
- **Configuration Loading** - Improved configuration loading and merging

## [1.0.0] - 2025-07-27

### Added
- Initial release of Watchdog Discord
- Automatic exception capturing and reporting to Discord
- Configurable webhook settings
- Environment-specific error reporting
- Detailed error notifications with context information