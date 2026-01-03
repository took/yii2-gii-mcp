# Documentation

## AI Memory Bank

This directory contains comprehensive technical documentation optimized for AI coding assistants working with the yii2-gii-mcp project.

### [AI-MEMORY-BANK.md](AI-MEMORY-BANK.md) - Complete Technical Reference

**Purpose**: Single comprehensive reference document for AI agents

**Contents**:
- Complete project overview and architecture
- Full MCP protocol implementation details
- All 8 tools with detailed specifications
- Configuration for all MCP clients (Firebender, Claude Desktop, Cline, Cursor)
- Yii2 integration and bootstrap process
- Safety features and validation
- Setup tools documentation
- Testing infrastructure
- Development guidelines
- Complete file structure reference
- Troubleshooting guide with solutions
- Implementation history and design decisions
- Best practices for AI agents

**Target Audience**: AI coding assistants (Firebender, Claude, Cline, etc.)

**Size**: ~50KB comprehensive reference

**Use Case**: AI agents should refer to this document as their primary source of technical information about the project. It contains everything needed to understand, use, and extend the yii2-gii-mcp server.

## Human-Friendly Documentation

For human developers and quick start guides, see:

- **[../README.md](../README.md)** - Main project README with installation and quick start
- **[../TODO.md](../TODO.md)** - Development roadmap and implementation plan

## Quick Links

**For AI Agents**:
- Start here: [AI-MEMORY-BANK.md](AI-MEMORY-BANK.md)

**For Humans**:
- Start here: [../README.md](../README.md)
- Setup: Run `php vendor/took/yii2-gii-mcp/bin/interactive-setup`
- Troubleshooting: Run `php vendor/took/yii2-gii-mcp/bin/diagnose`

## Documentation Philosophy

**Separation of Concerns**:
- **Root README.md**: Quick start guide optimized for human developers
- **AI-MEMORY-BANK.md**: Comprehensive technical reference optimized for AI agents
- **TODO.md**: Development roadmap (will be removed before public release)

This structure ensures:
1. Humans get concise, action-oriented documentation
2. AI agents get exhaustive technical context
3. No redundancy across multiple files
4. Single source of truth for each audience

## Contributing to Documentation

When updating documentation:

1. **For user-facing changes**: Update [../README.md](../README.md)
2. **For technical details**: Update [AI-MEMORY-BANK.md](AI-MEMORY-BANK.md)
3. **For development plans**: Update [../TODO.md](../TODO.md)

Keep both human and AI documentation in sync when features change.
