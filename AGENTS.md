# AGENTS.md — ZPM Membership Management & GIS Intelligence System

## 1. Project Overview

Build a secure, web-based Membership Management, Political Analysis, and GIS Decision Support System for the Zoram People's Movement (ZPM) in Mizoram, India.

The system will be managed centrally from the office of the General Secretary and will support the organized maintenance of party membership records, organizational hierarchy, membership changes, aggregated analysis, campaign planning, and geographic visualization.

The application must be suitable for non-technical administrative users. The interface should be elegant, minimal, intuitive, responsive, and extremely easy to use.

The system must prioritize data accuracy, maintainability, usability, privacy, and reliable reporting.

Do not begin by building all modules simultaneously. First understand the requirements, design the data model, establish the application architecture, and implement the system in manageable stages.

## 2. Technology Stack

Use the following technologies:

- Frontend: HTML5, CSS3, vanilla JavaScript.
- Backend: PHP.
- Database: PostgreSQL.
- Geospatial database functionality: PostGIS.
- Mapping: Leaflet with appropriate base maps and GeoJSON layers.
- Data visualization: Chart.js where useful.
- Database access: PDO with the PostgreSQL driver.
- Version control: Git and GitHub.

Do not commit sensitive information, such as passwords or personal member data, to the version control system.

Do not introduce React, Vue, Angular, or a large frontend framework unless explicitly approved.

Avoid unnecessary dependencies. Prefer lightweight, well-maintained libraries when they provide a clear benefit.

Use server-rendered PHP pages where appropriate, with JavaScript enhancing interactivity.

## 3. Core Objectives

The application must provide the following capabilities:

1. Maintain a centralized register of ZPM members.
2. Organize members according to Unit, Block, and District.
3. Record member details, designations, enrollment dates, and EPCI numbers.
4. Track membership status and changes over time.
5. Record affiliations or declared political alignment where lawfully and appropriately authorized.
6. Maintain a clear record of organizational roles and their changes.
7. Provide dashboards, reports, charts, and aggregated statistical analysis.
8. Provide GIS-based analysis at Unit, Block, and District levels.
9. Support administrative record keeping and data quality checks.
10. Support aggregate organizational planning and campaign activity reporting.
11. Maintain historical records for auditing and longitudinal analysis.
12. Protect sensitive personal information through appropriate permissions and security controls.

## 4. Organizational Hierarchy

The organizational hierarchy is:

District → Block → Unit → Member.

### District

The highest organizational level in this application.

A district may contain multiple blocks.

Each district should have:

- Unique identifier.
- District name.
- Administrative status.
- Optional geographic boundary.
- Associated blocks.
- Creation and modification timestamps.

### Block

A block belongs to one district and contains multiple units.

Each block should have:

- Unique identifier.
- Block name.
- Parent district.
- Administrative status.
- Optional geographic boundary.
- Creation and modification timestamps.

### Unit

The lowest organizational jurisdiction.

A unit typically represents a village or locality.

Each unit should have:

- Unique identifier.
- Unit name.
- Unit type: Village, Locality, or Other.
- Parent block.
- Administrative status.
- Optional geographic boundary.
- Optional centroid or representative coordinate.
- Remarks.
- Creation and modification timestamps.

The unit is the primary geographic and organizational aggregation level.

### Hierarchy Rules

- Every active unit must belong to a block.
- Every active block must belong to a district.
- Members should be associated with an organizational unit.
- A member's district and block should normally be derived through the unit relationship rather than stored redundantly.
- Changes to organizational assignments must be recorded.
- Do not delete organizational records that have associated historical data. Use an inactive or archived status instead.
- Support organizational reassignment without destroying previous assignments.
- Prevent duplicate or conflicting hierarchy records.

## 5. Member Management

Create a comprehensive member register.

### Member Fields

Each member record should support:

- Internal member ID.
- Full name.
- Gender: Male, Female, or Not Specified.
- EPCI number, subject to validation and appropriate access controls.
- Date of enrollment.
- Current organizational unit.
- Current organizational designation, if any.
- Current membership status.
- Date of status change, where applicable.
- Remarks or administrative notes, with appropriate permissions.
- Created by.
- Created timestamp.
- Last modified by.
- Last modified timestamp.

Additional fields should only be introduced when they serve a documented requirement.

Do not collect sensitive personal information without an approved business need.

### Designation

