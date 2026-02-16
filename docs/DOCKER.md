# Docker Usage Guide

Complete guide for using yii2-gii-mcp with Docker Desktop.

## Quick Start

### Option A: MCP Server on Host (Recommended)

```bash
# 1. Install MCP server on host (outside Docker)
cd /path/to/your/yii2/project
composer require took/yii2-gii-mcp

# 2. Create config pointing to Docker database
php vendor/took/yii2-gii-mcp/bin/interactive-setup

# 3. Configure Firebender (one-time setup)
# Edit ~/firebender.json
# See configuration below

# 4. Restart PhpStorm/IDE
# That's it! MCP server is ready
```

### Option B: MCP Server Inside Docker

```bash
# 1. Install inside container
docker exec -it your-php-container bash
cd /var/www/html
composer require took/yii2-gii-mcp

# 2. Run setup inside container
php vendor/took/yii2-gii-mcp/bin/interactive-setup

# 3. Use exec wrapper (see Advanced Setup below)
```

## Prerequisites

- **Docker Desktop** installed and running
- **PHP 8.2+** available (host or container)
- **Yii2 project** already containerized
- **Database** running in Docker container

## Understanding the Architecture

### Scenario A: Hybrid Setup (Recommended)

```
┌─────────────────────────┐
│   Host Machine          │
│  ┌───────────────────┐  │
│  │  Firebender       │  │
│  │  (PhpStorm)       │  │
│  └────────┬──────────┘  │
│           │ stdio       │
│  ┌────────▼──────────┐  │
│  │  MCP Server       │  │
│  │  (PHP process)    │  │
│  └────────┬──────────┘  │
└───────────┼─────────────┘
            │ TCP
            │ host.docker.internal:3306
┌───────────▼─────────────┐
│   Docker Desktop        │
│  ┌───────────────────┐  │
│  │  MySQL Container  │  │
│  │  Port 3306        │  │
│  └───────────────────┘  │
│  ┌───────────────────┐  │
│  │  PHP Container    │  │
│  │  (Yii2 app)       │  │
│  └───────────────────┘  │
└─────────────────────────┘
```

**Advantages:**

- ✅ Simple Firebender configuration
- ✅ No Docker networking complexity
- ✅ Fast communication (no Docker overhead)
- ✅ Easy debugging

**Requirements:**

- PHP 8.2+ installed on host
- Composer available on host

### Scenario B: Full Docker Setup

```
┌─────────────────────────┐
│   Host Machine          │
│  ┌───────────────────┐  │
│  │  Firebender       │  │
│  │  (PhpStorm)       │  │
│  └────────┬──────────┘  │
└───────────┼─────────────┘
            │ docker exec
┌───────────▼─────────────┐
│   Docker Desktop        │
│  ┌───────────────────┐  │
│  │  PHP Container    │  │
│  │  ┌─────────────┐  │  │
│  │  │ MCP Server  │  │  │
│  │  │ (running in │  │  │
│  │  │  container) │  │  │
│  │  └──────┬──────┘  │  │
│  └─────────┼─────────┘  │
│            │ localhost  │
│  ┌─────────▼─────────┐  │
│  │  MySQL Container  │  │
│  │  Port 3306        │  │
│  └───────────────────┘  │
└─────────────────────────┘
```

**Advantages:**

- ✅ No host PHP requirement
- ✅ Consistent environment
- ✅ Works on any host OS

**Disadvantages:**

- ⚠️ More complex setup
- ⚠️ Requires wrapper script
- ⚠️ Slower (Docker exec overhead)

## Configuration

### Option A: Host-Based Setup

#### 1. Database Connection

Your Docker database needs to be accessible from the host machine. Ensure ports are exposed:

```yaml
# docker-compose.yml
services:
  mysql:
    image: mysql:8.0
    ports:
      - "3306:3306"  # Expose to host
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: yii2_app
```

#### 2. Create config-mcp.php

Use the interactive setup and configure database host:

```bash
php vendor/took/yii2-gii-mcp/bin/interactive-setup
```

Or manually create `config-mcp.php`:

```php
<?php
// For Docker Desktop on Windows/Mac: use host.docker.internal
// For Docker on Linux: use 172.17.0.1 or find docker0 bridge IP

return [
    'id' => 'mcp-console',
    'basePath' => dirname(__DIR__),
    'components' => [
        'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=127.0.0.1;dbname=yii2_app',  // Localhost from host perspective
            'username' => 'root',
            'password' => 'secret',
            'charset' => 'utf8',
        ],
    ],
    'modules' => [
        'gii' => [
            'class' => 'yii\gii\Module',
        ],
    ],
];
```

#### 3. Configure Firebender

Edit `~/firebender.json`:

```json
{
  "mcpServers": {
    "yii2-gii": {
      "command": "php",
      "args": [
        "${workspaceFolder}/vendor/took/yii2-gii-mcp/bin/yii2-gii-mcp"
      ],
      "env": {
        "YII2_CONFIG_PATH": "${workspaceFolder}/config-mcp.php",
        "YII2_APP_PATH": "${workspaceFolder}"
      }
    }
  }
}
```

