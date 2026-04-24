# WPPlugin Watch

![License: GPL-2.0-or-later](https://img.shields.io/badge/License-GPL--2.0--or--later-blue.svg)

Continuous vulnerability monitoring for WordPress plugins, themes, and core — with plain-language results designed for real-world site owners.

## Overview

WPPlugin Watch scans your WordPress installation against the Wordfence Intelligence vulnerability feed and highlights known vulnerabilities with clear severity ratings and actionable guidance.

The goal is simple: make security visibility accessible without requiring security expertise.

## Features

- Detects known vulnerabilities (CVEs) in plugins, themes, and WordPress core  
- Severity grading: Critical, High, Medium, Low  
- Plain-language explanations for each finding  
- Version update alerts for security-related releases  
- Daily background checks for new vulnerabilities  
- Privacy-first design — no personally identifiable information collected  

## Architecture

- WordPress plugin collects local inventory (plugins, themes, core version)  
- A one-way SHA-256 fingerprint identifies the site (domain + site URL + `AUTH_SALT`)  
- Inventory is sent to the WPPlugin Watch backend (`api.wpplugin.watch`)  
- Backend matches against the Wordfence Intelligence vulnerability database  
- Results returned with severity grading and explanations  

## Privacy Model

- No usernames, emails, or content are transmitted  
- Site identity is represented only as a non-reversible SHA-256 fingerprint  
- Data sent is limited strictly to software inventory required for vulnerability matching  

## Requirements

- WordPress 6.0 or higher  
- PHP 8.0 or higher  

## Installation

1. Download the latest zip from the [Releases](../../releases) page  
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**  
3. Upload and activate  
4. Navigate to **WPPlugin Watch** and run your first scan  

## How It Works

1. The plugin gathers installed plugin slugs, theme slugs, and WordPress core version  
2. This inventory is securely transmitted to the backend with a site fingerprint  
3. The backend checks for known vulnerabilities using the Wordfence Intelligence database  
4. Results are returned with severity ratings and plain-language explanations  
5. A daily check identifies new vulnerabilities and available updates

## Building from Source

```bash
git clone https://github.com/skybytedev/wppluginwatch.git
cd wppluginwatch
./build.sh
```

`build.sh` prompts for an optional dev API endpoint override and outputs a versioned zip to `dist/`. See [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
