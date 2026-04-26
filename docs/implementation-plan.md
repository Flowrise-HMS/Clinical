# Patient Module Implementation Plan

**Document Status:** Draft
**Last Updated:** April 2026
**Module:** FlowRise HMS Patient Module

---

## 1. Executive Summary

This document outlines the implementation roadmap for the Patient module of FlowRise HMS. It consolidates all design decisions and technical implementation details.

### Business Context

The Patient module is the **core foundation** of the entire HMS. Every clinical, billing, and operational action links back to a patient.

### Core Entities

| Entity | Purpose |
|--------|--------|
| **Patient** | Core entity with demographics, contact, address |
| **PatientIdentifier** | MRN, NHIS, Passport, etc. |
| **EmergencyContact** | Next of kin, emergency contacts |
| **PatientSchool** | School-based health records |

---

## 2. Database Architecture

### 2.1 Tables

```
patients
├── patient_identifiers (MRN, NHIS, Passport, etc.)
├── emergency_contacts (next of kin)
└── patient_schools (school-based records)
```

### 2.2 Encrypted Fields

The following fields are encrypted:
- `date_of_birth`
- `phone`
- `email`

---

## 3. Module Structure

### 3.1 Models

| Model | File |
|-------|------|
| Patient | `Modules/Patient/app/Models/Patient.php` |
| PatientIdentifier | `Modules/Patient/app/Models/PatientIdentifier.php` |
| EmergencyContact | `Modules/Patient/app/Models/EmergencyContact.php` |
| PatientSchool | `Modules/Patient/app/Models/PatientSchool.php` |

### 3.2 Services

| Service | File | Methods |
|---------|------|--------|
| PatientService | `Classes/Services/PatientService.php` | all, getActive, find, findByMrn, create, update, delete |
| PatientIdentifierService | `Classes/Services/PatientIdentifierService.php` | generateMrn, generateUuid, validateIdentifier |
| EmergencyContactService | `Classes/Services/EmergencyContactService.php` | addContact, updateContact, removeContact |
| PatientSearchService | `Classes/Services/PatientSearchService.php` | search, getSearchableFields |
| PatientSchoolService | `Classes/Services/PatientSchoolService.php` | enroll, updateEnrollment, withdraw |

### 3.3 Enums

| Enum | Purpose |
|------|---------|
| Gender | male, female, other |
| BloodType | A+, A-, B+, B-, AB+, AB-, O+, O- |
| MaritalStatus | single, married, divorced, widowed |
| IdentifierType | MRN, NHIS, PASSPORT, DRIVERS_LICENSE |
| RelationshipType | SPOUSE, PARENT, SIBLING, CHILD |
| EducationLevel | NONE, PRIMARY, SECONDARY, TERTIARY |
| SchoolType | PRIMARY, JHS, SHS |
| DocumentType | NHIS, VOTERS_ID, PASSPORT, DRIVERS_LICENSE |

---

## 4. Filament Resources

### 4.1 PatientResource

**Cluster:** Patient Cluster
**File:** `Filament/Clusters/Patient/Resources/Patients/PatientResource.php`

**Pages:**
- ListPatients - List all patients with search/filters
- CreatePatient - Register new patient (wizard form)
- EditPatient - Update patient profile
- ViewPatient - View full patient details
- ListPatientActivities - Patient activity log

### 4.2 Form Schema

**File:** `Schemas/PatientForm.php`

**Wizard Steps:**
1. Demographics - Name, DOB, gender, blood type
2. Contact - Phone, email
3. Address - Full address
4. Identifiers - MRN, NHIS, etc.
5. Emergency Contact - Next of kin
6. School (optional) - School-based records

### 4.3 Infolist Schema

**File:** `Schemas/PatientInfolist.php`

Displays:
- Demographics card
- Identifiers card
- Contact card
- Emergency contacts card

---

## 5. Key Features Implemented

### 5.1 Patient Registration

- Multi-step wizard form
- Auto-generated MRN (format: FR-YYYYMMDD-XXXXX)
- Global UUID for interoperability
- Encrypted PII (phone, email, DOB)
- Soft deletes

### 5.2 Patient Identifiers

