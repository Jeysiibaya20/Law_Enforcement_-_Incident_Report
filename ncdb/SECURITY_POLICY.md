# NCDB Security Policy & Compliance Guidelines

## 📋 Document Version
- **Version**: 1.0
- **Last Updated**: January 2026
- **Status**: Active
- **Classification**: Internal Use

---

## 🔒 Security Principles

### 1. Encryption First
- All sensitive data is encrypted at rest using AES-256-CBC
- API credentials encrypted before storage
- Query parameters encrypted in audit logs
- No plaintext storage of sensitive information

### 2. Least Privilege
- Minimum required permissions granted
- Role-based access control (Officer, Admin)
- No direct database access for users
- Data access logged and monitored

### 3. Audit Everything
- Every NCDB operation logged
- User tracking for all actions
- Timestamp on all records
- Immutable audit trail

### 4. Defense in Depth
- Input validation at application level
- Parameterized queries prevent SQL injection
- Output escaping prevents XSS
- Rate limiting prevents brute force
- IP tracking for anomaly detection

---

## 🚨 Threat Detection & Response

### Suspicious Activity Detection

**Automated Detection Triggers**:
- Query size > 1000 characters: **MEDIUM** threat
- Result limit > 10,000 rows: **HIGH** threat
- Export operations: **MEDIUM** threat
- After-hours access (6 PM - 6 AM): **LOW** threat
- Rate limit exceeded: **HIGH** threat
- Multiple suspicious actions (5+/hour): **CRITICAL** threat

### Threat Levels
1. **NONE** - Normal operation
2. **LOW** - Minor anomaly, monitor
3. **MEDIUM** - Concerning pattern, review
4. **HIGH** - Likely unauthorized, alert
5. **CRITICAL** - Definite threat, block

### Automated Response
- **CRITICAL**: Immediate alert + user session review
- **HIGH**: Alert logged + administrator notification
- **MEDIUM**: Alert logged + scheduled review
- **LOW**: Alert logged + analytics only

### Manual Review Process
1. Security officer reviews alert
2. Determine if legitimate use
3. Document findings
4. Escalate if needed
5. Update policies based on patterns

---

## 🔑 Key Management

### Encryption Keys

**Primary Key (NCDB_ENCRYPTION_KEY)**
```
Purpose: AES-256-CBC encryption for all sensitive data
Length: 32 bytes (256 bits)
Format: Hexadecimal string
Rotation: Quarterly (every 90 days)
Backup: Encrypted separately in secure location
```

**Key Generation**
```bash
# Linux/Mac
php -r "echo bin2hex(openssl_random_pseudo_bytes(32));"

# Windows PowerShell
$bytes = New-Object 'System.Byte[]' 32
[Security.Cryptography.RNGCryptoServiceProvider]::new().GetBytes($bytes)
[BitConverter]::ToString($bytes) -replace '-',''
```

**Key Storage**
- Store in environment variable: `NCDB_ENCRYPTION_KEY`
- Never commit to version control
- Keep separate from application code
- Backup in secure vault
- Document access restrictions

**Key Rotation Procedure**
1. Generate new key
2. Test with test data
3. Schedule rotation window
4. Re-encrypt existing data
5. Archive old key (encrypted)
6. Update environment variable
7. Restart application
8. Verify functionality

---

## 🛡️ Access Control

### Role-Based Access

#### Officer Role
**Permissions**:
- View: Read all records
- Verify: Perform NCDB verification
- Check: Check for duplicates
- Report: View own access logs

**Restrictions**:
- Cannot modify connections
- Cannot access other user logs
- Cannot change settings
- Cannot delete records

#### Admin Role
**Permissions**:
- All Officer permissions
- Create: Add NCDB connections
- Update: Modify connection settings
- Delete: Remove connections
- Configure: Change system settings
- Review: Access all audit logs
- Test: Run system tests

**Restrictions**:
- Cannot delete audit logs
- Must follow change approval process
- All actions logged and monitored

### Session Management
- Session timeout: 30 minutes of inactivity
- Force re-authentication for sensitive operations
- Single session per user (prevent multiple logins)
- Log all login attempts
- Block after 5 failed attempts (30 min cooldown)

---

## 📊 Audit Logging Policy

### What Gets Logged

**All Operations**:
- Query execution (type, parameters, result count)
- Record verification (record type, result)
- Duplicate flagging (records involved)
- Connection testing
- Settings changes
- Administrative actions

**User Information**:
- User ID
- IP address
- User agent
- Session information
- Timestamp (precise to millisecond)

**Operation Details**:
- Action type
- Query type
- Result count
- Execution time
- Success/failure status
- Error messages (sanitized)

**Security Information**:
- Suspicious activity flag
- Threat level assessment
- Risk flags
- Anomaly notes

### Log Retention

**Access Logs**:
- Minimum retention: 365 days
- Searchable for 90 days
- Archived after 90 days
- Deletion only after 1 year

**Alert Logs**:
- Indefinite retention
- Never automatically deleted
- Regularly reviewed for patterns
- Used for security audits

**Test Logs**:
- Retained for 90 days
- Automatic cleanup after expiration
- Separate from access logs

### Log Access

