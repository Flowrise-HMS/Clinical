# Clinical Module Implementation Record

**Document Status:** Implemented (verified against code 2026-09-20)
**Module:** FlowRise HMS Clinical Module (`Modules/Clinical`)

Until 2026-09-20 this file was an accidental copy of the Patient module plan. It now describes the Clinical module as built. Staff-facing instructions: [docs/user-guide/clinical-workflows.md](../../../docs/user-guide/clinical-workflows.md). Inpatient internals: [docs/developer-guide/adt-bed-management.md](../../../docs/developer-guide/adt-bed-management.md).

---

## 1. Scope

Encounters and their lifecycle (planned → arrived → triaged → in_progress → finished / cancelled, with on_leave for passes), encounter participants, encounter diagnoses (ICD-10/ICD-11 with a local `diagnosis_codes` catalogue and the WHO ICD-11 API), vital signs, clinical notes, allergies, service requests / request items / tasks (the generic order model that Diagnostics and Pharmacy fulfil), medication administration records (MAR) with dose scheduling and reminders, admission requests and ADT location events, discharge readiness and discharge summaries, nursing care plans, the clinical workspace pages, and the FHIR transformers for Encounter, Condition, AllergyIntolerance, CarePlan and Goal.

---

## 2. Database (42 migrations)

| Table(s) | Purpose |
|----------|---------|
| `encounters` | Visit record: `encounter_number` (`ENC-<date>-<seq>` via Core `DocumentNumberGenerator`), patient, branch, type (`EncounterType`), status (`EncounterStatus`), priority, class, coverage type + NHIS claim check code, chief complaint, department/location/bed, admitted/discharged timestamps, expected discharge, discharge disposition/condition, primary provider, soft deletes |
| `encounter_participants` | Care team members per encounter (`ParticipantRole`, `ParticipantStatus`) |
| `encounter_location_events` | ADT audit trail (`AdtEventType`: admitted, transferred_internal/in/out, discharged, bed_assigned, cancelled, admission_requested/rejected/cancelled/expired, on_pass, returned_from_pass) |
| `admission_requests` | Ward admission requests (`AdmissionRequestStatus`: pending, accepted, rejected, cancelled, expired; requested ward/bed, decided by, reason) |
| `discharge_summaries` | Draft / signed / amended summaries with structured sections and JSON diagnoses |
| `encounter_diagnoses`, `diagnosis_codes` | Coded diagnoses (`DiagnosisType` primary/secondary/complication, `DiagnosisCertainty`) and the seeded code catalogue |
| `vital_signs` | Measurements with `VitalSignType` context and `recorded_by` (NOT NULL) |
| `clinical_notes` | `NoteType` (12 types), `NoteStatus` draft/signed/amended, rich text content |
| `allergies` | `AllergenType`, `AllergySeverity`, `AllergyVerificationStatus`, onset |
| `service_requests`, `request_items`, `tasks` | Orders (`SRQ-<date>-<seq>`), line items (`RequestItemStatus`), fulfilment tasks (`TaskStatus`, `TaskOutcome`); medication items carry prescription details used by Pharmacy and MAR |
| `medication_administrations`, `medication_dose_reminder_logs` | Dose records (`MedicationAdministrationStatus` given/omitted/refused, witness flag, PRN reason) and reminder de-duplication |
| `clinical_canvas_layouts` | Saved layouts for the medication canvas |
| `care_plans` + `care_plan_problems`, `care_plan_problem_strengths`, `care_plan_objectives`, `care_plan_interventions`, `care_plan_evaluations`, `care_plan_routine_cares`, `care_plan_diagnoses`, `care_plan_medical_diagnoses`, `care_plan_orders`, `nursing_diagnosis_catalogue` | Nursing care plans (`CarePlanStatus`, `CarePlanCategory`, goal lifecycle/achievement, evaluation outcome and next action, `RoutineCareItem`) |

`ward`/`bed` attributes live on Core `locations` (capacity, gender policy, bed class, bed status).

---

## 3. Code structure (`app/`)

