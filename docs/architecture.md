# Source Transcription Module – Architecture

## Overview

The *Source Transcription* module introduces a structured workflow for transcribing genealogical sources in webtrees.

It separates:
- **working state** (editable NOTE in webtrees)
- **historical state** (immutable revisions stored in module tables)

This enables versioning, collaboration, and integration with external transcription tools.

---

## Core Concept

### NOTE vs. Revision

| Concept      | Purpose              | Storage                  |
|--------------|----------------------|--------------------------|
| **NOTE**     | Current working copy | webtrees GEDCOM (`NOTE`) |
| **Revision** | Historical snapshot  | module database tables   |

Key principle:

> The NOTE is editable. The revision is immutable.

Workflow:

```text
NOTE (editable)
    ↓ Save as revision
Revision (immutable)
    ↓ Generate/update NOTE
NOTE (new working state)
```

Finalizing a transcription also follows this rule. When a user clicks **Finalize**, the current editor content is copied back to the working NOTE first and then saved as a new revision before the status is changed to `final`.

Earlier revisions can be promoted back to the current state. This operation updates the working NOTE from the selected immutable revision and then marks that revision as the single current revision. It does not rewrite older revision rows.

Two revisions of the same transcription can also be compared. The comparison shows metadata differences side by side and a line-based text diff for the transcription content.

Revision number allocation is protected against concurrent save requests by parent-row locking and a database-level uniqueness constraint. The concurrency reasoning and manual test idea are documented in [docs/revision-concurrency.md](revision-concurrency.md).

## Data Model

An overview of the current database schema can be found in [docs/database/schema-3.sql.txt](database/schema-3.sql.txt).

### transcription_collaborators
id  
transcription_id  
user_id  
role  
invited_by_user_id  
invited_at  
accepted_at  
is_active  

This table stores the team for internal collaboration. It does not replace the transcription or revision tables.
V1 uses immediate assignment: selected collaborators become active as soon as they are added. `accepted_at` remains unused in V1 and is reserved for a later opt-in invitation/acceptance workflow.

### transcription_requests (planned)
id  
transcription_id  
provider_key  
interaction_model  
request_status  
external_reference  
external_url  
request_payload_json  
response_payload_json  
created_by_user_id  
created_at  
updated_at  
closed_at  

### Future: Evidence Layer

The long-term direction of the module is not only to store better notes. It should support an explicit evidence layer between transcription text and genealogical conclusions.

A future table such as `transcription_inferences` could document the chain:

```text
transcription revision
    -> finding / evidence statement
    -> interpretation
    -> conclusion or claim
```

This would allow users to distinguish what the source literally says, what is inferred from it, and which genealogical conclusion is derived from that inference. The goal is to make reasoning inspectable instead of embedding it only in free-text notes.

This direction follows the Genealogical Proof Standard (GPS) in spirit:

- reasonably exhaustive research is supported by linking conclusions to multiple source-based findings
- complete and accurate source references remain anchored in webtrees records
- analysis and correlation can be represented as explicit inference steps
- conflicting evidence can be documented without overwriting earlier interpretations
- conclusions can carry a visible reasoning path back to the underlying transcription revisions

This is intentionally a future architecture direction. The current module focuses on transcription, NOTE synchronization, revision history, provider integration, and collaboration; the evidence layer should build on those foundations later.

---

## Interaction Models

Manual (direct)  
interaction_model = manual_direct  

Internal collaboration  
interaction_model = internal_collaborative  

Automated (asynchronous)  
interaction_model = automated_async  

Crowd-based (asynchronous)  
interaction_model = crowd_async  

---

## Workflow

Manual:
1. Create transcription  
2. Create NOTE and link it to the selected media object  
3. Edit NOTE  
4. Save as revision  
5. Finalize or reopen from the NOTE action area

Internal collaboration:
1. Open an existing transcription for collaboration
2. Optionally save the current working NOTE as a starting revision
3. Assign collaborators
4. Notify the team
5. Collaborators edit the working NOTE and save revisions
6. Any collaborator can set ready for review
7. The initiator can set final
8. Finalize saves the current NOTE as a revision before closing the transcription

Automated:
1. Submit request  
2. Import result  

Crowd:
1. Ask community  
2. Import answer  

---

## Permissions

