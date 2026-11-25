=== Prohibited Terms Scanner ===
Contributors: apos37
Tags: content moderation, search, compliance, media library, scanner
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.3.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Scan your site's posts, pages, comments, taxonomy terms, and media for prohibited terms or phrases so you can find and fix them before they go public.

== Description ==

**Prohibited Terms Scanner** searches your site for a list of terms or phrases you define, so you can identify content that shouldn't be public facing before it causes a problem.

Paste a list of terms or phrases, choose where to search, and run a scan. Results show you the term found, surrounding context, where it was found, and a direct link to it. Flagged results can be reviewed, marked as OK to ignore going forward, or cleared once resolved.

**Features:**
* Search post titles, main content, excerpts, comments, taxonomy term names and slugs, media filenames, and media alt text
* Optional file content scanning for plain text, CSV, Word (.docx), and PDF files (opt-in, off by default)
* ERI File Library integration (filenames, titles, descriptions, and file content)
* Case-sensitive and whole-word (strict) matching, set globally or per term
* Batched AJAX scanning with an animated progress bar and running item count, so large sites don't time out
* Cancel a scan in progress; results found so far are kept
* Results table with highlighted context snippets, post type/file type labels, source titles, direct links, and a highlight-and-blink indicator on the linked page
* Mark individual results as OK to exclude them from future scans, or clear them once resolved, including a Clear All option
* Omit specific posts, pages, media, or files from all future scans — directly from the Results page or via a quick action link (with bulk action support) on your admin list tables, viewable and manageable from a dedicated Omitted Sources page
* External Links scan that lists every outbound link on your public content, with notes and status filters
* Two access levels: View roles can review results and external links (read-only), Scan roles can also run scans, mark results, add notes, and omit sources
* Front-end shortcode for Scan roles to run scans without accessing wp-admin, styled to match the admin screens, with its own results, public pages, and external links panels
* Optional "Only Scan Public Pages" setting, with Deadbolt integration to skip restricted pages
* Inherits your Admin Help Docs colors when that plugin is active
* Optional warning when saving a post/page, uploading a file, or editing alt text that contains a monitored term (warns, does not block)
* Optional scheduled (daily/weekly/monthly) automatic scanning using your saved terms and search locations, with a status readout showing next/last run
* A count badge on the admin menu showing how many unresolved results need attention
* Import/export your term list and settings as JSON to move between sites, or reset settings to defaults
* Batch size and context snippet length are both configurable for performance tuning
* An Errors log for troubleshooting scan issues without digging through server logs
* Extendable: developers can register additional scan locations and use the actions and filters listed below
* Optional removal of all plugin data on uninstall (off by default)