| Area | Contents |
|------|----------|
| `Models/` (26) | See table above plus `Encounter` helpers (`generateEncounterNumber`, `isLongStay`, `isCompleted`) |
| `Enums/` | EncounterType, EncounterStatus, EncounterPriority, AdmissionRequestStatus, AdtEventType, AdtDestinationType, DischargeDisposition, DischargeCondition, DischargeSummaryStatus, RequestStatus, RequestItemStatus, RequestPriority, TaskStatus, TaskOutcome, NoteType, NoteStatus, MedicationAdministrationStatus, MedicationSlotStatus, CarePlan*, Goal*, NursingProblemStatus, RoutineCareItem, AllergenType, AllergySeverity, AllergyVerificationStatus, OnsetType, DiagnosisType, DiagnosisCertainty, ParticipantRole, ParticipantStatus, VitalSignType, PatientPosition, SpO2Label, SpO2Parameter |
| `Classes/Services/` (34) | `EncounterService` (create, triage, complete, cancel), `AdtService` (request/accept/reject/withdraw admission, transfer internal/out, pass/return, discharge, expected discharge), `BedAssignmentService`, `BedStatusBackfillService`, `WardBoardService` + `WardBoardAlertResolver`, `DischargeReadinessService` (readiness items and blocking levels from config), `DischargeSummaryService`, `VitalSignService`, `ClinicalNoteService`, `DiagnosisService`, `DiagnosisCodeService`, `IcdCatalogueService` (WHO ICD-11 API), `DiagnosisSearch`, `AllergyService`, `ServiceRequestService`, `TaskService`, `FulfillmentService` (workspace fulfilment forms by diagnostic category), `MedicationAdministrationService`, `MedicationDoseScheduleService`, `MedicationFulfillmentPolicy` (payment-before-MAR, emergency exemption, controlled-substance witness), `MedicationSlotStatusResolver`, `MedicationCanvasService`, `CanvasLayoutService`, `ClinicalCanvasTreeBuilder`, `CarePlanService`, `CarePlanProblemService`, `CarePlanObjectiveService`, `CarePlanOrderService`, `NursingDiagnosisService`, `NhisClaimCodeGateway` (delegates to Insurance OTAC when present), `NullPrescriptionScheduleCalculator` (fallback when Pharmacy is absent), `ClinicalWorkspaceService`, `Pdf` |
| `Classes/Actions/` | `PatientActions` (header actions + "More Actions" group reused across Patient, MCH and workspace pages), `EncounterActions` (Request/Accept/Reject Admission, Complete, Triage, Send on pass, Return from pass, Set expected discharge, Withdraw request, Transfer (internal), Transfer out, Discharge, Cancel), `DischargeSummaryActions` (Discharge summary, Sign, Print) |
| `Classes/Fhir/` | Encounter, Condition, AllergyIntolerance, CarePlan, Goal transformers (read/search through the FHIR module) |
| `Events/`, `Listeners/`, `Notifications/` | Admission requested / accepted / rejected / cancelled, PatientAdmitted, PatientTransferred, PatientDischarged, EncounterFinished, EncounterCancelled, RequestItemCreated / Updated / Cancelled; listeners notify the ward (roles from `config('clinical.wards.notify_roles')`), the requester and the patient (channels per Core `NotificationSettings`) |
| `Console/` | `SendMarDoseRemindersCommand` (`clinical:mar-dose-reminders`, every 5 min when Pharmacy is enabled and reminders are on), `ExpireAdmissionRequestsCommand` (`clinical:expire-admission-requests`, hourly), `BackfillBedStatusCommand` (`clinical:backfill-bed-status`) |
| `Http/` | `CarePlanPdfController`, `DischargeSummaryPdfController` (auth routes). `routes/web.php` also still registers a scaffold `Route::resource('clinicals')` with placeholder views |
| `Filament/Clusters/Workspace/` | `WorkspaceCluster` (sidebar Workspaces → Clinical Workspace); pages `ClinicalWorkspace` (search/register, role-based tabs, header actions; `Concerns/ManagesWorkspacePatient`, `ManagesCarePlan`), `WardBoard`, `Timeline`, `PatientProfile`, `MedicationCanvas`, `PatientWorkspace` (legacy, hidden), `CarePlanWorkspace` (registered as a top-level Workspaces page) |
| `Filament/Clusters/Clinical/` | `ClinicalCluster` (Patient Care → Clinical); resources Encounters (with participants, vitals, notes, service requests, diagnoses, documents, invoices relation managers), ServiceRequests (request items), Tasks, ClinicalNotes, VitalSigns, CarePlans (view/print, hidden from nav), Allergies (hidden), EncounterDiagnoses (hidden); pages `MedicationAdministrationBoard`, `IcdBrowserPage`, `ManageClinicalSettings` |
| `Filament/RelationManagers/Patient/` | Allergies, Diagnoses, Encounters, Medication administrations, Tasks tabs for the patient record |
| `Filament/Widgets/` (23) | Workspace home: CriticalPatients, LongStayPatients, MyTasks, PendingAdmissions, PendingFulfillments, WorkspaceTodayAppointments; profile: PatientVitalsHistory/Chart/Overview, PatientDiagnoses, PatientDocuments, PatientNotes, PatientOrders, PatientTimeline, RecentPatients, CarePlanRecent/Previous; care plan workspace: CarePlanWard/Problems/RoutineCare/Diagnoses/Interventions/Objectives tables |
| `Filament/Exports/` | `EncounterExporter` (super-admin export) |
| `Settings/ClinicalSettings.php` | Spatie settings edited on the Clinical settings page (only `mar_require_payment_before`, `mar_emergency_exempt`, `mar_reminders_enabled` are read at runtime; the rest are stored only) |
| `config/config.php` | `admissions.request_expiry_hours` (24), `admissions.long_stay_days` (7), `beds.cleaning_on_discharge`, `beds.reserve_on_request`, `wards.notify_roles`, `adt_notifications.channels`, `discharge.enforce_readiness`, `discharge.require_signed_summary`, `discharge.readiness.*` (blocking / warning / info per item), `mar_payment`, `mar_allergy`, `mar_schedule`, `mar_default_times`, `mar_reminders`, `icd.*` (WHO API client id/secret, release, linearization), custom permissions |
| `Policies/` | Allergy, CarePlan (+ evaluate), ClinicalNote, DischargeSummary, EncounterDiagnosis, Encounter, ServiceRequest, Task, VitalSign |
| `database/` | 26 factories; seeders `ClinicalDatabaseSeeder`, `ClinicalCustomPermissionSeeder` (manage_bed_status → super_admin/nurse/admissions_staff; sign_discharge_summary → super_admin/doctor; print_discharge_summary → super_admin/doctor/nurse), `DiagnosisCodeSeeder`, `NursingDiagnosisCatalogueSeeder` |

