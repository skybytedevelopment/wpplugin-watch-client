( function () {
    "use strict";

    /* ------------------------------------------------------------------ *
     * Severity helpers
     * ------------------------------------------------------------------ */

    /* Sort order for severity levels -- lower index = higher priority. */
    var SEV_ORDER = { critical: 0, high: 1, medium: 2, low: 3 };

    var SEV_CLASS = {
        critical: "wpw-sev-critical",
        high:     "wpw-sev-high",
        medium:   "wpw-sev-medium",
        low:      "wpw-sev-low"
    };

    function sevLabel( sev ) {
        return sev ? sev.charAt( 0 ).toUpperCase() + sev.slice( 1 ) : "Unknown";
    }

    function cvssColor( score ) {
        var s = parseFloat( score ) || 0;
        if ( s >= 9.0 ) return "#d63638";
        if ( s >= 7.0 ) return "#996800";
        if ( s >= 4.0 ) return "#1d4289";
        return "#8c8f94";
    }

    /* ------------------------------------------------------------------ *
     * Vuln row renderer
     * ------------------------------------------------------------------ */

    /**
     * Build and return a single vulnerability row element.
     *
     * @param {string} slug        Plugin or theme slug.
     * @param {Object} vuln        Vulnerability object from the API response.
     * @param {string} key         Unique key used as data-key on the row element.
     * @param {string} containerId ID of the parent list container (used to detect core).
     * @returns {HTMLElement}
     */
    function makeVulnRow( slug, vuln, key, containerId ) {
        var pct    = Math.round( Math.min( ( parseFloat( vuln.cvss ) || 0 ) / 10, 1 ) * 100 );
        var color  = cvssColor( vuln.cvss );
        var sevCls = SEV_CLASS[ vuln.severity ] || "wpw-sev-low";

        var patchHtml = vuln.patched
            ? '<span class="wpw-patch-yes">&#10003; Patched in ' + escHtml( vuln.patched_in || "unknown" ) + "</span>"
            : '<span class="wpw-patch-no">&#10005; No patch available</span>';

        var row = document.createElement( "div" );
        row.className  = "wpw-vuln-row";
        row.dataset.key = key;

        var isCore       = containerId === "wpw-core-list";
        var titleInMain  = isCore
            ? '<span class="wpw-vuln-main-title">' + escHtml( vuln.title || "" ) + "</span>"
            : "";
        var subtitleHtml = isCore
            ? ""
            : '<div class="wpw-vuln-subtitle">' + escHtml( vuln.title || "" ) + "</div>";

        row.innerHTML =
            '<div class="wpw-vuln-main">' +
                '<span class="wpw-vuln-slug">'  + escHtml( slug ) + "</span>" +
                titleInMain +
                '<span class="wpw-sev ' + sevCls + '">' + escHtml( sevLabel( vuln.severity ) ) + "</span>" +
                '<span class="wpw-chevron">&#9658;</span>' +
            "</div>" +
            subtitleHtml +
            '<div class="wpw-vuln-detail">' +
                '<div class="wpw-detail-grid">' +
                    '<div class="wpw-detail-cell">' +
                        '<div class="wpw-detail-cell-label">CVSS score</div>' +
                        '<div class="wpw-detail-cell-val" style="color:' + color + '">' + escHtml( vuln.cvss || "\u2014" ) + "</div>" +
                        '<div class="wpw-cvss-bar"><div class="wpw-cvss-fill" style="width:' + pct + '%;background:' + color + '"></div></div>' +
                    "</div>" +
                    '<div class="wpw-detail-cell">' +
                        '<div class="wpw-detail-cell-label">Vuln ID</div>' +
                        '<div class="wpw-detail-cell-val" style="font-family:monospace;font-size:11px">' + escHtml( vuln.id || "\u2014" ) + "</div>" +
                    "</div>" +
                    '<div class="wpw-detail-cell">' +
                        '<div class="wpw-detail-cell-label">Patch status</div>' +
                        '<div class="wpw-detail-cell-val">' + patchHtml + "</div>" +
                    "</div>" +
                "</div>" +
            "</div>";

        row.addEventListener( "click", function () {
            row.classList.toggle( "is-open" );
        } );

        return row;
    }

    /* ------------------------------------------------------------------ *
     * Section renderer (plugins / themes / core)
     * ------------------------------------------------------------------ */

    /**
     * Render all vulnerability rows for a section and update its badge.
     *
     * @param {string} containerId  ID of the list container element.
     * @param {string} badgeId      ID of the badge element.
     * @param {Object} items        Map of slug -> vuln[] from the API response.
     * @returns {number}            Total number of vulnerabilities rendered.
     */
    function renderSection( containerId, badgeId, items ) {
        var container = document.getElementById( containerId );
        var badge     = document.getElementById( badgeId );
        if ( ! container || ! badge ) return 0;

        container.innerHTML = "";

        if ( ! items || Object.keys( items ).length === 0 ) {
            var clean = document.createElement( "div" );
            clean.className  = "wpw-clean";
            clean.textContent = "No vulnerabilities found";
            container.appendChild( clean );
            badge.textContent = "Clean";
            badge.className   = "wpw-badge wpw-badge-clean";
            return 0;
        }

        var total = 0;

        // Flatten slug+vuln pairs and sort by descending severity before render.
        var pairs = [];
        Object.keys( items ).forEach( function ( slug ) {
            var vulns = items[ slug ];
            if ( ! Array.isArray( vulns ) ) return;
            vulns.forEach( function ( vuln ) {
                pairs.push( { slug: slug, vuln: vuln } );
            } );
        } );

        pairs.sort( function ( a, b ) {
            var ao = SEV_ORDER[ a.vuln.severity ] !== undefined ? SEV_ORDER[ a.vuln.severity ] : 99;
            var bo = SEV_ORDER[ b.vuln.severity ] !== undefined ? SEV_ORDER[ b.vuln.severity ] : 99;
            return ao - bo;
        } );

        pairs.forEach( function ( pair, i ) {
            total++;
            container.appendChild( makeVulnRow( pair.slug, pair.vuln, containerId + "-" + i, containerId ) );
        } );

        badge.textContent = total + ( total === 1 ? " issue" : " issues" );
        badge.className   = "wpw-badge wpw-badge-issues";
        return total;
    }

    /* ------------------------------------------------------------------ *
     * Stat helpers
     * ------------------------------------------------------------------ */

    /** Count critical + high severity vulns across a section's items map. */
    function countSevere( items ) {
        var n = 0;
        if ( ! items ) return n;
        Object.keys( items ).forEach( function ( slug ) {
            ( items[ slug ] || [] ).forEach( function ( v ) {
                if ( v.severity === "critical" || v.severity === "high" ) n++;
            } );
        } );
        return n;
    }

    function setText( id, val ) {
        var el = document.getElementById( id );
        if ( el ) el.textContent = val;
    }

    /* ------------------------------------------------------------------ *
     * Utilities
     * ------------------------------------------------------------------ */

    /** Minimal HTML escaping for untrusted string output into innerHTML. */
    function escHtml( str ) {
        return String( str )
            .replace( /&/g,  "&amp;" )
            .replace( /</g,  "&lt;" )
            .replace( />/g,  "&gt;" )
            .replace( /"/g,  "&quot;" );
    }

    /**
     * Build a "Last scan: ..." label from the browser's local time.
     *
     * Note: this uses client-side time. The server-side wpw_last_scan option
     * stores a UTC Unix timestamp and is rendered in PHP on page load. After
     * a scan the JS replaces the PHP-rendered value with client time, which
     * may differ from server time in different timezone configurations. This
     * is cosmetic -- the timestamp is display-only and does not affect logic.
     */
    function nowLabel() {
        var d  = new Date();
        var tz = d.toLocaleTimeString( [], { timeZoneName: "short" } ).split( " " ).pop();
        return (
            "Last scan: " +
            d.toLocaleDateString() +
            " " +
            d.toLocaleTimeString( [], { hour: "2-digit", minute: "2-digit" } ) +
            " " + tz
        );
    }

    /* ------------------------------------------------------------------ *
     * Commentary panel
     * ------------------------------------------------------------------ */

    /**
     * Render the scan summary / commentary panel.
     *
     * The commentary object comes from the backend and contains:
     *   status  {string} "clean" | "warning" | "critical"
     *   summary {string} Plain-language summary sentence.
     *   actions {Array}  List of recommended action strings.
     *
     * Premium tier: the backend generates richer AI commentary via the
     * Claude API. The free tier returns a simpler rule-based object in the
     * same shape. No client-side changes are needed when premium launches.
     *
     * @param {Object|null} commentary
     */
    function renderCommentary( commentary ) {
        var el = document.getElementById( "wpw-commentary" );
        if ( ! el ) return;
        if ( ! commentary ) { el.style.display = "none"; return; }

        var status  = commentary.status  || "clean";
        var summary = commentary.summary || "";
        var actions = commentary.actions || [];

        var statusClass = {
            clean:    "wpw-commentary-clean",
            warning:  "wpw-commentary-warning",
            critical: "wpw-commentary-critical"
        }[ status ] || "wpw-commentary-clean";

        var actionsHtml = "";
        if ( actions.length > 0 ) {
            actionsHtml = '<ul class="wpw-action-list">' +
                actions.map( function ( a ) {
                    return "<li>" + escHtml( a ) + "</li>";
                } ).join( "" ) +
            "</ul>";
        }

        el.className = "wpw-card wpw-commentary " + statusClass;
        el.innerHTML =
            '<div class="wpw-card-header">' +
                '<span class="wpw-card-title">Scan summary</span>' +
            "</div>" +
            '<div class="wpw-commentary-body">' +
                '<p class="wpw-commentary-summary">' + escHtml( summary ) + "</p>" +
                actionsHtml +
            "</div>";

        el.style.display = "block";
    }

    /* ------------------------------------------------------------------ *
     * Version notice banner
     * ------------------------------------------------------------------ */

    /**
     * Render the version update notice banner.
     *
     * ADR section 7 requirement:
     *   - type === "security": red banner, NON-DISMISSIBLE. If a close button
     *     is ever added for standard notices, security notices must be explicitly
     *     excluded from that logic.
     *   - type === "standard": yellow banner, dismissible (close button TBD).
     *
     * @param {Object|null} notice  { type: "security"|"standard", available_version: string }
     */
    function renderVersionNotice( notice ) {
        var el = document.getElementById( "wpw-version-notice" );
        if ( ! el ) return;
        if ( ! notice ) { el.style.display = "none"; el.innerHTML = ""; return; }

        var isSecurity = notice.type === "security";
        var icon       = isSecurity ? "dashicons-warning" : "dashicons-update";
        var cls        = isSecurity ? "wpw-version-notice-security" : "wpw-version-notice-standard";
        var title      = isSecurity
            ? "Security update available \u2014 " + escHtml( notice.available_version )
            : "Update available \u2014 "          + escHtml( notice.available_version );
        var msg = isSecurity
            ? "This update contains an important security fix. Update as soon as possible."
            : "A new version of WPPlugin Watch is available.";

        el.className = "wpw-version-notice " + cls;
        el.innerHTML =
            '<span class="dashicons ' + icon + '"></span>' +
            '<div class="wpw-version-notice-body">' +
                '<p class="wpw-version-notice-title">'   + title + "</p>" +
                '<p class="wpw-version-notice-message">' + msg   + "</p>" +
                '<a href="' + ( isSecurity ? "plugin-install.php?tab=search&s=wppluginwatch" : "plugins.php" ) + '" ' +
                    'class="wpw-version-notice-link">Update now &rarr;</a>' +
            "</div>";

        el.style.display = "flex";
    }

    /* ------------------------------------------------------------------ *
     * Scan handler
     * ------------------------------------------------------------------ */

    function runScan() {
        var btn    = document.getElementById( "wpw-scan-btn" );
        var label  = document.getElementById( "wpw-btn-label" );
        var status = document.getElementById( "wpw-scan-status" );

        if ( ! btn || btn.disabled ) return;

        btn.disabled = true;
        btn.classList.add( "wpw-scanning" );
        if ( label )  label.textContent  = "Scanning\u2026";
        if ( status ) { status.textContent = ""; status.style.color = ""; }

        var body = new URLSearchParams( {
            action: "wpw_run_scan",
            nonce:  wpwData.nonce
        } );

        fetch( wpwData.ajaxUrl, {
            method:  "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body:    body.toString()
        } )
        .then( function ( res ) {
            if ( ! res.ok ) throw new Error( "HTTP " + res.status );
            return res.json();
        } )
        .then( function ( json ) {
            if ( ! json.success ) {
                var msg = ( json.data && json.data.message ) || "Scan failed";

                // Rate limit response is shown inline as a soft notice, not an error.
                // Message text is defined server-side (see ADR-002).
                if ( msg.indexOf( "Daily scan limit" ) !== -1 ) {
                    if ( status ) {
                        status.style.color = "var(--wpw-warn, #996800)";
                        status.textContent = msg;
                    }
                    return;
                }
                throw new Error( msg );
            }

            var vulns         = ( json.data && json.data.vulnerabilities ) || {};
            var commentary    = ( json.data && json.data.commentary )      || null;
            var versionNotice = ( json.data && json.data.version_notice )  || null;

            var pv = vulns.plugins || {};
            var tv = vulns.themes  || {};
            var cv = vulns.core    || {};

            var pc = renderSection( "wpw-plugins-list", "wpw-badge-plugins", pv );
            var tc = renderSection( "wpw-themes-list",  "wpw-badge-themes",  tv );
            var cc = renderSection( "wpw-core-list",    "wpw-badge-core",    cv );

            var total  = pc + tc + cc;
            var severe = countSevere( pv ) + countSevere( tv ) + countSevere( cv );

            setText( "wpw-stat-vulns",    total  > 0 ? total  : "0" );
            setText( "wpw-stat-critical", severe > 0 ? severe : "0" );
            setText( "wpw-last-scan", nowLabel() );

            renderCommentary( commentary );
            renderVersionNotice( versionNotice );

            document.getElementById( "wpw-results" ).style.display = "block";

            if ( status ) {
                status.textContent = "Scan complete";
                setTimeout( function () { status.textContent = ""; }, 3000 );
            }
        } )
        .catch( function ( err ) {
            if ( status ) status.textContent = "Error: " + err.message;
        } )
        .finally( function () {
            btn.disabled = false;
            btn.classList.remove( "wpw-scanning" );
            if ( label ) label.textContent = "Scan now";
        } );
    }

    /* ------------------------------------------------------------------ *
     * Boot
     * ------------------------------------------------------------------ */

    document.addEventListener( "DOMContentLoaded", function () {
        var btn = document.getElementById( "wpw-scan-btn" );
        if ( btn ) btn.addEventListener( "click", runScan );

        // Render version notice from server-side transient cache.
        if ( wpwData.versionNotice ) {
            renderVersionNotice( wpwData.versionNotice );
        }

        // Restore last scan results from the server-side transient cache.
        // The cached object includes scan_timestamp (Unix seconds) so the
        // "Last scan" header can reflect the actual scan time rather than
        // the page load time.
        if ( wpwData.cachedResults ) {
            var cached = wpwData.cachedResults;
            var vulns  = cached.vulnerabilities || {};
            var pv = vulns.plugins || {};
            var tv = vulns.themes  || {};
            var cv = vulns.core    || {};

            var pc = renderSection( "wpw-plugins-list", "wpw-badge-plugins", pv );
            var tc = renderSection( "wpw-themes-list",  "wpw-badge-themes",  tv );
            var cc = renderSection( "wpw-core-list",    "wpw-badge-core",    cv );

            var total  = pc + tc + cc;
            var severe = countSevere( pv ) + countSevere( tv ) + countSevere( cv );

            setText( "wpw-stat-vulns",    total  > 0 ? total  : "0" );
            setText( "wpw-stat-critical", severe > 0 ? severe : "0" );

            // Restore the scan timestamp if the backend includes it in the
            // cached response. Fall back to the PHP-rendered value (already
            // in the DOM) if the field is absent.
            if ( cached.scan_timestamp ) {
                var d  = new Date( cached.scan_timestamp * 1000 );
                var tz = d.toLocaleTimeString( [], { timeZoneName: "short" } ).split( " " ).pop();
                setText( "wpw-last-scan",
                    "Last scan: " +
                    d.toLocaleDateString() + " " +
                    d.toLocaleTimeString( [], { hour: "2-digit", minute: "2-digit" } ) +
                    " " + tz
                );
            }

            renderCommentary( cached.commentary || null );
            document.getElementById( "wpw-results" ).style.display = "block";
        }
    } );

} )();
