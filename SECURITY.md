# Security Policy - Multi-Store & Warehouse Management System (MSWMS)

**Project:** POSWMS Backend  
**Framework:** Laravel 13.x (PHP 8.3)  
**Last Updated:** March 21, 2026

---

## 🔒 Reporting a Vulnerability

If you discover a security vulnerability within this application, please send an email to the development team at **security@example.com**. All security vulnerabilities will be promptly addressed.

### What to Include

- Description of the vulnerability
- Steps to reproduce the issue
- Potential impact
- Suggested fix (if any)

### Response Time

- **Initial Response:** Within 48 hours
- **Status Update:** Within 5 business days
- **Resolution Timeline:** Depends on severity (critical: 24-72 hours)

---

## ✅ Security Measures Implemented

### 1. Authentication & Authorization

- **Laravel Sanctum** for API token authentication
- **Role-Based Access Control (RBAC)** with 5 default roles
- **Permission-based authorization** for sensitive operations
- **Token expiration** and refresh mechanism
- **Multi-tenant isolation** - all queries scoped by tenant_id

### 2. Input Validation

- **Form Request classes** for validation
- **Type hinting** throughout the codebase
- **SQL injection prevention** via Eloquent ORM
- **XSS protection** via Laravel's automatic escaping
- **CSRF protection** for state-changing requests

### 3. Security Headers

The application implements the following security headers:

| Header | Value | Purpose |
|--------|-------|---------|
| X-Frame-Options | DENY | Prevent clickjacking |
| X-XSS-Protection | 1; mode=block | Enable browser XSS filter |
| X-Content-Type-Options | nosniff | Prevent MIME sniffing |
| Referrer-Policy | strict-origin-when-cross-origin | Control referrer information |
| Content-Security-Policy | Restricted | Limit resource loading |
| Strict-Transport-Security | max-age=31536000 | Force HTTPS (production) |
| Permissions-Policy | Restricted | Disable browser features |

### 4. Rate Limiting

Configured rate limiters to prevent abuse:

| Limiter | Requests/Minute | Use Case |
|---------|-----------------|----------|
| api | 60-120 | General API access |
| api-admin | 100-200 | Admin operations |
| api-heavy | 200-500 | Heavy operations |
| auth | 30-60 | Authentication attempts |

### 5. Audit Logging

- All sensitive operations logged
- User actions tracked with IP and user agent
- Configurable retention periods (90-365 days)
- Tamper-evident logs

### 6. Data Protection

- **Password hashing** with bcrypt (12 rounds)
- **API token hashing** for Sanctum tokens
- **Environment variable encryption** for sensitive config
- **Soft deletes** for data recovery
- **Tenant isolation** at database level

### 7. Dependency Security

- Regular `composer audit` checks
- Automated security updates via CI/CD
- Vulnerability monitoring

---

## 🛡️ Security Best Practices

### For Developers

1. **Never commit secrets**
   - Use `.env` files for sensitive data
   - Add `.env` to `.gitignore`
   - Use GitHub Secrets for CI/CD

2. **Validate all input**
   - Use Form Request classes
   - Never trust user input
   - Sanitize output

3. **Use Eloquent ORM**
   - Avoid raw SQL queries
   - Use parameter binding
   - Prevent SQL injection

4. **Implement authorization**
   - Check permissions in controllers
   - Use policies for model authorization
   - Never expose internal IDs

5. **Log security events**
   - Failed login attempts
   - Permission denials
   - Unusual activity

### For System Administrators

1. **Server Configuration**
   - Use HTTPS everywhere
   - Keep PHP and extensions updated
   - Configure firewall rules
   - Disable unnecessary services

2. **Database Security**
   - Use strong passwords
   - Limit database user privileges
   - Enable query logging
   - Regular backups

3. **Monitoring**
   - Set up log monitoring
   - Configure alerts for suspicious activity
   - Regular security audits
   - Monitor resource usage

---

## 📋 Security Checklist

### Pre-Deployment

- [ ] All dependencies updated
- [ ] `APP_DEBUG=false` in production
- [ ] Unique `APP_KEY` generated
- [ ] HTTPS configured
- [ ] Security headers enabled
- [ ] Rate limiting configured
- [ ] Database credentials secured
- [ ] File permissions set correctly

### Post-Deployment

- [ ] Verify HTTPS redirect
- [ ] Test authentication flows
- [ ] Verify authorization rules
- [ ] Check log file permissions
- [ ] Test backup restoration
- [ ] Verify monitoring is active

### Regular Maintenance

- [ ] Monthly dependency updates
- [ ] Quarterly security audit
- [ ] Annual penetration testing
- [ ] Review access logs weekly
- [ ] Update security documentation

---

## 🚨 Incident Response

### Severity Levels

| Level | Description | Response Time |
|-------|-------------|---------------|
| Critical | Data breach, remote code execution | Immediate (1-4 hours) |
| High | Authentication bypass, privilege escalation | 24 hours |
| Medium | XSS, CSRF, information disclosure | 72 hours |
| Low | Minor security improvements | Next release |

### Response Process

1. **Detection** - Identify and verify the security issue
2. **Containment** - Limit the impact
3. **Eradication** - Remove the vulnerability
4. **Recovery** - Restore normal operations
5. **Lessons Learned** - Document and improve

---

## 🔐 Encryption

### Data at Rest

- Passwords: bcrypt (12 rounds)
- API tokens: SHA-256 hashing
- Session data: Laravel encrypted cookies

### Data in Transit

- HTTPS/TLS 1.3 (production)
- HSTS enabled
- Secure cipher suites only

---

## 📊 Security Metrics

| Metric | Target | Current |
|--------|--------|---------|
| Dependency vulnerabilities | 0 | 0 |
| Failed login attempts (daily) | < 100 | - |
| Security incidents (monthly) | 0 | 0 |
| Time to patch critical issues | < 72 hours | - |

---

## 📚 Additional Resources

- [Laravel Security Documentation](https://laravel.com/docs/security)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)
- [CWE/SANS Top 25](https://cwe.mitre.org/top25/)

---

## 📝 Security Audit History

| Date | Type | Findings | Status |
|------|------|----------|--------|
| 2026-03-21 | Dependency Audit | 1 medium vulnerability (league/commonmark) | ✅ Fixed |
| 2026-03-21 | Security Headers Review | All headers implemented | ✅ Complete |
| 2026-03-21 | Authentication Review | Sanctum properly configured | ✅ Complete |

---

**Document Maintainer:** Development Team  
**Review Cycle:** Quarterly security reviews  
**Next Review:** June 2026