---

## 4. Key behaviours

- **Encounter numbers** `ENC-<YYYYMMDD>-<00001>` and service request numbers `SRQ-...` come from Core's `DocumentNumberGenerator` with fixed prefixes.
- **Admission flow**: `AdtService::requestAdmission()` creates an `AdmissionRequest` (optionally reserving the preferred bed); `acceptAdmission()` assigns the bed, sets the encounter to inpatient and fires `PatientAdmitted`; `rejectAdmission()` needs a reason; pending requests expire after `admissions.request_expiry_hours`.
- **Discharge**: `DischargeReadinessService` evaluates pending medication doses, undispensed take-home meds, pending diagnostics, financial hold (Billing), unsigned notes, discharge diagnosis, signed discharge summary and follow-up booked, each classified blocking / warning / info by config; blocking items prevent discharge when `discharge.enforce_readiness` is true. Discharge frees the bed (status cleaning when configured), fires `PatientDischarged` (Billing finalises invoices, Appointment books a follow-up).
- **MAR**: doses are scheduled by Pharmacy's `PrescriptionScheduleCalculator` (default times in config); `MedicationAdministrationService::record()` enforces `administer_medication`, PRN reason, omission/refusal reason and witness attestation for controlled medications; `MedicationFulfillmentPolicy` enforces payment-before-MAR with emergency exemption; reminders are sent by the scheduled command and de-duplicated in `medication_dose_reminder_logs`.
- **Workspace tabs** are role-based (`ClinicalWorkspace::getUserRoleKey()`: doctor/clinical_officer/consultant/physician/specialist → clinician; nurse/registered_nurse/practice_nurse → nurse; laboratory_technician/lab_technician/radiographer → lab) and permission-filtered (notes, diagnosis, ADT).
- **Cross-module hooks**: header actions, widgets and relation managers are pulled from Core registries so Appointment, Billing, Diagnostics and MCH can extend the pages without hard dependencies (`OptionalClass::when(...)`).

---

## 5. Tests

93 test files under `tests/` (feature tests for encounters, ADT, ward board, discharge summaries, MAR, care plans, diagnoses, workspace, widgets, FHIR transformers, commands; two Playwright browser tests). Run with `php artisan test --compact Modules/Clinical/tests`.