The module follows webtrees tree permissions. Access to the dashboard is available to members, but editing workflows require editor rights for the current tree.

Important enforcement points:

- Manual transcription creation requires `Auth::isEditor($tree)`.
- Source and media selection for manual transcriptions only returns records visible to the current user.
- Saving NOTE text, saving revisions, opening collaboration, submitting for review, finalizing, and reopening require editor rights.
- Internal collaboration membership does not override webtrees permissions. A user invited as a collaborator must still be an editor for the tree to edit the NOTE or save revisions.
- Eligible internal collaborators are limited to users who are editors for the tree.

---

## Services

- **CreateTranscriptionService**: Delegates creation of a new transcription to the matching provider.
- **GetTranscriptionDetailService**: Loads complete transcription data, including metadata and revision history.
- **SaveNoteAsRevisionService**: Creates an immutable revision snapshot from the current NOTE content.
- **GenerateOrUpdateNoteService**: Synchronizes a webtrees NOTE with module data and links the NOTE primarily to the selected media object. If no media object is selected, the source is used as fallback target.
- **CompareRevisionsService**: Builds side-by-side revision metadata rows and a line-based text diff for two revisions of the same transcription.
- **EnsureTagNoteService**: Ensures that the configured webtrees tag NOTE exists and is linked to the same target as the transcription.
- **SourceBadgeService**: Builds source title badges from active transcription state and linked transcription-suitable source media.
- **MediaBadgeService**: Builds media title badges for media objects linked from sources through `SOUR:OBJE`. Media objects without a source link are ignored.
- **OpenCollaborationService**: Opens an existing transcription for internal collaboration, assigns the team, and delegates to the internal provider.
- **CollaborationStatusService**: Applies collaborative status transitions with role checks.
- **CollaborationNotificationService**: Notifies team members about collaboration events through the webtrees `MessageService`.

Status request handlers for manual and internal workflows also ensure that a finalize action persists the current NOTE as a revision before changing the status.

---

## Providers

- **TranscriptionProviderInterface**: Defines the common base contract for transcription providers.
- **CreatesTranscriptionsInterface**: Marks providers that can create new transcriptions.
- **OpensCollaborationInterface**: Marks providers that can open an existing transcription for collaboration.
- **ManualTranscriptionProvider**: Encapsulates the manual workflow, including transcription creation, initial revision, working NOTE, and tag NOTE.
- **InternalCollaborationProvider**: Encapsulates opening an existing transcription for internal collaboration. Source, media object, working NOTE, and existing revisions are preserved.
- Provider-specific media rules are exposed by the provider classes themselves. Transkribus accepts JPEG, TIFF, and PNG with a 20 MB limit. Discourse is prepared for `jpg`, `jpeg`, `png`, `gif`, `heic`, `heif`, `webp`, `avif`, `txt`, and `pdf`; no Discourse file-size limit is currently known.

`TranscriptionProviderFactory` lives in `Application/Factory` and resolves the matching provider from the provider key.

---

## Gateways

- **SharedNoteGateway**: Abstraction layer for accessing webtrees shared NOTE records. NOTE records are created and updated through webtrees record APIs so that CHAN data, pending changes, and internal links are handled correctly.
- **SourceGateway**: Provides access to webtrees source records, their GEDCOM structure, and fallback NOTE links to sources.
- **MediaObjectGateway**: Provides access to media objects, media-file metadata, and primary NOTE links to OBJE records.

---

## UI

### Pages
- **Dashboard**: Central overview of all available transcriptions.
- **Create**: Interface for manually creating a transcription by selecting a source and optional media object.
- **Detail**: Main working view. It contains the editor for the current NOTE, shows the linked media object, lists the revision history, allows a previous revision to become current again, and provides revision comparison controls.
- **Compare revisions**: Side-by-side view for two revisions of one transcription. It highlights changed metadata fields and displays line-level text additions, removals, and changes.
- **Admin settings**: Module configuration plus diagnostics for database schema, NOTE editor/TinyMDE availability, tree Markdown settings, transcription counts by tree, and an optional consistency check.

### Context Badges

The module can add colored `T` badges to source and media titles through webtrees custom view overrides.

Source badges are rendered for:

- source detail titles
- source lists
- linked source tables
- source facts in the individual sources tab

Media badges are rendered for:

