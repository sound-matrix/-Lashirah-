# Lashirah enquiries — authenticated mail

Recipient (server-only): `nandavaramhassan@gmail.com`.

The website previously used unauthenticated PHP `mail()`, which frequently lands in spam. The new sender uses authenticated Hostinger SMTP when an actual domain mailbox and credentials are configured. The client can then click **Reply** in Gmail, which uses the visitor's email address through the `Reply-To` header.

## Configure the sending mailbox

1. In Hostinger hPanel, create a real mailbox under `lashirah.com` (for example `contact@lashirah.com`). This must exist as a real mailbox, not just an invented From label.
2. In the domain's Email DNS settings, verify Hostinger's supplied SPF and DKIM records, plus an appropriate DMARC policy. Do not override an existing valid SPF record; avoid duplicate SPF records.
3. Via Hostinger SSH, outside `public_html`, create `/home/u419719929/domains/lashirah.com/private/mail.local.php` with:
   ```php
   <?php
   return [
       'username' => 'YOUR_ACTUAL_MAILBOX@lashirah.com',
       'password' => 'YOUR_HOSTINGER_MAILBOX_PASSWORD',
   ];
   ```
   Use a private editor. Never paste the password in chat or GitHub. Restrict permissions with `chmod 600` after creation.
4. Send a legitimate test enquiry from the website and inspect the message in the recipient's Gmail. Confirm Reply addresses the visitor correctly.
5. In Gmail's **Show original**, check SPF, DKIM and DMARC statuses. Inbox placement is determined by Gmail; it cannot be guaranteed.

SMTP server `smtp.hostinger.com`, port 465 (encrypted). The GitHub deployment workflow installs PHPMailer and deploys `vendor/`; no SMTP password is stored in GitHub. Without a mailbox configuration file, the old PHP `mail()` fallback remains operational but **does not solve spam placement**. Configuration is required to finish the upgrade.

No changes to the contact form's visible address; destination remains private.
