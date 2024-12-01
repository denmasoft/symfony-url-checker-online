# Symfony url checker online

## Overview

The Symfony url checker online is a Symfony command-line tool designed to verify the online status of web URLs. It provides robust URL validation, connection checking, and includes features to handle complex network scenarios like Cloudflare protection.

## Features

- ✅ Comprehensive URL validation
- 🌐 HTTP/HTTPS protocol support
- 🛡️ Security checks against private networks
- 🕵️ Cloudflare and captcha protection detection
- 📊 Detailed status reporting

## System Requirements

- PHP 8.1 or higher
- Composer
- Symfony CLI (recommended)
- Network connectivity

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/denmasoft/symfony-url-checker-online.git
cd symfony-url-checker-online
```

### 2. Install Dependencies

```bash
composer install
```

## Configuration

The application uses environment-based configuration. You can customize settings in the `.env` file.

## Usage

### Basic URL Check

```bash
php bin/console app:check-url https://example.com
```

### Advanced Options

1. Custom Timeout
```bash
php bin/console app:check-url https://example.com --timeout=5
```

2. Cloudflare Bypass Mode (default: true)
```bash
php bin/console app:check-url https://protected-site.com
```

## Command Options

- `url`: (Required) The URL to check
- `--timeout`: Connection timeout in seconds (default: 10)
- `--cloudflare-bypass`: Attempt to bypass Cloudflare protection (default: true)

## Security Considerations

- The tool prevents checking localhost and private network URLs
- Validates URL structure to mitigate potential injection risks
- Uses a realistic User-Agent to improve request success rates

## Troubleshooting

### Common Issues

1. **SSL/TLS Errors**: 
   - Ensure your PHP installation has up-to-date CA certificates

2. **Network Restrictions**:
   - Check firewall settings
   - Verify internet connectivity

## Development

### Running Tests

```bash
php bin/phpunit
```

### Code Quality

```bash
php vendor/bin/phpcs
php vendor/bin/phpstan analyze src
```