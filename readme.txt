=== Prohibited Terms Scanner ===
Contributors: apos37
Tags: content moderation, search, compliance, media library, scanner
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Scan your site's posts, pages, comments, taxonomy terms, and media for prohibited terms or phrases so you can find and fix them before they go public.

== Description ==

**Prohibited Terms Scanner** searches your site for a list of terms or phrases you define, so you can identify content that shouldn't be public facing before it causes a problem.

Paste a list of terms or phrases, choose where to search, and run a scan. Results show you the term found, surrounding context, where it was found, and a direct link to it. Flagged results can be reviewed, marked as OK to ignore going forward, or cleared once resolved.

**Features:**
* Search post titles, main content, excerpts, comments, taxonomy term names and slugs, media filenames, and media alt text
* Optional file content scanning for plain text, CSV, Word (.docx), and PDF files (opt-in, off by default)
* Case-sensitive and whole-word (strict) matching, set globally or per term
* Batched AJAX scanning with a live progress bar, so large sites don't time out
* Cancel a scan in progress; results found so far are kept
* Results table with context snippets, direct links, and a highlight-and-blink indicator on the linked page
* Mark individual results as OK to exclude them from future scans, or clear them once resolved
* Omit specific pages, posts, or files from being scanned
* Front-end shortcode for staff to run scans without accessing wp-admin, restricted to roles you choose
* Optional warning when saving a post/page, uploading a file, or editing alt text that contains a monitored term (warns, does not block)
* Import/export your term list and settings as JSON to move between sites
* Batch size and context snippet length are both configurable for performance tuning
* Extendable: developers can register additional scan locations for their own integrations
* Optional scheduled (daily/weekly/monthly) automatic scanning using your saved terms and search locations, with a status readout showing next/last run

This plugin does not automatically remove or redact anything it finds. It is a discovery tool to help you locate and manually review content that may need attention.

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/prohibited-terms-scanner/` directory, or install through the Plugins screen directly.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Prohibited Terms** in the admin menu to add your terms and run your first scan.
4. Optionally visit **Prohibited Terms > Settings** to adjust search locations, post types, batch size, and the save/upload warning.

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
Yes, using the `[prohibited_terms_scanner]` shortcode on any page. Access is restricted to the roles you choose in Settings; there is no public/logged-out option.

= Does the save/upload warning stop me from saving or uploading? =
No. It warns you that a monitored term was found so you can double check before continuing, but it will not block you from proceeding.

= Can scans run automatically on a schedule? =
Yes. In Settings, you can enable daily, weekly, or monthly scheduled scanning using your saved term list and search locations — no need to visit the Scanner page. This runs as a single background process via WordPress's cron system, so very large sites may want to increase their PHP execution time limit for cron requests, or test this on a staging site first. The Settings page shows the next scheduled run and the result of the last run.

== Screenshots ==
1. Add terms and choose search locations on the Scanner page
2. Live progress bar while a scan runs
3. Results table with context, links, and resolution actions
4. Settings page with search locations, post types, and performance options

== Changelog ==

= 1.1.0 =
* Added file content scanning for PDF and Word (.docx) documents
* Added ERI File Library integration (filenames, descriptions, and file content)
* Added a results counter badge on the admin menu
* Added optional scheduled (cron) scanning with status display

= 1.0.0 =
* Initial release