The initial designation options are:

- President.
- Vice President.
- Secretary.
- Treasurer.
- Executive.
- General.

Confirm the intended meaning of "General" before finalizing this designation list.

The system should support a member having no designation.

Designations should be configurable through an authorized administrative interface rather than hard-coded throughout the application.

If organizational rules allow a person to hold multiple roles, implement designations as effective-dated assignments rather than forcing a single permanent role into the member record.

Record the unit, block, or district level at which each designation applies.

### Member Operations

Authorized users must be able to:

- Add a member.
- View a member.
- Edit a member.
- Search and filter members.
- Assign or change an organizational unit.
- Assign or change a designation.
- Correct erroneous records.
- Mark a member as inactive or departed.
- Record membership or affiliation changes.
- View permitted historical records.
- Export authorized reports.

Use clear confirmation dialogs for consequential changes.

Do not physically delete member records by default. Use archival, deactivation, or an authorized privacy-compliant deletion process.

## 6. Membership Lifecycle and Affiliation History

The application must distinguish between a person's organizational membership, their current declared affiliation, and their historical record.

Do not represent all three using a single status field.

### Membership Status

Use configurable statuses such as:

- Active.
- Inactive.
- Resigned.
- Deceased, if necessary for official record keeping.
- Archived.

The approved business rules must determine how each status affects membership totals.

### Affiliation

Support the following initial affiliation options:

- ZPM.
- Neutral / Undeclared.
- Indian National Congress (Congress).
- Mizo National Front (MNF).
- Other.
- Unknown / Not Recorded.

The distinction between "Neutral" and "Unknown / Not Recorded" must be maintained. An unknown value must never automatically be interpreted as neutral.

"Other" should allow a party name to be recorded where authorized.

Affiliation changes must be recorded as dated events, including:

- Previous affiliation.
- New affiliation.
- Effective date.
- Recorded date.
- Authorized user making the change.
- Reason or administrative note, where appropriate.

Historical changes must remain available to authorized administrators.

Do not infer affiliation solely from an individual's name, gender, location, family, community, or other personal characteristics.

Do not automatically change membership or affiliation based on a score, prediction, or map result.

## 7. Political Classification

The initial requested classification uses three colors:

- Red.
- Yellow.
- Green.

These colors must be configurable labels with clearly defined, approved meanings.

If this classification is approved for use, maintain it as a separate field from affiliation and membership status.

Each classification record should support:

- Classification.
- Effective date.
- Recorded date.
- Authorized recorder.
- Optional review date.
- Optional administrative note.

Provide a clear legend in all interfaces where these colors appear.

Never use color alone to communicate meaning. Include a text label and accessible contrast.

Do not automatically assign or change classifications using personal data, inferred beliefs, protected characteristics, or opaque predictive models.

Do not build automated individual-level political persuasion scores or recommendations. Analysis should emphasize organizational records, aggregate trends, geographic coverage, and explicitly documented administrative measures.

## 8. Member Movement and Change History

The system must preserve a reliable history of membership events.

Examples include:

- New enrollment.
- Correction of an existing record.
- Organizational unit transfer.
- Designation change.
- Affiliation change.
- Membership activation or deactivation.
- Resignation.
- Record archival.

Each event should include:

- Event ID.
- Member ID.
- Event type.
- Previous value, where applicable.
- New value, where applicable.
- Effective date.
- Recorded timestamp.
- Authorized user.
- Administrative reason, where required.

Use database transactions for related changes.

Current member information should be easily accessible, while historical events should be available through a clearly organized history or timeline.

## 9. Duplicate Detection and Data Quality

The application must prevent duplicate and inconsistent records.

- Use EPCI as a potential unique identifier where the official identifier's uniqueness and validity have been verified.
- Normalize identifiers before comparison.
- Enforce database constraints where appropriate.
- Detect possible duplicate names within the same organizational unit.
- Show potential duplicates before creating a new record.
- Allow authorized users to review and resolve duplicate records.
- Preserve audit history when records are merged or corrected.
- Never merge records automatically based only on matching names.

Provide a data quality dashboard covering incomplete records, potential duplicates, invalid identifiers, and inconsistent organizational assignments.

## 10. Dashboard and Analysis Module

Create a dedicated dashboard for the General Secretary's office.

The dashboard should provide a clear overview of organizational records and data quality.

### Core Indicators

Include, where supported by the data:

