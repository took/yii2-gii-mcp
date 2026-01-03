# Docker Examples

Example configurations for using yii2-gii-mcp with Docker Desktop.

## Files

- **firebender-host.json** - Firebender config for host-based MCP server (recommended)
- **firebender-docker.json** - Firebender config for container-based MCP server
- **claude-host.json** - Claude Desktop config for host-based setup
- **claude-docker.json** - Claude Desktop config for container-based setup
- **config-mcp-docker.php** - Example MCP config for Docker database
- **docker-compose.example.yml** - Sample Docker Compose setup
- **yii2-gii-mcp-docker** - Wrapper script for running MCP in container

## Quick Setup

### Option A: Host-Based (Recommended)

1. **Copy Firebender config:**
```bash
# Merge content from firebender-host.json into ~/.firebender/firebender.json
```

2. **Create MCP config:**
```bash
cp config-mcp-docker.php ../../config-mcp.php
# Edit database credentials
```

3. **Restart PhpStorm**

### Option B: Container-Based

1. **Copy wrapper script:**
```bash
cp yii2-gii-mcp-docker ../../bin/
chmod +x ../../bin/yii2-gii-mcp-docker
# Edit container name in script
```

2. **Copy Firebender config:**
```bash
# Merge content from firebender-docker.json into ~/.firebender/firebender.json
```

3. **Create MCP config in container:**
```bash
docker exec -it your-php-container bash
cd /var/www/html
cp vendor/took/yii2-gii-mcp/examples/docker/config-mcp-docker.php config-mcp.php
# Edit for container environment (use service names)
```

4. **Restart PhpStorm**

## Testing

```bash
# Host-based
php ../../vendor/took/yii2-gii-mcp/bin/diagnose

# Container-based
docker exec your-php-container php /var/www/html/vendor/took/yii2-gii-mcp/bin/diagnose
```

## Documentation

For complete Docker usage guide, see:

**→ [../../docs/DOCKER.md](../../docs/DOCKER.md)**