**Important:** Use `${workspaceFolder}` - it automatically resolves to your project's path.

#### 4. Verify Setup

```bash
# Test database connection from host
php vendor/took/yii2-gii-mcp/bin/diagnose

# Test MCP server manually
YII2_CONFIG_PATH=config-mcp.php php vendor/took/yii2-gii-mcp/examples/test-list-tables.php
```

### Option B: Container-Based Setup

#### 1. Install in Container

```bash
# Access your PHP container
docker exec -it your-php-container bash

# Inside container
cd /var/www/html
composer require took/yii2-gii-mcp
```

#### 2. Create config-mcp.php in Container

```php
<?php
// Inside container, use Docker service names
return [
    'id' => 'mcp-console',
    'basePath' => dirname(__DIR__),
    'components' => [
        'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=mysql;dbname=yii2_app',  // Use Docker service name
            'username' => 'root',
            'password' => 'secret',
            'charset' => 'utf8',
        ],
    ],
    'modules' => [
        'gii' => [
            'class' => 'yii\gii\Module',
        ],
    ],
];
```

#### 3. Create Wrapper Script

Create `bin/yii2-gii-mcp-docker` on host:

```bash
#!/bin/bash
# Wrapper script to run MCP server in Docker container

CONTAINER_NAME="your-php-container"
PROJECT_PATH="/var/www/html"  # Path inside container

docker exec -i "$CONTAINER_NAME" php "$PROJECT_PATH/vendor/took/yii2-gii-mcp/bin/yii2-gii-mcp"
```

Make it executable:

```bash
chmod +x bin/yii2-gii-mcp-docker
```

#### 4. Configure Firebender

Edit `~/firebender.json`:

```json
{
  "mcpServers": {
    "yii2-gii": {
      "command": "${workspaceFolder}/bin/yii2-gii-mcp-docker",
      "args": [],
      "env": {
        "YII2_CONFIG_PATH": "/var/www/html/config-mcp.php",
        "YII2_APP_PATH": "/var/www/html"
      }
    }
  }
}
```

**Note:** Use container paths in environment variables!

#### 5. Verify Setup

```bash
# Test inside container
docker exec -it your-php-container bash
php vendor/took/yii2-gii-mcp/bin/diagnose

# Test wrapper from host
./bin/yii2-gii-mcp-docker <<EOF
{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}}}
EOF
```

## Claude Desktop Configuration

### Option A: Host-Based (Recommended)

Edit Claude config file:

- **macOS**: `~/Library/Application Support/Claude/claude_desktop_config.json`
- **Windows**: `%APPDATA%\Claude\claude_desktop_config.json`
- **Linux**: `~/.config/Claude/claude_desktop_config.json`

```json
{
  "mcpServers": {
    "yii2-gii": {
      "command": "php",
      "args": [
        "/absolute/path/to/your/project/vendor/took/yii2-gii-mcp/bin/yii2-gii-mcp"
      ],
      "env": {
        "YII2_CONFIG_PATH": "/absolute/path/to/your/project/config-mcp.php",
        "YII2_APP_PATH": "/absolute/path/to/your/project"
      }
    }
  }
}
```

**Important:** Claude Desktop requires **absolute paths**, not `${workspaceFolder}`.

### Option B: Container-Based

```json
{
  "mcpServers": {
    "yii2-gii": {
      "command": "/absolute/path/to/your/project/bin/yii2-gii-mcp-docker",
      "args": [],
      "env": {
        "YII2_CONFIG_PATH": "/var/www/html/config-mcp.php",
        "YII2_APP_PATH": "/var/www/html"
      }
    }
  }
}
```

## Troubleshooting

### Database Connection Errors

**Error:** `SQLSTATE[HY000] [2002] Connection refused`

**Solutions:**

1. **Check port mapping:**

```bash
docker ps
# Look for 0.0.0.0:3306->3306/tcp
```

2. **Verify database is running:**

```bash
docker exec -it your-mysql-container mysql -u root -p
```

3. **Test connection from host:**

```bash
mysql -h 127.0.0.1 -P 3306 -u root -p
```

4. **Check firewall:** Ensure port 3306 is not blocked

5. **Use correct host:**
    - Windows/Mac Docker Desktop: `127.0.0.1` (from host)
    - Linux: `127.0.0.1` or docker bridge IP

### Path Not Found Errors

**Error:** `config-mcp.php not found`

**Check paths match your setup:**

```bash
# Host-based: Use host file system paths
YII2_CONFIG_PATH="${workspaceFolder}/config-mcp.php"

# Container-based: Use container file system paths
YII2_CONFIG_PATH="/var/www/html/config-mcp.php"
```

**Verify file exists:**

```bash
# Host
ls -la /path/to/project/config-mcp.php

# Container
docker exec your-php-container ls -la /var/www/html/config-mcp.php
```

### Permission Errors

**Error:** `Permission denied` when generating files