- Total recorded members.
- Active members.
- New enrollments within a selected period.
- Inactive or departed members.
- Members by district.
- Members by block.
- Members by unit.
- Members by designation.
- Membership changes over time.
- Organizational units with incomplete records.
- Data entry and record maintenance activity.

Show the date range and the definitions used for each metric.

Ensure that counts use consistent, documented rules.

### Filters

Provide filters for:

- District.
- Block.
- Unit.
- Date range.
- Membership status.
- Designation.
- Affiliation, where access is authorized.
- Record completeness.

Filters should work consistently across relevant tables, charts, reports, and maps.

### Charts

Use charts only when they improve understanding.

Prefer:

- Trend lines for enrollment and membership changes over time.
- Bar charts for comparisons between organizational units.
- Stacked bars for suitable categorical breakdowns.
- Tables for precise figures.
- Maps for geographic distribution.

Avoid cluttered dashboards, unnecessary charts, and misleading visualizations.

Clearly distinguish recorded totals from estimates and incomplete data.

## 11. GIS and Spatial Analysis Module

GIS is a core application module.

Use PostgreSQL with PostGIS for geographic data management and spatial queries.

Use Leaflet for interactive web maps.

### Geographic Model

Support optional geographic boundaries for:

- Districts.
- Blocks.
- Units.

Use appropriate spatial data types and coordinate reference systems.

For administrative boundaries, use a consistent projected or geographic reference system appropriate to the source data and operation.

Use valid geometries and spatial indexes where appropriate.

### Initial Mapping Requirements

- Display district boundaries.
- Display block boundaries.
- Display unit boundaries.
- Allow users to navigate between geographic levels.
- Select a unit on the map to view authorized aggregate statistics.
- Select a block to inspect its constituent units.
- Select a district to inspect its constituent blocks.
- Provide map legends and clear layer controls.
- Support map search and filtering.
- Provide a synchronized relationship between map selections and dashboard filters.
- Show clear loading, empty, and error states.
- Allow authorized users to import approved geographic boundary datasets.

### Member Geolocation

Individual members do not need to be geolocated in the initial release.

Do not require latitude or longitude for member registration.

Use the organizational unit as the default geographic reference for aggregated analysis.

Do not fabricate individual coordinates from unit centroids or boundaries.

If individual geolocation is considered in a future version, require a separate approved purpose, appropriate consent or other lawful basis, access controls, and a privacy assessment.

### Spatial Analysis

Prioritize:

- Membership record counts by unit, block, and district.
- Enrollment trends by administrative area.
- Organizational coverage and missing-record analysis.
- Comparison of administrative areas across selected periods.
- Geographic distribution of data quality issues.
- Identification of units requiring administrative record verification.

Do not describe geographic correlations as proof of causation.

Do not expose small-group or sensitive individual information through maps or exported spatial datasets.

Use aggregation thresholds where needed to reduce disclosure risks.

## 12. Reports and Record Keeping

Provide reports that authorized users can generate, filter, and export.

Initial reports should include:

- Member register.
- Active and inactive member counts.
- Enrollment register for a selected period.
- Organizational hierarchy register.
- Designation register.
- Unit, block, and district summaries.
- Membership change history.
- Record completeness and duplicate review reports.
- Administrative activity and audit reports.

Support CSV and Excel exports when appropriate.

PDF reports may be introduced where an official printable format is required.

Exports must respect the requesting user's permissions.

Provide export timestamps, selected filters, and report definitions.

Do not include sensitive identifiers in general-purpose reports unless explicitly required and authorized.

## 13. Campaign and Activity Management

Provide a module for recording organizational activities and administrative planning.

Keep this module separate from individual member records.

Possible features include:

- Campaign or organizational activity name.
- Activity type.
- Associated district, block, or unit.
- Planned date.
- Actual date.
- Activity status.
- Responsible organizational office.
- Approved objectives.
- Recorded aggregate attendance or participation.
- Completion notes.
- Supporting documents, where authorized.

Provide activity calendars and aggregate progress summaries.

Do not automatically generate individualized political persuasion strategies, rank people by predicted political susceptibility, or infer personal voting intentions.

The initial release should focus on organizational coordination, transparent reporting, geographic coverage, and administrative planning.

## 14. User Interface and Experience

The interface must be exceptionally easy to use for non-technical office staff.

### Design Principles

