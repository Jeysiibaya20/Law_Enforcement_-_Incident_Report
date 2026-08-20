# Suspect & Witness Data Privacy Policy
**Alertara Law Enforcement & Incident Management System**
*Republic Act No. 10173 (Data Privacy Act of 2012) & PNP Law Enforcement Information Standards*

---

## 1. Purpose & Scope
This policy establishes strict data protection and confidentiality controls for **Suspect, Person of Interest, and Witness Information** handled within the Law Enforcement & Incident Report System. Its goal is to guarantee privacy-by-design, prevent unauthorized disclosure, and ensure full compliance with the National Privacy Commission (NPC) and law enforcement evidential rules.

---

## 2. What Data is Protected (Sensitive Personal Information)
- **Suspect Identifiers**: Full legal name, aliases, exact date of birth, age, physical marks, photo attachments, identity documents, and criminal history.
- **Witness & Complainant Records**: Full name, residential address, telephone/mobile numbers, email addresses, relationship to case, and video/audio interview recordings.
- **Confidential Statements**: Sworn testimonies, narrative accounts, and investigative notes.
- **Witness Protection Indicators**: Flagged witness status, protective custody details, and relocated residence information.

---

## 3. Data Masking & Redaction Standards
By default, all sensitive personal information displayed in general interfaces or non-investigative views is automatically masked:
- **Names**: Partially masked (e.g. `J*** D**` or pseudonymized reference `SUS-2026-0042`).
- **Contact Numbers**: Mid-range masked (e.g. `0917-***-5678`).
- **Residential Addresses**: Masked street level with general locality preserved (e.g. `[REDACTED STREET], Barangay Central, Quezon City`).
- **ID Numbers**: Redacted with last 4 digits (e.g. `***-***-8912`).
- **Protected Witnesses**: Full pseudonymity (`Witness Protected Ref #W-PROTECTED-09`) with blurred avatar and hidden contact channels.

---

## 4. Role-Based Access Control (RBAC)
1. **System Administrator / Chief of Police**: Full access for oversight, audit log analysis, and system security management.
2. **Lead Investigator / Case Officer**: Authorized to view unmasked records strictly for **assigned cases**. Every unmasking event requires an authenticated session and is permanently logged.
3. **Desk Officer / Standard Personnel**: Access to masked summaries only, sufficient for incident logging and duty turnover without exposing PII.
4. **External Partners (Group 2, Group 7, Surveillance)**: Data transmitted through external integrations strips PII and only includes standardized operational case references.

---

## 5. Security & Mandatory Audit Logging
- **Access Logs**: Every view, create, edit, unmask, soft-delete, and export action on suspect or witness records is recorded in `suspect_witness_privacy_audit` with the investigator's ID, action type, IP address, and timestamp.
- **Digital Evidence Storage**: Photo uploads and supporting documents are isolated in protected directories (`uploads/suspects/`, `uploads/evidence/`).
- **Soft Deletion**: Record deletions are preserved as soft-deletes (`deleted_at` timestamp) to prevent intentional tampering while removing the record from general view.

---

## 6. Retention, Deletion, and Legal Inquiries
Records are retained in accordance with statutory criminal justice retention periods. Data correction or legal disclosure requests must follow authorized court subpoenas or official PNP oversight requests.
