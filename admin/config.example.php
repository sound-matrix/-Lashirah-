<?php
// Copy to config.local.php on Hostinger and replace with a securely generated password hash.
// In Hostinger terminal, create a hash using: php -r 'echo password_hash("REPLACE-WITH-YOUR-STRONG-PASSWORD", PASSWORD_DEFAULT), PHP_EOL;'
return ['username'=>'clientadmin', 'password_hash'=>'PASTE_PASSWORD_HASH_HERE'];