- Elegant, minimal, professional appearance.
- Clear typography and readable labels.
- Consistent spacing, colors, buttons, and form controls.
- Mobile-responsive layouts.
- Desktop-first optimization for the General Secretary's office, without sacrificing mobile usability.
- Minimal unnecessary navigation.
- Avoid technical jargon.
- Prefer clear labels over ambiguous icons.
- Use icons only where they improve recognition.
- Provide visible confirmation for successful actions.
- Provide clear, actionable error messages.
- Use sensible defaults and avoid unnecessary form fields.
- Make common tasks accessible in as few steps as practical.

### Navigation

Use a clear sidebar or similarly simple navigation structure.

Initial navigation may include:

1. Dashboard.
2. Members.
3. Organizational Structure.
4. Membership History.
5. GIS & Maps.
6. Analysis & Reports.
7. Activities.
8. User Management.
9. Audit Log.
10. Settings.

Hide or restrict navigation items according to user permissions.

### Member Registration

The add-member workflow must be fast and straightforward.

- Use clearly labeled form fields.
- Provide dropdowns for controlled values.
- Filter available blocks based on the selected district.
- Filter available units based on the selected block.
- Validate EPCI and required fields.
- Show duplicate warnings before saving.
- Preserve entered data if a validation error occurs.
- Disable repeated submissions while a request is processing.
- Confirm successful registration.
- Provide a direct way to view the newly registered record.

### Member Register

Provide:

- Search by name and permitted identifiers.
- Pagination.
- Sorting.
- Filter controls.
- Clear membership status indicators.
- Unit, block, and district context.
- Quick access to record details.
- Authorized edit and archival actions.

Do not load an unlimited number of records into the browser.

### Accessibility

Use semantic HTML, keyboard-accessible controls, adequate contrast, clear focus indicators, and accessible form labels.

Do not depend exclusively on color to communicate membership status or other information.

## 15. Authentication and Authorization

The system contains confidential organizational records and must not be publicly accessible.

Implement secure authentication and role-based authorization.

Suggested initial roles:

### System Administrator

- Manage application settings.
- Manage user accounts and permissions.
- Maintain organizational master data.
- Perform authorized data maintenance.
- Access system health and audit functions.

### General Secretary / Authorized Central Office User

- View permitted organization-wide records.
- Manage authorized membership records.
- Access approved reports and analysis.
- Review membership history and organizational summaries.

### Data Entry Operator

- Create and update records within assigned permissions.
- Access only the organizational areas and fields authorized for that user.
- Cannot manage users or modify system-level settings unless separately authorized.

### Read-Only User

- View permitted records, summaries, and reports.
- Cannot modify records.

These roles are initial proposals. Confirm actual responsibilities before implementing permissions.

Enforce permissions on the server and in the database access layer where appropriate.

Hiding a button in the frontend is not sufficient authorization.

## 16. Privacy and Security

- Protect personal information and identifiers such as EPCI.
- Apply data minimization and purpose limitation.
- Restrict access to individual records and sensitive fields.
- Use secure password hashing.
- Protect sessions and authentication endpoints.
- Use prepared SQL statements.
- Apply CSRF protection where applicable.
- Escape user-generated content to prevent XSS.
- Validate all input on the server.
- Use HTTPS in production.
- Keep credentials and secrets outside version control.
- Maintain audit logs for sensitive actions.
- Avoid recording passwords, authentication tokens, or unnecessary personal information in logs.
- Implement secure backups and a tested restoration procedure.
- Define retention and archival rules with the authorized data owner.
- Do not expose personal identifiers through URLs, public APIs, map popups, or unprotected exports.
- Do not send member records to third-party AI services without explicit approval and an appropriate privacy and security review.

Treat political affiliation and political opinions as sensitive personal information. Process them only for an authorized, documented purpose and under applicable law.

## 17. Database Architecture

Use a normalized relational schema.

Potential core tables include:

- districts.
- blocks.
- units.
- members.
- designations.
- member_designation_history.
- membership_events.
- affiliation_history.
- user_accounts.
- roles.
- permissions.
- role_permissions.
- user_scope_assignments.
- activities.
- activity_locations or activity_assignments, if required.
- audit_logs.
- geographic boundary tables or geometry columns.

The final schema must be derived from approved requirements.

Do not create redundant tables merely because they appear in this preliminary list.

Use foreign keys, constraints, appropriate indexes, and transactions.

