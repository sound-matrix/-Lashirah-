# Lashirah — automatic Hostinger deployment

GitHub Actions deploys to Hostinger using SSH and rsync instead of FTPS. The previous FTPS run connected but its data socket reset.

## Verified Hostinger settings
- Host: `82.25.107.62`
- SSH port: `65002`
- User: `u419719929`
- Web root: `/home/u419719929/domains/lashirah.com/public_html`
- ED25519 host fingerprint (verified against Hostinger hPanel): `SHA256:sxb45CEQcolp6ddRf4NNDG9v5XgeeAxS2bxRUJBYMkc`
- Authorized public key installed in Hostinger as `lashirah-github-deploy`.

These nonsecret settings are configured in `.github/workflows/deploy-hostinger.yml`, with SSH host-key fingerprint verification.

## Only remaining GitHub Action secret
In repository Settings → Secrets and variables → Actions, create the repository secret `HOSTINGER_SSH_PRIVATE_KEY`.

Its value must be the **entire private key** from the Mac path `~/.ssh/lashirah_deploy`, including BEGIN/END lines. The owner should enter this secret **directly into GitHub**. Do not send the private key in chat or commit it to GitHub source files.

To copy the private key into the macOS clipboard locally:
```bash
pbcopy < ~/.ssh/lashirah_deploy
```

## Deployment behavior
- On merges to main, PHP syntax checks run first, then SSH authenticates and rsync uploads changed files.
- No remote deletion; server-side gallery content and private CMS config remain protected.
- The workflow requires the remote `rsync` binary.
- Previous `HOSTINGER_FTP_*` repository secrets are not used after this fix.
- The client's `admin/config.local.php` must be created privately on Hostinger and the admin subdomain configured separately.
