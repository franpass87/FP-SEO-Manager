# Fix Changelog

| ID | File | Line | Severity | Fix summary | Commit |
| --- | --- | --- | --- | --- | --- |
| ISSUE-001 | src/Analysis/Analyzer.php | 68 | high | Respect saved analyzer check toggles when selecting active checks | ba9b098 |
| ISSUE-002 | src/Admin/BulkAuditPage.php | 459 | medium | Add lightweight WP_Query flags to skip counts and heavy caches | cadb16c |
| ISSUE-003 | src/SiteHealth/SeoHealth.php | 240 | medium | Route Site Health PSI test through cached signals and handle errors | a47a494 |
| ISSUE-003 | src/Perf/Signals.php | 101 | medium | Cache PSI performance scores and expose them to callers | a47a494 |

## Summary

All audited issues (ISSUE-001 to ISSUE-003) were resolved, bringing the plugin's fix phase to completion on 2025-10-01.
