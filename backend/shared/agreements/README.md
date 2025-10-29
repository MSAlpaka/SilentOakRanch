# Agreements Storage Fixtures

The repository no longer ships binary contract PDFs. Instead, lightweight Markdown placeholders document the sample contract UUIDs used when developing the contracts dashboard.

During local testing the application continues to generate PDFs dynamically via Dompdf. When preparing staging or production environments, replace these Markdown files with the real contract artifacts (ideally stored outside of Git) and point `contracts_storage_path` to their location.