Store timestamps consistently, and document the timezone used for display.

Use migrations to create and modify the database schema.

Do not recreate existing tables unnecessarily or make destructive changes without approval.

## 18. API and Application Architecture

- Separate application logic, database access, and presentation.
- Use a consistent API response structure.
- Return appropriate HTTP status codes.
- Validate requests and authorize actions before accessing sensitive data.
- Avoid duplicating business rules between PHP endpoints.
- Use database transactions for multi-step changes.
- Handle errors centrally where practical.
- Do not expose stack traces or internal SQL errors to users.
- Keep database credentials in protected configuration.
- Avoid placing confidential configuration files in publicly accessible directories.
- Prevent direct access to internal files that are not intended to be public.

Inspect the existing application before deciding whether to use a new architecture.

## 19. Performance and Reliability

- Add indexes for frequently searched fields.
- Use spatial indexes for spatial queries.
- Paginate member registers.
- Avoid unnecessary database queries.
- Optimize aggregate queries used by dashboards.
- Avoid recalculating expensive statistics on every page interaction when a safe, documented optimization is appropriate.
- Use asynchronous requests where they improve responsiveness.
- Display loading indicators for longer operations.
- Handle network failures gracefully.
- Use transactions to prevent partial updates.
- Maintain reliable backups and verify restoration.

Do not sacrifice data correctness for performance.

## 20. Testing and Quality Assurance

Test the following before considering the application ready:

- Authentication and permission enforcement.
- Member creation, editing, archival, and authorized deletion workflows.
- Duplicate detection.
- Enrollment date validation.
- Designation assignment and history.
- Affiliation changes and history.
- Organizational transfers.
- District, block, and unit relationships.
- Membership totals and report accuracy.
- Map layers and geographic filtering.
- Missing or invalid geographic boundaries.
- CSV and Excel exports.
- Concurrent edits and transaction failures.
- Security controls.
- Mobile and desktop usability.
- Backup restoration.

Do not claim a test has passed unless it has actually been performed.

## 21. Development Workflow

Follow this process for every significant feature:

1. Read AGENTS.md and the relevant requirements.
2. Inspect the current project files and database schema.
3. Identify dependencies and affected modules.
4. Propose the implementation approach for significant changes.
5. Implement in small, testable increments.
6. Create or update database migrations when required.
7. Test the feature and relevant existing functionality.
8. Check authorization, data integrity, and security.
9. Update documentation.
10. Summarize the changes, tests, and remaining work.

Do not generate the entire application in a single unreviewed step.

Do not replace working components without understanding their dependencies.

Do not perform destructive database operations, deploy to production, or publish confidential data without explicit authorization.

## 22. Initial Development Phases

Implement the system in the following phases.

### Phase 1 — Foundation

- Confirm requirements and terminology.
- Design the database schema.
- Establish migrations.
- Implement authentication and authorization.
- Establish the application structure and common UI components.

### Phase 2 — Organizational Structure

- Create districts, blocks, and units.
- Support organizational hierarchy management.
- Import or enter approved boundary datasets.
- Validate organizational relationships.

### Phase 3 — Membership Register

- Create and edit member records.
- Implement search, filters, pagination, and duplicate detection.
- Implement designation assignments.
- Implement membership status and history.
- Implement archival and correction workflows.

### Phase 4 — Dashboard and Reports

- Build organization-wide summaries.
- Implement date-based reporting.
- Implement authorized exports.
- Add data quality reporting.

### Phase 5 — GIS

- Integrate Leaflet.
- Display administrative boundaries.
- Add unit, block, and district selection.
- Synchronize map filters with aggregate statistics.
- Implement spatial validation and geographic summaries.

### Phase 6 — Activities and Planning

- Record organizational activities.
- Provide activity calendars and progress summaries.
- Add aggregate activity reporting.

### Phase 7 — Hardening and Deployment

- Complete security testing.
- Validate permissions.
- Test database backups and restoration.
- Optimize performance.
- Prepare documentation and production deployment procedures.

Do not begin a later phase before its required foundations are sufficiently complete.

## 23. Definition of Done

A feature is complete only when:

- Its requirements are satisfied.
- The implementation follows the approved architecture.
- The database remains consistent.
- Required authorization checks are implemented.
- Errors and empty states are handled.
- Relevant tests have been performed.
- The interface is usable and responsive.
- Documentation and migrations are updated where necessary.
- Any outstanding limitations are clearly reported.

