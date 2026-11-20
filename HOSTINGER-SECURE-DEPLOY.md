# Hostinger secure GitHub deployment (SSH + rsync)

The original GitHub Actions FTPS job reached Hostinger but failed when opening the data socket (`ECONNRESET`). Hostinger's published FTP instructions recommend **plain FTP**, which would send credentials unencrypted. This project instead uses SSH and rsync for encrypted transfers without a Hostinger paid Git integration.

## One-time Hostinger configuration
1. Open lashirah.com in Hostinger hPanel and go to **Advanced → SSH Access**. Check whether the hosting plan includes SSH, enable it, and record its IP / hostname, username, and port (not necessarily FTP port 21).
2. Create a dedicated Ed25519 deployment key pair locally: `ssh-keygen -t ed25519 -f lashirah_deploy -C lashirah-github-deploy`. Do not commit or share the private key. Install the **public** key in the hosting account's SSH authorized keys using the host's supported key management method.
3. Determine the **absolute remote document-root path** (the directory containing `index.html`). Hostinger paths typically contain `/domains/lashirah.com/public_html` but must be checked from SSH before setting the path.
4. Independently verify the host key fingerprint using Hostinger's authenticated control panel/support before adding its known_hosts line. Do not blindly trust `ssh-keyscan`.

## GitHub Actions repository secrets (Settings → Secrets and variables → Actions)
- `HOSTINGER_SSH_HOST`: SSH server IP/hostname from SSH Access.
- `HOSTINGER_SSH_USER`: SSH username from SSH Access.
- `HOSTINGER_SSH_PORT`: SSH port from SSH Access.
- `HOSTINGER_SSH_PRIVATE_KEY`: entire private key from `lashirah_deploy` (multiline).
- `HOSTINGER_SSH_KNOWN_HOSTS`: verified known_hosts line (for a nonstandard port, use `[host]:port key-type base64key`).
- `HOSTINGER_SSH_TARGET_PATH`: the absolute public_html path.

## Behavior
- On each push to main, PHP syntax validation runs, then SSH checks and an encrypted rsync deployment.
- Excluded from upload: repository metadata, server-only CMS secret config, CMS gallery data, and user-uploaded gallery images.
- No remote deletion is performed, so live CMS content stays intact.
- Do not run deployment until SSH access and key verification are configured.
- Existing `HOSTINGER_FTP_*` secrets are no longer used by the new workflow and may be removed after successful secure deployment.