- media detail titles
- media list cards
- linked media tables

Badge states:

- green: an active transcription exists
- red: the source or source-linked media is suitable for transcription, but no active transcription exists
- yellow: transcription is still possible, but no suitable source-linked media file is available

Media badges only apply to media objects linked from source records through `SOUR:OBJE`. `OBJE:SOUR` citations are intentionally not used for this feature.

Badge links use the dashboard route. Green badges pass `source_xref` or `media_xref` so the dashboard opens with a matching transcription filter. Red and yellow badges link to the unfiltered dashboard because there is no active transcription to filter to.

The dashboard repository query supports optional `source_xref` and `media_xref` filters. Sort links, status/provider filters, and pagination preserve these parameters.

The module also renders badges from `webtrees-media-badge` when that module is loaded. Conversely, `webtrees-media-badge` can render the source-transcription badge if its compatibility component is available. This avoids losing badges when both modules override the same media-related webtrees views.

### Media Viewer

The detail page renders media files through `resources/views/partials/media-viewer.phtml`.
The detail workspace supports two browser-side layouts: side-by-side media and NOTE editor, and a vertical layout with media above the NOTE editor. The selected layout is stored in `localStorage`, so no database migration or server-side user setting is required.
If no manual layout selection has been stored yet, the default layout is side-by-side. For local image files with readable dimensions, the detail page automatically chooses the vertical layout when the first image with known dimensions is wider than it is tall. A manual user selection still overrides this automatic choice through the existing `localStorage` setting.

`MediaObjectGateway::files()` enriches webtrees `MediaFile` objects with viewer metadata:

- `url`: inline download URL for local media, original URL for external media
- `download_url`: attachment download URL for local media, original URL for external media
- `mime`
- `extension`
- `is_external`
- `is_embeddable_external`
- `viewer_type`: `image`, `pdf`, `audio`, `video`, `text`, or `download`
- `width` and `height` for local image files when dimensions can be read
- `text_content` and `text_truncated` for local text previews

Supported preview behavior:

- images (`jpg`, `jpeg`, `png`, `gif`, `webp`) use the locally bundled OpenSeadragon viewer for zoom and pan
- PDF files use the browser's native PDF renderer in an iframe
- audio files use native HTML5 `<audio controls>` plus a module stop button
- video files use native HTML5 `<video controls>` plus a module stop button
- QuickTime/MOV files are not treated as browser-previewable and fall back to a download link
- TXT, Markdown, and GEDCOM files are read server-side from the local webtrees media directory and shown as a scrollable plain-text preview
- RTF files are converted to a simple plain-text preview; complex RTF markup may still leave visible control text or formatting artifacts
- unsupported files show a preview warning and a clearly labeled download link

No external CDN libraries are used. Viewer CSS, JavaScript, and vendored viewer libraries live in:

```text
resources/css/media-viewer.css
resources/js/media-viewer.js
vendor/openseadragon/
```

External media URLs are embedded only when they use `http` or `https`. They are loaded directly by the browser, not fetched server-side by PHP. This avoids server-side request risks. External media can still fail to display when the remote server blocks embedding, CORS, or range requests; in that case the user can open the URL directly.

Manual verification cases are tracked in [docs/media-viewer-test-matrix.md](media-viewer-test-matrix.md).

### Media Metadata

`MediaMetadataReader` reads embedded metadata from local webtrees media files without depending on another custom module. The implementation is intentionally read-only and reuses the parsing approach from the `webtrees-sammlungen` metadata code where it fits this module.

The reader extracts XMP packets directly from file content when possible and uses Imagick as a fallback for local image files. For image files, EXIF `ImageDescription`, `UserComment`, `DateTimeOriginal`, `DateTimeDigitized`, and `DateTime` are used as fallback values when the corresponding XMP fields are missing. It currently exposes:

- `dc:language` as `language`
- `dc:description` as `description`
- `xmp:CreateDate` or `xmp:ModifyDate` as `created`
- `iptcExt:PersonInImage` as `persons`
- `dc:subject` as `keywords`
- a generic `fields` list with additional extracted XMP, IPTC, and EXIF values

