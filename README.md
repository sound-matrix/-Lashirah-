# Lashirah — Official Website

Interactive athlete portfolio for Nandavaram Lashirah, prepared for hosting on Hostinger.

## Project layout

- `index.html` — website entry point, styling, JavaScript, and page content
- `assets/gallery-media.css` — embedded gallery images and poster styles (split from the prototype to make GitHub uploads manageable)
- `assets/intro-video.js` — introduction video data used by the existing intro player
- `.gitignore` — local development files to exclude

**No npm, build command, Vercel, or Cloudflare account is required.**

## Deploy to Hostinger

1. Open Hostinger hPanel and activate the client's hosting plan.
2. Open File Manager for the site.
3. Upload the contents of this repository into `public_html/`, preserving the `assets/` directory.
4. Verify `index.html` is directly inside `public_html/`.
5. Confirm the domain points to Hostinger hosting and enable SSL.
6. Test desktop/mobile navigation, intro video, gallery, and contact actions.

## Before launch

- Replace the prototype contact email `replace@example.com` with the approved actual recipient.
- The contact form currently opens the visitor's mail application. It is not a server-side inbox form.
- The browser editor's PIN is client-side and is **not** a secure staff authentication system.
- The built-in editor stores local changes in that browser; edits need publishing/uploading to appear for other visitors.
- Payment processing is not integrated yet. Integrate via backend or a provider-hosted checkout. Never put payment secret keys in frontend files.

The original approved visuals and content have been retained. The heavy embedded gallery styles and intro media were separated into assets solely to facilitate GitHub and Hostinger deployment.

## Client Admin & YouTube Gallery upgrade

For the server-side client administration, Hostinger PHP setup, private credentials, gallery upload permissions, and admin subdomain mapping, see [HOSTINGER-ADMIN-SETUP.md](HOSTINGER-ADMIN-SETUP.md).

- Admin: `https://admin.CLIENT-DOMAIN` (configure DNS, Hostinger subdomain document root and SSL)
- Images: `/gallery.php?type=photos`
- YouTube videos: `/gallery.php?type=videos`

Both galleries are independent; the new CMS publishes uploads and YouTube embeds to every visitor. Existing preloaded prototype content remains in the homepage and is not migrated automatically.
