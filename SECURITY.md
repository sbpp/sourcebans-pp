# Security

## Here are some steps to secure SourceBans++

1. Use HTTPS! HTTPS is Key if you don't want MitM Attacks happening. Many services such as StartSSL and Let's Encrypt provide free SSL Certificates.
2. Use a separate MySQL user just for SourceBans. Don't use the root MySQL user with SourceBans, make a separte SQL account that only has access to the SourceBans DB.
3. Make sure you do regular backups of your SourceBans Database.
4. Trust your Admins, don't give Admin access to your server(s) to a random stranger or a person you don't know.
5. Keep Steam OAuth-Only Login On

## I Found a Security Hole, what do I do?

1. Open an issue on the GitHub repo, with all nessesary info. (DON'T PUT HOW TO DO THE ACTUAL ATTACK, JUST THE IMPLICATIONS)
2. If possible, open a Pull Request with a fix for the said Security Hole while following the instrucions in CONTRIBUTING.md.