## 24. Final Instructions

Act as a careful senior software engineer.

Prioritize correctness, privacy, security, data integrity, maintainability, and usability over speed or unnecessary complexity.

Build a reliable administrative and GIS analysis platform, not merely a collection of forms and charts.

Before making major architectural decisions, confirm the relevant requirements with the project owner.

Keep the system understandable to both the office staff using it and the developers maintaining it.

Should run on localhost for development and testing purposes. I have XAMPP installed for this purpose. And db is local too. Ensure that the local environment mirrors the production environment as closely as possible to avoid deployment issues.


## 25. Current Implementation Status (reference for agents)

This section describes what exists today. Sections 1–24 remain the requirements; where they differ, this section records what was actually built and the decisions taken.

### Runtime and compatibility

- PHP **7.4 or later** is supported (the shared host runs 7.4). Do not use PHP 8-only syntax: no `match`, constructor property promotion, `catch` without a variable, union types, `readonly`, nullsafe `?->`, or named arguments. `app/polyfills.php` supplies `str_starts_with`, `str_ends_with` and `str_contains`.
- PostgreSQL with PostGIS. The production server's PostgreSQL is older than 11: use `EXECUTE PROCEDURE` (not `EXECUTE FUNCTION`) in triggers, and do not use psql `\if`/`\gset` in deployment SQL.
- All application tables live in the `zpm_mms` schema; PostGIS lives in `public`.

### Layout

- `public/` – web root (`index.php` front controller, `assets/` with `app.css`, `app.js`, `gis.js`, `ask.js`). `base_path` in `config/config.php` is stripped from the request URI for routing.
- `app/` – `Application` (routes and handlers), `Auth`, `Csrf`, `Database`, `View`, `QueryEngine`, `AiPlanner`, `polyfills.php`. The autoloader is in `public/index.php` and `scripts/bootstrap.php`.
- `templates/` – server-rendered pages; `templates/partials/header.php` is the single shared top bar (title "ZPM Member Registry", user, Sign out) used by every page.
- `database/migrations/` 001–005; `scripts/` for migration, user creation and development-only demo seeding.
- `deploy/` – `init.sql` (complete schema for a fresh server, no role/extension statements), `01_db_setup.sql`, `config.production.example.php`, `apache-vhost.conf`, `backup.sh`, `package.ps1`, `DEPLOYMENT.md`. `deploy/data.sql` is a local data export containing personal data and password hashes; it is gitignored and must never be committed.

### Implemented modules

- Authentication, roles and permissions, CSRF, audit log.
- Registered individuals (the member register): search, pagination, party and classification filters, **district/block/unit cascading filters**, create/edit/archive, family heads (one level, enforced by a trigger).
- Organization: districts, blocks, units.
- Dashboard, reports, CSV export, data quality checks, party migration chart.
- **GIS (`/gis`, `/gis/party-data?level=district|block`)**: Leaflet map showing a party-population pie marker per district or per block (no unit-level data). Counts below `MAP_DISCLOSURE_THRESHOLD` (5) are suppressed. Selecting a district or block zooms to it and shows its data. Current district and block geometry is point-only (`representative_location`); there are no boundary polygons yet.
- **AI analysis (`POST /reports/ask`)**: a question planner using any OpenAI-compatible chat API (configured in the `ai` block). Only the question text, a data-free schema and aggregate counts (small groups suppressed) are sent; never member records. Failure detail is returned as `debug` in the JSON response and logged to the browser console and the PHP error log; API keys are never logged or returned. `gpt-oss` models get `reasoning_effort` low and a larger completion limit.

### Deployment notes

- Production target is shared hosting (no root, no Ollama). Free hosted AI option: Groq (`https://api.groq.com/openai/v1`); the model must be one the key can access (check `/models`; `openai/gpt-oss-20b` worked in this deployment).
- Real `config/config.php` is gitignored and edited by hand on each server: `environment` `production`, correct `base_path`, database credentials, `ai.enabled`, and the API key. Never commit keys or paste them in chat.
- Load a fresh server with `deploy/init.sql`; optionally load sample data with `deploy/data.sql` (it truncates app tables first).

### Not yet done

Activities module, member designation history UI, affiliation history beyond party classification, boundary polygon import, PDF/Excel export, backup restoration testing, automated tests.