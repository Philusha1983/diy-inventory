# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| latest `main` branch | ✅ Yes |
| older commits | ❌ No — always use the latest code |

## Scope

This is a **self-hosted, single-user application** intended to run on a trusted local network or behind authentication. Key security assumptions:

- The app is password-protected but uses a **single shared password** (not per-user accounts)
- API keys (Gemini, OpenAI) are stored in the MySQL database — protect your database accordingly
- Uploaded images are stored on the local filesystem in `/uploads/`
- The app is not designed for public-internet exposure without additional hardening (reverse proxy, HTTPS, firewall)

See [DEPLOYMENT.md](DEPLOYMENT.md) for production security recommendations.

## Reporting a Vulnerability

If you discover a security vulnerability (e.g. SQL injection, path traversal, XSS, credential exposure), please **do not** open a public GitHub issue.

Instead, report it privately:

1. **Email**: Open a [GitHub Security Advisory](https://github.com/Philusha1983/diy-inventory/security/advisories/new) (preferred — keeps it private until patched)
2. **Or email** the maintainer directly via the contact on the GitHub profile

Please include:
- A description of the vulnerability and affected file(s)
- Steps to reproduce
- Potential impact
- Suggested fix (if you have one)

I aim to respond within **5 business days** and release a patch within **14 days** of confirmation.

## Known Limitations (by design)

- The password is stored as plaintext in `index.php` — this is intentional for simplicity. For production use, replace with `password_hash()` / `password_verify()`.
- API keys entered in the Settings UI are stored unencrypted in MySQL — restrict DB access accordingly.
- File uploads are validated by extension and MIME type but images are served directly — ensure the web server does not execute uploaded files.