**Who Can Access**:
- Audit officers (read-only)
- Security team (analysis)
- System administrators (maintenance)
- Not accessible to end users

**Access Logging**:
- All log access is logged
- IP and timestamp recorded
- Purpose required
- Audit trail of audits

---

## 🔐 Data Protection

### Data Classification

**Public**:
- Case numbers
- General incident types
- Non-identifying information

**Internal**:
- Officer names and assignments
- Department information
- General case details

**Sensitive**:
- Personal identifying information (names, addresses, dates of birth)
- Contact numbers
- ID numbers
- Criminal history details
- NCDB query results

**Highly Sensitive**:
- API credentials
- Encryption keys
- Individual access logs
- Threat assessment details
- Audit information

### Data Protection Requirements

**Sensitive Data**:
- ✓ Encrypted at rest
- ✓ Encrypted in transit (HTTPS only)
- ✓ Access logged
- ✓ Retention limited
- ✓ Purpose limited

**Highly Sensitive**:
- ✓ Encrypted with highest grade
- ✓ Never logged in plaintext
- ✓ Extreme access control
- ✓ Encryption keys separated
- ✓ Regular integrity checks

---

## 🚨 Incident Response

### Security Incident Definition
- Unauthorized access attempt
- Data breach
- Suspicious activity threshold exceeded
- Connection failures
- Anomalous behavior patterns
- Encryption key compromise

### Response Procedure

**Step 1: Detection & Alert (Immediate)**
- Automated detection triggers alert
- Alert logged with full context
- Security team notified
- Time: < 1 minute

**Step 2: Containment (First 5 Minutes)**
- Block suspicious user if critical threat
- Preserve evidence (don't delete logs)
- Isolate affected systems if needed
- Notify supervisor

**Step 3: Investigation (First Hour)**
- Review audit logs
- Determine scope of incident
- Identify root cause
- Document findings
- Contact affected parties if needed

**Step 4: Eradication (Within 24 Hours)**
- Remove threat
- Patch vulnerabilities
- Reset credentials if needed
- Verify threat removal

**Step 5: Recovery (Within 48 Hours)**
- Restore normal operations
- Verify system functionality
- Perform security tests
- Confirm incident resolved

**Step 6: Post-Incident (Within 1 Week)**
- Complete incident report
- Conduct root cause analysis
- Implement preventive measures
- Update security policies
- Train staff on lessons learned

---

## 📋 Compliance Requirements

### Data Privacy (GDPR/Local Laws)
- **Personal Data**: Minimal collection and retention
- **Consent**: Required for data usage
- **Deletion**: Honor data deletion requests
- **Transparency**: Inform users of data practices
- **Audit**: Maintain records of consent and deletion

### Financial/Legal Compliance
- **Audit Trail**: Maintain for 1+ year
- **Access Control**: Restrict to authorized personnel
- **Change Log**: Document all modifications
- **Retention**: Follow legal requirements
- **Encryption**: Protect sensitive data

### Law Enforcement Specific
- **Chain of Custody**: Track evidence access
- **Warrant Requirements**: Log legal demands
- **Record Accuracy**: Maintain data integrity
- **Access Justification**: Require legitimate purpose
- **Minimization**: Only access necessary data

---

## ✅ Security Checklist

### Monthly
- [ ] Review access logs for anomalies
- [ ] Check connection status
- [ ] Verify encryption functionality
- [ ] Test backup and recovery
- [ ] Review user access list

### Quarterly
- [ ] Rotate encryption keys
- [ ] Conduct security audit
- [ ] Review and update policies
- [ ] Test incident response procedures
- [ ] Review and clean old logs

### Annually
- [ ] Full security assessment
- [ ] Penetration testing
- [ ] Policy review and update
- [ ] Staff security training
- [ ] Compliance audit

---

## 📞 Security Contacts

**Security Incidents**:
- Email: security@your-domain.com
- Phone: +1-xxx-xxx-xxxx
- On-call: [Contact info]

**Audit & Compliance**:
- Department: Compliance Office
- Email: compliance@your-domain.com
- Officer: [Name/Contact]

**Key Management**:
- System Admin: [Name]
- Backup Contact: [Name]

---

## 📖 References

### Internal Documents
- [README.md](./README.md) - Feature documentation
- [INSTALLATION.md](./INSTALLATION.md) - Setup guide
- [QUICKSTART.md](./QUICKSTART.md) - Quick reference

### External Standards
- OWASP Top 10 (https://owasp.org/www-project-top-ten/)
- NIST Cybersecurity Framework
- ISO 27001 - Information Security
- GDPR - General Data Protection Regulation
- Local Data Protection Laws

### Technologies
- PHP Security Best Practices
- MySQL Security Guidelines
- OpenSSL Encryption Standards
- OAuth 2.0 / API Security

---

## 🔄 Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | Jan 2026 | Initial policy document |

---

## ⚠️ Disclaimer

This security policy outlines best practices and intended security measures. No system is 100% secure. Regular audits, updates, and monitoring are essential. Non-compliance with this policy may result in disciplinary action or legal consequences.

**All personnel must acknowledge understanding of this policy before accessing NCDB systems.**

---

**Status**: ACTIVE ✓  
**Next Review**: January 2027  
**Classification**: Internal Use Only