- MRN (Medical Record Number) - primary
- NHIS Number
- Passport
- Driver's License
- Custom identifier types

### 5.3 Search

- Global search across name, MRN, phone, email
- Searchable via PatientSearchService

### 5.4 Relationships

| Relationship | Model |
|--------------|-------|
| User account | User (optional) |
| Identifiers | PatientIdentifier |
| Emergency contacts | EmergencyContact |
| Clinical data | Encounter, VitalSign, ClinicalNote |
| Allergies | Allergy |

---

## 6. Implementation Checklist

### 6.1 What's Done ✅

| Item | Notes |
|------|-------|
| Database migrations | patients, patient_identifiers, emergency_contacts, patient_schools |
| Patient model | With HasUuids, HasAddress, HasContact, SoftDeletes |
| PatientIdentifier model | Unique constraint on (patient_id, type, value) |
| EmergencyContact model | Full relationship tracking |
| PatientSchool model | School enrollment tracking |
| Enums | Gender, BloodType, MaritalStatus, IdentifierType, etc. |
| PatientService | Full CRUD + search + pagination |
| PatientIdentifierService | MRN auto-generation |
| EmergencyContactService | Full relationship CRUD |
| PatientSearchService | Global search |
| PatientSchoolService | School enrollment |
| PatientResource | Full Filament resource |
| PatientForm | Multi-step wizard |
| PatientInfolist | Display cards |
| PatientsTable | Column configuration |
| Policies | PatientPolicy |
| Events | PatientRegistered, PatientUpdated, PatientDeactivated, PatientDeceased |
| Observers | PatientObserver |
| Factories | All models have factories |

### 6.2 What's Pending ⏳

| Item | Priority | Notes |
|------|----------|-------|
| Media uploads for patient photo | MEDIUM | Spatie Media Library |
| Patient merge/deduplication | LOW | Merge duplicate patients |
| Bulk import | LOW | CSV import |
| Export to FHIR | LOW | FHIR Patient resource |

---

## 7. Key Design Decisions

### 7.1 MRN Generation

Format: `FR-YYYYMMDD-XXXXX`

- FR prefix (FlowRise)
- Date of registration
- Sequential number

### 7.2 Global UUID

Each patient gets a global UUID for interoperability with other systems (FHIR compliance).

### 7.3 Encrypted Fields

PII fields encrypted at rest:
- `date_of_birth`
- `phone`
- `email`

### 7.4 Soft Deletes

Patients are soft-deleted (can be restored), maintaining historical data integrity.

---

## 8. File Structure

```
Modules/Patient/
├── app/
│   ├── Classes/Services/
│   │   ├── PatientService.php
│   │   ├── PatientIdentifierService.php
│   │   ├── EmergencyContactService.php
│   │   ├── PatientSearchService.php
│   │   └── PatientSchoolService.php
│   ├── Models/
│   │   ├── Patient.php
│   │   ├── PatientIdentifier.php
│   │   ├── EmergencyContact.php
│   │   └── PatientSchool.php
│   ├── Enums/
│   │   ├── Gender.php
│   │   ├── BloodType.php
│   │   ├── MaritalStatus.php
│   │   ├── IdentifierType.php
│   │   ├── RelationshipType.php
│   │   └── ...
│   ├── Filament/
│   │   └── Clusters/Patient/
│   │       └── Resources/Patients/
│   │           ├── PatientResource.php
│   │           ├── Schemas/
│   │           │   ├── PatientForm.php
│   │           │   └── PatientInfolist.php
│   │           ├── Tables/
│   │           │   └── PatientsTable.php
│   │           └── Pages/
│   │               ├── ListPatients.php
│   │               ├── CreatePatient.php
│   │               ├── EditPatient.php
│   │               └── ViewPatient.php
│   └── Policies/
│       └── PatientPolicy.php
���── database/
│   ├── migrations/
│   └── factories/
└── composer.json
```

---

## 9. Next Steps

1. **Short-term:** None (module is feature-complete)
2. **Medium-term:** Add patient photo uploads via Spatie Media Library
3. **Long-term:** FHIR Patient resource export

---

## 10. References

- FHIR Patient Resource
- FlowRise HMS Executive Summary
- User Guide: Patient Management