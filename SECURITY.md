# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 1.x | yes |

## Reporting a vulnerability

Please do not open a public issue for security problems.
Use GitHub private vulnerability reporting in the repository:

https://github.com/SmoKE585/NBCom-Bitrix24-Laravel-Client/security/advisories/new

Include:

- affected version;
- reproduction steps;
- impact;
- whether a webhook token or personal data could be exposed.

## Webhook token

`BITRIX24_ENDPOINT` contains a secret. The package never writes it to the
capabilities snapshot, logs or exception context. If a token is leaked,
recreate the inbound webhook in Bitrix24 and update the secret.