This plugin does not automatically remove or redact anything it finds. It is a discovery tool to help you locate and manually review content that may need attention.

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/prohibited-terms-scanner/` directory, or install through the Plugins screen directly.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Prohibited Terms Scanner** in the admin menu to add your terms and run your first scan.
4. Optionally visit **Prohibited Terms Scanner > Settings** to adjust search locations, post types, batch size, and the save/upload warning.

== Frequently Asked Questions ==

= Does this plugin automatically remove or fix flagged content? =
No. It only finds and reports flagged terms so you can review and fix them yourself. Nothing is edited or redacted automatically.

= Will scanning slow down my site? =
Scans run in batches over AJAX and only while you're actively on the Scanner page, not in the background or on every page load. You can adjust the batch size in Settings if scans are timing out or running too slowly on a large site.

= What file types are supported for file content scanning? =
File content scanning (optional, off by default) currently supports:

* Plain text files (.txt)
* CSV files (.csv)
* Word documents (.docx)
* PDF files (.pdf)

Legacy Word documents (.doc, the pre-2007 binary format) are not supported out of the box, since reliably reading that format requires a separate third-party library this plugin doesn't bundle. Developers can add support for .doc or any other file type using the `ptscanner_file_content_mimes` filter along with their own extraction logic.

= What happens if I run a full scan again? =
A full scan clears previously flagged (unresolved) results before running, so your results always reflect the current state of your site. Results you've marked as OK are kept and are not re-flagged unless the same term is found in a new location.

= Can non-admin users run scans? =
Yes. In Settings, choose View roles (can see the Results and External Links tabs, read-only) and Scan roles (can also run scans, mark results, add notes, and omit sources). Scan roles can also use the `[prohibited_terms_scanner]` shortcode on any page. Settings and the Errors log are always limited to administrators. There is no public/logged-out option.

= Does uninstalling delete my data? =
Only if you choose to. Enable "Remove all data on uninstall" in Settings before deleting the plugin to remove its database tables, options, and post meta. Otherwise your terms, settings, and results are kept in case you reinstall. Scheduled scans are always unscheduled on uninstall.

= Is PDF scanning supported on all servers? =
PDF text extraction requires the PHP mbstring extension, which is enabled on most hosts. Scanned (image-only) PDFs have no text to read and cannot be searched.

= Can I omit specific content from being scanned? =
Yes. You can omit individual posts, pages, media, or files from all future scans, either from the Results page (which also clears any existing flagged results for that source) or via a quick "Omit from Terms Scanner" action link on your Posts, Pages, and Media list tables, with bulk action support. Omitted sources are managed from their own Omitted Sources page.

= Can scans run automatically on a schedule? =
Yes. In Settings, you can enable daily, weekly, or monthly scheduled scanning using your saved term list and search locations — no need to visit the Scanner page. This runs as a single background process via WordPress's cron system, so very large sites may want to increase their PHP execution time limit for cron requests, or test this on a staging site first. The Settings page shows the next scheduled run and the result of the last run.

= Does the save/upload warning stop me from saving or uploading? =
No. It warns you that a monitored term was found so you can double check before continuing, but it will not block you from proceeding.

== Third-Party Libraries ==

This plugin bundles the following library:

* **PdfParser** by Sebastien Malot, version 2.12.5 - https://github.com/smalot/pdfparser
  License: LGPL-3.0 (see `vendor/pdfparser/LICENSE.txt` and `vendor/pdfparser/GPL-3.0.txt`)
  Used to extract text from PDF files for optional file content scanning. The library was namespace-prefixed and relocated to avoid conflicts with other plugins; all modifications are described in `vendor/pdfparser/NOTICE.txt`.

== Screenshots ==
1. Add terms and choose search locations on the Scanner page
2. Live progress bar while a scan runs
3. Results table with highlighted context, source links, notes, and actions
4. External Links tab with flagged links highlighted
5. Settings page with search locations, matching, scheduling, access roles, and data options
6. Front-end shortcode scanner for staff with Scan access

== Changelog ==

= 1.3.0 =
* Update: New admin design with a shared header and tabs (Scanner, Results, External Links, Omitted Sources, Errors, Settings), inheriting Admin Help Docs colors when active
* Update: New Settings tab with import, export, and reset (replaces the Import/Export page)
* Update: Added View and Scan roles; View roles get read-only access to Results and External Links (replaces the shortcode roles setting, which is migrated automatically)
* Update: Added External Links scanning with notes and status filters
* Update: Added "Only Scan Public Pages" setting and Deadbolt integration (replaces the Role Visibility integration)
* Update: Restyled the front-end shortcode to match the admin screens
* Update: Added optional removal of all plugin data on uninstall; uninstall now also clears the external links table, post meta, transients, and the scheduled scan
* Update: Added developer hooks, including `ptscanner_file_text_replacements`, `ptscanner_can_view`, `ptscanner_can_scan`, and `ptscanner_is_public_post`
* Update: Added excluded post types filters
* Tweak: Results dates now use your site's date and time format
* Fix: Matches in files missed because of ligatures, curly quotes, special spaces, non-UTF-8 text, or Word line breaks
* Fix: Phrases split across two PDF pages were not found
* Fix: Missing or unreadable files were skipped silently; they are now logged to the Errors page
* Fix: Fatal errors during file scans now report the file being processed, and stale tracking data is cleared
* Fix: Restricted pages were hidden from scheduled scans by access-control plugins
* Fix: First "Un-Omit" click on an omitted source re-omitted it instead
* Fix: Front-end External Links pagination also reloaded the Results panel
* Fix: Results filters dropped terms containing "|" and filtered pagination lost the active filters
* Fix: Security hardening of the media usage list output
* Fix: Plugin Check compliance (escaping, nonces, translator comments, enqueued assets)
* Fix: The External Links table was never created and database upgrades never ran
* Fix: Front-end shortcode tables and boxes overflowing on themes with narrow content areas

= 1.2.1 =
* Fix: Missing Location column in the Results page table that caused Source, Date, and Actions to misalign
* Fix: Shortcode output leaking into AJAX scan responses on certain sites, corrupting batch results
* Fix: Batch failures during a scan skipping the rest of that location type instead of continuing
* Tweak: Improved scan error messages to be more descriptive and human-readable, including which shortcode tag was involved when applicable
* Fix: HTML entities (e.g. "&amp;") displaying incorrectly in source titles on the front-end results table
* Fix: Various Plugin Check compliance fixes (input sanitization, output escaping, direct database call annotations, global variable prefixing)

= 1.2.0 =
* Update: Added an Omitted Sources system: omit posts, pages, media, or files from all future scans, via the Results page or list table quick/bulk actions
* Tweak: Added source titles to the results table
* Update: Added a Clear All action for results and for term lists
* Tweak: Added collapsible term list accordions that auto-collapse when terms already exist
* Fix: Various bug fixes from real-world testing

= 1.1.0 =
* Update: Added file content scanning for PDF and Word (.docx) documents
* Update: Added ERI File Library integration (titles, filenames, descriptions, and file content)
* Update: Added a results counter badge on the admin menu
* Update: Added optional scheduled (cron) scanning with status display

= 1.0.0 =
* Initial release