=== WPPlugin Watch ===
Contributors: aarondemory
Tags: security, vulnerabilities, plugins, wordpress security
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WPPlugin Watch monitors installed plugins, themes, and WordPress core for known vulnerabilities and provides severity-rated results.

== Description ==

WPPlugin Watch helps site owners identify known vulnerabilities in their WordPress environment by analyzing installed plugins, themes, and WordPress core versions.

The plugin compares your site's software versions against a continuously updated vulnerability database and returns severity-rated findings in plain English.

Features include:

- Plugin and theme vulnerability scanning
- WordPress core vulnerability checks
- Severity-rated results
- Clear, actionable findings
- Lightweight and fast scanning

== External Services ==

This plugin connects to an external API provided by WPPlugin Watch to perform vulnerability analysis.

Service endpoint:
https://api.wpplugin.watch

Data sent to the API includes:

- Installed plugin names and versions
- Installed theme names and versions
- WordPress core version
- An anonymous site fingerprint (generated locally)

No personal data, user data, or content is transmitted.

The site fingerprint is used to uniquely identify installations for operational and service purposes and cannot be used to identify individuals.

External communication only occurs when a scan is initiated by the user or when the feature is enabled.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wpplugin-watch` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to the WPPlugin Watch interface.
4. Enable scanning and initiate your first scan.

== Frequently Asked Questions ==

= Does this plugin send my data externally? =

Yes. The plugin sends plugin, theme, and WordPress version information to the WPPlugin Watch API to perform vulnerability analysis. No personal data is sent.

= Is user consent required? =

Yes. The plugin should only be used after the site administrator enables scanning.

= Does this plugin slow down my site? =

No. Scans are performed on demand and designed to be lightweight.

= What vulnerabilities are detected? =

The plugin detects known vulnerabilities in plugins, themes, and WordPress core based on an external vulnerability database.

== Screenshots ==

1. Scan results overview
2. Vulnerability details
3. Plugin dashboard interface

== Changelog ==

= 1.2.0 =
* Added explicit scanning control (disabled by default until enabled by an administrator)
* Added external service disclosure
* Added privacy policy integration
* Added uninstall cleanup
* Improved transparency around vulnerability scanning behavior

= 1.1.0 =
* Initial release

== Upgrade Notice ==

= 1.2.0 =
This release introduces explicit user consent controls and improved transparency around vulnerability scanning.

= 1.1.0 =
Initial release of WPPlugin Watch.