**Solutions:**

1. **Check volume mount permissions:**

```bash
# docker-compose.yml
volumes:
  - ./:/var/www/html:delegated  # Add :delegated for better performance
```

2. **Fix ownership inside container:**

```bash
docker exec your-php-container chown -R www-data:www-data /var/www/html
```

3. **Match user IDs:**

```yaml
# docker-compose.yml
services:
  php:
    user: "${UID:-1000}:${GID:-1000}"  # Use host user ID
```

### MCP Server Not Responding

**Error:** Firebender shows "MCP server not available"

**Solutions:**

1. **Test PHP is accessible:**

```bash
php --version
# Should show PHP 8.2+
```

2. **Test MCP server manually:**

```bash
echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}}}' | php vendor/took/yii2-gii-mcp/bin/yii2-gii-mcp
```

3. **Check Firebender logs:**
    - PhpStorm: Help → Show Log in Files
    - Look for MCP-related errors

4. **Restart completely:**
    - Close all PhpStorm windows
    - Kill PhpStorm processes
    - Start PhpStorm fresh

### Docker Compose Not Found

**Error:** `docker compose` command not found

**Solutions:**

1. **Use Docker Desktop's built-in Compose:**

```bash
docker compose version
# Should show Compose v2.x
```

2. **Or use docker-compose (legacy):**

```bash
docker-compose version
```

3. **Update Docker Desktop** to latest version

### Network Issues on Linux

**Problem:** Can't connect to Docker MySQL from host

**Solution:** Find and use docker0 bridge IP:

```bash
# Find docker bridge IP
ip addr show docker0 | grep inet
# Use that IP in config-mcp.php, e.g., 172.17.0.1

# Or add host network mode (less isolated)
# docker-compose.yml
services:
  mysql:
    network_mode: "host"
```

## Best Practices

### Security

1. **Add config-mcp.php to .gitignore:**

```bash
echo "config-mcp.php" >> .gitignore
```

2. **Use environment variables for secrets:**

```php
'password' => getenv('DB_PASSWORD') ?: 'fallback',
```

3. **Restrict database access:**

```yaml
services:
  mysql:
    environment:
      MYSQL_ROOT_HOST: "172.17.0.%"  # Only allow Docker network
```

### Performance

1. **Use host-based setup** for better performance (no Docker exec overhead)

2. **Add delegated mode to volumes:**

```yaml
volumes:
  - ./:/var/www/html:delegated
```

3. **Persistent volume for vendor:**

```yaml
volumes:
  - vendor:/var/www/html/vendor
```

### Development Workflow

1. **Use separate configs for Docker:**

```
config/              # Main Yii2 configs
config-mcp.php       # MCP-specific (Docker database)
config/test.php      # Test environment
```

2. **Keep MCP server on host** if possible - simpler debugging

3. **Use Docker for database only**, run PHP/Composer on host

## Examples

See `examples/docker/` directory for:

- Sample Firebender configurations
- Sample config-mcp.php for Docker
- Working Docker Compose setup
- Wrapper scripts

## Advanced Topics

### Multiple Environments

```json
{
  "mcpServers": {
    "yii2-dev": {
      "command": "php",
      "args": ["${workspaceFolder}/vendor/took/yii2-gii-mcp/bin/yii2-gii-mcp"],
      "env": {
        "YII2_CONFIG_PATH": "${workspaceFolder}/config-mcp-dev.php"
      }
    },
    "yii2-staging": {
      "command": "./bin/yii2-gii-mcp-docker-staging",
      "args": [],
      "env": {
        "YII2_CONFIG_PATH": "/var/www/html/config-mcp-staging.php"
      }
    }
  }
}
```

### Custom Docker Networks

```yaml
# docker-compose.yml
networks:
  yii2_network:
    driver: bridge

services:
  mysql:
    networks:
      - yii2_network
  php:
    networks:
      - yii2_network
```

From host, use exposed ports. From container, use service names.

### Windows-Specific Notes

1. **Use Windows-style paths in Claude Desktop:**

```json
"YII2_CONFIG_PATH": "C:\\Users\\YourName\\Projects\\yii2-app\\config-mcp.php"
```

2. **PowerShell wrapper script:**

```powershell
# bin/yii2-gii-mcp-docker.ps1
docker exec -i your-php-container php /var/www/html/vendor/took/yii2-gii-mcp/bin/yii2-gii-mcp
```

3. **WSL2 path translation:**

```bash
# Access Windows files from WSL2
cd /mnt/c/Users/YourName/Projects/yii2-app
```

## Further Help

- **General MCP issues**: See main [README.md](../README.md)
- **AI agent documentation**: See [AI-MEMORY-BANK.md](AI-MEMORY-BANK.md)
- **Interactive setup**: Run `php vendor/took/yii2-gii-mcp/bin/interactive-setup`
- **Diagnostics**: Run `php vendor/took/yii2-gii-mcp/bin/diagnose`
- **GitHub Issues**: Report Docker-specific problems with details about your setup
