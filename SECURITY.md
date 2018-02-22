# Security

## Here are some steps to secure SourceBans++

1. Use HTTPS! HTTPS is Key if you don't want MITM Attacks happening. Services such as Let's Encrypt provide free SSL Certificates.
2. Use a separate MySQL user just for SourceBans. Don't use the `root` MySQL user with SourceBans, make a seperate SQL account that only has access to the SourceBans DB.
3. Make sure you do regular backups of your SourceBans Database.
4. Trust your Admins, don't give Admin access to your server(s) to a random stranger or a person you don't know.

## I Found a Security Hole, what do I do?

1. Open an issue on the GitHub repo, with all nessesary info.
2. If possible, open a Pull Request with a fix for the said Security Hole while following the instrucions in [CONTRIBUTING.md](https://github.com/sbpp/sourcebans-pp/blob/v1.x/CONTRIBUTING.md).
