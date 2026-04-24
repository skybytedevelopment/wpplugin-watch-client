# Contributing to WPPlugin Watch

Thank you for your interest in contributing.

## Requirements

- PHP 8.0+
- WordPress 6.0+ (local install for testing)
- No build tools required — the plugin is plain PHP, JS, and CSS

## Development Setup

1. Clone your fork
2. Place the plugin in your local WordPress `/wp-content/plugins/` directory
3. Activate the plugin in WordPress
4. Make changes and test locally

## Building

```bash
./build.sh
```

Prompts for an optional API base override (for dev/staging) and whether this is a security release. Outputs a versioned zip to `dist/`.

## Code Standards

This plugin follows [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/):

- Spaces inside parentheses: `if ( $condition )`
- `array()` syntax, not `[]`
- Yoda conditions: `if ( 'value' === $var )`
- All public methods have docblocks with `@param` and `@return`
- Text domain: `wppluginwatch`
- Option prefix: `wpw_`
- Constants prefix: `WPW_`

## Submitting Issues

- **Bug reports:** Include WordPress version, PHP version, and steps to reproduce.
- **Feature requests:** Open an issue describing the use case before writing code.
- **Security vulnerabilities:** See [SECURITY.md](SECURITY.md) — do not open a public issue.

## Pull Requests

1. Fork the repo and create a branch from `main`.
   - Use a descriptive branch name: `feature/...`, `fix/...`, or `chore/...`
2. Make your changes following the coding standards above.
3. Test against a local WordPress install.
4. Open a pull request with a clear description of what changed and why.

Include in your PR:
- What changed
- Why it was needed
- Any testing performed

Pull requests that do not follow coding standards or are not tested locally may be closed.

Please keep pull requests focused — one concern per PR.
