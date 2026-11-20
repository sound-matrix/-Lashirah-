# Lashirah — secure admin and separated galleries

This upgrade requires Hostinger **PHP hosting**, not the static HTML-only upload mode.

## Hostinger deployment
1. Deploy the entire repository to the client's primary domain document root (typically `public_html`).
2. In Hostinger hPanel → Domains → Subdomains, create `admin.clientdomain.com` and set its **document root** to the existing `public_html/admin` folder. Use the client's actual registered domain. The subdomain is not automatically live until configured and SSL is active.
3. From `admin/config.example.php`, make `admin/config.local.php` on Hostinger (do not commit this file). Generate the password hash with `php -r 'echo password_hash("YOUR_UNIQUE_STRONG_PASSWORD", PASSWORD_DEFAULT), PHP_EOL;'`. Paste the hash and desired username into the config.
4. Confirm the server can write to `storage/` and `uploads/gallery/`. Restrict access to `storage` (the included Apache .htaccess denies reads); for Nginx-only deployments place storage outside webroot and update `LASHIRAH_STORAGE` accordingly.
5. Enable HTTPS/SSL on both the website and the admin subdomain.
6. Visit `https://admin.clientdomain.com` to sign in and upload photos or paste YouTube URLs.
7. Verify `https://clientdomain.com/gallery.php?type=photos` and `https://clientdomain.com/gallery.php?type=videos`.

## Notes
- The legacy HTML prototype's browser-only editor is not a secure content publishing system. Use the new server-side admin for gallery updates.
- Image and video galleries are separate pages and separate content collections. YouTube links support `youtu.be`, `youtube.com/watch`, Shorts, embed and live links.
- All gallery content is stored on the Hostinger server and is not synced into GitHub. Back up `storage/gallery.json` and `uploads/gallery/` routinely.
- Existing hard-coded gallery items in the homepage remain intact unless separately migrated; the new CMS manages newly published media.
- Server-side authentication uses PHP sessions, a password hash, and a CSRF token. Configure SSL and a strong password. Rate limiting/IP protections are recommended at the hosting layer.
- The existing prototype contact form and payment gateway have not been changed.
