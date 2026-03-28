# InfinityFree Deployment

1. Upload the project contents into your InfinityFree site `htdocs` folder.
2. Create a MySQL database from the InfinityFree control panel and import [`database/schema.sql`](C:/xampp/htdocs/mp/database/schema.sql).
3. For local development, keep using your current `.env`.
4. For InfinityFree, create a server-side `.env` file from [`.env.infinityfree.example`](C:/xampp/htdocs/mp/.env.infinityfree.example).
5. Set `APP_URL` to your real site URL, for example `https://your-domain.infinityfreeapp.com` or `https://your-custom-domain.com/subfolder`.
6. Set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` to the exact values from the InfinityFree control panel.
7. Upload that production `.env` file with the rest of the app files.
8. Make sure the `backend/uploads/` folder exists after upload. The app will try to create subfolders automatically when needed.
9. If email OTPs should work, open the app admin settings after login and enter valid external SMTP credentials.

Notes:

- `.htaccess` now preserves the `Authorization` header, which is important for token-based API requests on shared hosting.
- URL generation now handles HTTPS better behind shared-hosting proxies.
- Keep your local `.env` for XAMPP and use different production values on InfinityFree.
- InfinityFree database names and users usually start with a prefix like `epiz_`; copy them exactly as shown in the hosting panel.