`MediaFilesForMediaAction` includes these metadata values in its JSON response for each media file. `MediaObjectGateway::files()` also adds them to the detail page data so FILE/FORM/TYPE/MIME can be shown together with XMP/EXIF metadata. Large metadata sets are rendered in a collapsed details block on the transcription detail page. GEDCOM `RESN` restrictions on the selected media object are shown explicitly in the information block. The manual transcription form uses the first supported `dc:language` value to preselect the primary language when the user selects a linked media object and has not already selected a language manually.

### Actions / API
- **UpdateCurrentNoteAction**: Saves the current editor text to the webtrees NOTE.
- **SaveNoteAsRevisionAction**: Archives the current NOTE state as a new immutable revision.
- **MakeRevisionCurrentAction**: Promotes an earlier revision to the current revision, updates the working NOTE from that revision, and records the generated NOTE reference.
- **CompareRevisionsAction**: Loads two revisions of the same transcription and renders metadata and text differences.
- **SourceForManualAction**: Returns only sources that the current editor may see in the tree.
- **MediaForSourceAction**: Returns only media objects of the selected source that the current editor may see in the tree.
- **CollaborationStatusAction**: Handles status changes for internal collaboration, including `ready_for_review`, `finalize`, and `reopen`.
- **ManualStatusAction**: Handles `finalize` and `reopen` for manual transcriptions.

### Admin Diagnostics

The admin settings view contains three passive diagnostics sections:

- **Database**: schema version, whether all module tables exist, and a compact table of trees with active transcriptions. The tree table shows active transcriptions and total revisions.
- **NOTE editor**: whether `linkenhancer` is installed/enabled, whether the local TinyMDE option is enabled, whether the `MarkdownEditorActivationService` is available, and whether this module registered its TinyMDE rule.
- **Markdown option by tree**: whether the webtrees tree option `FORMAT_TEXT` is set to `markdown`.

The TinyMDE rule is registered for transcription text areas when the module option is enabled. The `linkenhancer` module registers its `MarkdownEditorActivationService` in its constructor so the service is available independently of module boot order.

### Consistency Check

The admin settings view includes a **Run consistency check** button. The check is not executed automatically; it runs only for the current request after the button is clicked.

It reports:

- **Errors** for broken invariants that can prevent normal module workflows.
- **Warnings** for suspicious states that may be legitimate after manual webtrees edits but should be reviewed.

Current checks:

- active transcription references a missing family tree
- active transcription references a missing source
- active transcription references a missing media object
- referenced media object is no longer linked to the source
- metadata of media files used by transcriptions is plausible:
  filename extension, GEDCOM `FORM`, and MIME type are compared against each other
- transcription has no current NOTE
- current NOTE does not exist
- current NOTE is not linked to the expected `SOUR` or `OBJE`
- tag NOTE is missing, deleted, or linked to the wrong target while tagging is enabled
- revision belongs to a missing transcription
- revision references a deleted generated NOTE
- transcription has no revisions
- transcription has zero or multiple current revisions
- current revision references a different NOTE than `current_note_xref`
- active collaboration entry references a deleted webtrees user

---

## State machine


### States
```
enum TranscriptionStatus: string
{
case NEW = 'new';
case IN_PROGRESS = 'in_progress';
case READY_FOR_REVIEW = 'ready_for_review';
case FINAL = 'final';
case REOPENED = 'reopened';
case CANCELLED = 'cancelled';
}
```

### Transitions
```text
enum TranscriptionTransition: string
{
case START = 'start';
case SUBMIT_FOR_REVIEW = 'submit_for_review';
case APPROVE = 'approve';
case REOPEN = 'reopen';
case CANCEL = 'cancel';
}
```

---

## File Structure

