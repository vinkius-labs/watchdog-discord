# Documentation Index

Welcome to the Watchdog Discord documentation. This comprehensive guide covers everything from basic installation to advanced performance optimization.

## Getting Started

### 📚 [Installation Guide](installation.md)
Complete setup instructions for all environments, including system requirements, configuration, and Discord webhook setup.

**Topics Covered**:
- System requirements and dependencies
- Installation methods (Composer, manual)
- Service provider registration
- Environment configuration
- Database migration
- Discord webhook setup
- Queue configuration
- Verification and testing

### ⚙️ [Configuration Reference](configuration.md)
Detailed reference for all configuration options with examples and best practices.

**Topics Covered**:
- Core configuration options
- Queue and performance settings
- Rate limiting and filtering
- Message formatting options
- Error tracking configuration
- Database and cache settings
- Environment-specific configurations

### 💡 [Examples and Usage Patterns](examples.md)
Practical examples and real-world usage patterns for different scenarios.

**Topics Covered**:
- Basic usage and quick setup
- Advanced configuration patterns
- Custom error handling
- Performance monitoring examples
- Testing and debugging techniques
- Production deployment patterns
- Security considerations

## Advanced Topics

### 🏗️ [Architecture Guide](architecture.md)
In-depth technical documentation of the package's internal architecture and design patterns.

**Topics Covered**:
- System overview and layered architecture
- Core component design patterns
- Error tracking and analytics algorithms
- Queue job architecture
- Data models and database design
- Performance optimizations
- Security implementations
- Event system design

### ⚡ [Performance Guide](performance.md)
Comprehensive guide to optimizing Watchdog Discord for production environments.

**Topics Covered**:
- Performance benchmarks and metrics
- Configuration optimization
- Redis and database tuning
- Queue worker scaling
- Memory management
- Load testing strategies
- Monitoring and alerting
- Scaling strategies

### 🔧 [Troubleshooting Guide](troubleshooting.md)
Solutions for common issues and debugging techniques.

**Topics Covered**:
- Quick diagnosis commands
- Common issues and solutions
- Performance problem debugging
- Environment-specific troubleshooting
- Debug tools and techniques
- Support and bug reporting

## Quick Reference

### Essential Commands

```bash
# Installation
composer require vinkius-labs/watchdog-discord
php artisan vendor:publish --provider="VinkiusLabs\WatchdogDiscord\WatchdogDiscordServiceProvider"
php artisan migrate

# Testing
php artisan watchdog-discord:test --exception
php artisan watchdog-discord:test --level=error --message="Test message"

# Queue Management
php artisan queue:work redis --queue=watchdog_notifications
php artisan queue:monitor redis:watchdog_notifications
php artisan queue:failed

# Configuration
php artisan config:show watchdog-discord
php artisan config:clear
```

### Critical Environment Variables

```env
# Core Settings
WATCHDOG_DISCORD_ENABLED=true
WATCHDOG_DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/your/webhook

# Performance (Production)
WATCHDOG_DISCORD_ASYNC_ENABLED=true
WATCHDOG_DISCORD_QUEUE_CONNECTION=redis
WATCHDOG_DISCORD_CACHE_PREFIX=watchdog

# Error Tracking
WATCHDOG_DISCORD_MIN_SEVERITY=7
WATCHDOG_DISCORD_FREQUENCY_THRESHOLD=10

# Rate Limiting
WATCHDOG_DISCORD_RATE_LIMIT_ENABLED=true
WATCHDOG_DISCORD_RATE_LIMIT_MAX=20
```

### Usage Examples

```php
use VinkiusLabs\WatchdogDiscord\Facades\WatchdogDiscord;

// Manual logging
WatchdogDiscord::error('Payment failed', ['user_id' => 123]);
WatchdogDiscord::critical('Database connection lost');

// Direct notifier usage
$notifier = app(\VinkiusLabs\WatchdogDiscord\DiscordNotifier::class);
$notifier->send($exception);

// Middleware usage
Route::middleware('watchdog-discord:error')->group(function () {
    Route::post('/api/critical', [Controller::class, 'method']);
});
```

## Documentation Maintenance

This documentation is maintained alongside the codebase to ensure accuracy and completeness. Each section is designed to be:

- **Comprehensive**: Covers all aspects of the topic
- **Practical**: Includes working examples and real-world scenarios
- **Current**: Updated with each package release
- **Searchable**: Organized for easy navigation and reference

## Contributing to Documentation

If you find errors, have suggestions for improvements, or want to contribute new content:

1. **Report Issues**: [GitHub Issues](https://github.com/vinkius-labs/watchdog-discord/issues)
2. **Submit Pull Requests**: [GitHub Repository](https://github.com/vinkius-labs/watchdog-discord)
3. **Join Discussion**: [Discord Community](https://discord.gg/vinkiuslabs)

## Additional Resources

- **Main README**: [Project overview and quick start](../README.md)
- **API Reference**: Generated from source code docblocks
- **Change Log**: [CHANGELOG.md](../CHANGELOG.md)
- **Contributing Guide**: [CONTRIBUTING.md](../CONTRIBUTING.md)
- **License**: [LICENSE](../LICENSE)

## Version Information

This documentation is for Watchdog Discord v2.x. For previous versions, please refer to the appropriate branch in the repository.

**Compatibility**:
- PHP: 8.1, 8.2, 8.3
- Laravel: 9.x, 10.x, 11.x
- Redis: 6.0+ (recommended)

## Support

For technical support and questions:

- **GitHub Issues**: Bug reports and feature requests
- **Discord Community**: Real-time help and discussion
- **Email**: labs@vinkius.com
- **Documentation Updates**: Submit PRs for improvements

---

*Last updated: January 2025*