```text
.
├── .gitignore
├── LICENSE
├── README.md
├── autoload.php
├── composer.json
├── latest-version.txt
├── module.php
├── docs/
│   ├── architecture.md
│   ├── database/
│   │   ├── schema-1.sql.txt
│   │   ├── schema-2.sql.txt
│   │   └── schema-3.sql.txt
│   ├── images/
│   │   └── ui/
│   │       ├── control_panel.png
│   │       ├── create_manual.png
│   │       ├── dashboard.png
│   │       └── details.png
│   └── provider/
│       ├── discourse.md
│       ├── internal.md
│       ├── manual.md
│       └── transkribus.md
├── resources/
│   ├── lang/
│   │   ├── de.mo
│   │   ├── de.po
│   │   ├── nl.mo
│   │   └── nl.po
│   └── views/
│       ├── admin-settings.phtml
│       ├── compare-revisions.phtml
│       ├── create-manual.phtml
│       ├── dashboard.phtml
│       └── detail.phtml
└── src/
    ├── SourceTranscription.php
    ├── Application/
    │   ├── Dto/
    │   │   ├── CreateTranscriptionCommand.php
    │   │   └── OpenCollaborationCommand.php
    │   ├── Factory/
    │   │   ├── NoteContentFactory.php
    │   │   └── TranscriptionProviderFactory.php
    │   ├── Provider/
    │   │   ├── CreatesTranscriptionsInterface.php
    │   │   ├── DiscourseTranscriptionProvider.php
    │   │   ├── InternalCollaborationProvider.php
    │   │   ├── ManualTranscriptionProvider.php
    │   │   ├── OpensCollaborationInterface.php
    │   │   ├── TranskribusTranscriptionProvider.php
    │   │   └── TranscriptionProviderInterface.php
    │   └── Service/
    │       ├── CollaborationNotificationService.php
    │       ├── CollaborationStatusService.php
    │       ├── CompareRevisionsService.php
    │       ├── CreateTranscriptionService.php
    │       ├── EnsureTagNoteService.php
    │       ├── GenerateOrUpdateNoteService.php
    │       ├── GetTranscriptionDetailService.php
    │       ├── OpenCollaborationService.php
    │       └── SaveNoteAsRevisionService.php
    ├── Domain/
    │   ├── Enum/
    │   │   ├── InteractionModel.php
    │   │   ├── PrimaryForm.php
    │   │   ├── PrimaryLanguage.php
    │   │   ├── PrimaryScript.php
    │   │   ├── RevisionOriginType.php
    │   │   ├── TranscriptionStatus.php
    │   │   ├── TranscriptionTransition.php
    │   │   └── TranscriptionType.php
    │   ├── Entity/
    │   │   ├── NoteLink.php
    │   │   ├── Transcription.php
    │   │   └── TranscriptionRevision.php
    │   ├── Service/
    │   │   └── TranscriptionStateMachine.php
    │   └── ValueObject/
    │       ├── CollaborationRole.php
    │       ├── NoteStrategy.php
    │       ├── ProviderKey.php
    │       ├── ProviderLabel.php
    │       └── ProviderPresentation.php
    ├── Http/
    │   └── RequestHandlers/
    │       ├── CollaborationStatusAction.php
    │       ├── CompareRevisionsAction.php
    │       ├── CreateManualAction.php
    │       ├── DashboardAction.php
    │       ├── DetailAction.php
    │       ├── ManualStatusAction.php
    │       ├── MakeRevisionCurrentAction.php
    │       ├── MediaForSourceAction.php
    │       ├── OpenCollaborationAction.php
    │       ├── SaveNoteAsRevisionAction.php
    │       ├── SourceForManualAction.php
    │       ├── StoreManualAction.php
    │       └── UpdateCurrentNoteAction.php
    ├── Infrastructure/
    │   ├── Persistence/
    │   │   ├── Repository/
    │   │   │   ├── NoteLinkRepository.php
    │   │   │   ├── RevisionRepository.php
    │   │   │   ├── TranscriptionCollaboratorRepository.php
    │   │   │   ├── SettingsRepository.php
    │   │   │   └── TranscriptionRepository.php
    │   │   └── Schema/
    │   │       ├── Migration0.php
    │   │       ├── Migration1.php
    │   │       ├── Migration2.php
    │   │       └── SchemaManager.php
    │   ├── Webtrees/
    │   │   ├── MediaObjectGateway.php
    │   │   ├── SharedNoteGateway.php
    │   │   └── SourceGateway.php
    │   └── WhatsNew/
    │       ├── WhatsNew0.php
    │       └── WhatsNewInterface.php
    └── Support/
        ├── HashService.php
        └── TranscriptionSlug.php
```

---

## Known Limitations

- Existing data created by earlier development versions may still contain old NOTE links to sources until the affected transcription or tag NOTE is saved again.
- The consistency check reports issues only. It does not repair records automatically.
- The check compares NOTE links by GEDCOM references and does not attempt semantic repair if a user intentionally moved or duplicated NOTE links outside the module.
