
# Source Transcriptions

A custom module for [webtrees](https://www.webtrees.net/) to manage transcriptions of sources and source media.

## Purpose

Genealogical sources often contain handwritten or otherwise difficult-to-read texts.  
This module adds a structured workflow for creating, importing, managing, and revising transcriptions directly in webtrees.

The module is intentionally provider-agnostic.

Version 1 starts with two transcription providers:

- **Manual** – transcriptions entered and maintained directly in webtrees
- **Transkribus** – transcriptions created externally in Transkribus and imported into webtrees

The architecture is designed to support additional providers later.

## Scope

The module links transcriptions to:

- a **source** (`SOUR`)
- optionally a specific **media object** (`OBJE`) attached to that source

A transcription is not just a note.  
It is treated as a structured object with:

- metadata
- a provider
- a status
- a revision history
- a current working note in webtrees

## Main ideas

### 1. Provider-based architecture

The module itself does not assume a single transcription workflow.

Instead, it defines a provider interface.  
Providers can support different workflows such as:

- manual transcription
- Transkribus import/synchronization
- future OCR/HTR tools
- file import (TXT, TEI, PAGE XML)
- local AI-based recognition

### 2. Revision history

The actual transcription history is stored in module tables.

Each revision contains:

- origin/provider
- text content
- format
- hash
- timestamp
- optional origin reference

This means that revisions remain stable even if the associated webtrees note is edited later.

### 3. webtrees NOTE as working copy

The module can generate or update a shared NOTE linked to the source.

This NOTE is the current working version shown and edited in webtrees.

Important:

- the NOTE is **not** the authoritative revision history
- the revision history is stored separately in database tables
- the NOTE may be edited manually by users
- edited notes can optionally be saved as new manual revisions

### 4. Tagging of sources

Sources with at least one transcription can be marked by an additional shared NOTE such as:

`TAG: Transcription`

This supports genealogical workflow management and filtering.

## Data model

### Transcription

A transcription is the main domain object.

Typical properties:

- source
- optional media object
- provider
- title
- type
- status
- current note
- tag note

### Revision

A revision is a specific text state of a transcription.

Possible origins in version 1:

- `manual_entry`
- `manual_note_save`
- `transkribus_import`
- `transkribus_sync`

### Note link

The module tracks which NOTE was generated from which revision and whether that NOTE is currently active.

## Providers in version 1

### Manual provider

The manual provider supports:

- creating a new transcription in webtrees
- creating an initial empty revision
- generating a working NOTE
- saving the current NOTE as a new manual revision

### Transkribus provider

The Transkribus provider is planned to support:

- creating a transcription associated with a source media object
- importing transcription text from Transkribus
- creating new revisions from imported text
- updating or generating a current working NOTE

## Database schema

Version 1 uses the following tables:

- `wt_transcription_metadata`
- `wt_transcriptions`
- `wt_transcription_revisions`
- `wt_transcription_note_links`

The module uses an explicit schema version to allow future migrations.

## Design principles

- keep the module **provider-agnostic**
- keep revision history separate from editable webtrees notes
- avoid destructive overwrites of manually changed notes
- support both simple and advanced workflows
- make future providers easy to add

## Planned workflow

### Manual
1. Open a source
2. Create a transcription
3. Select provider: Manual
4. Generate a working note
5. Edit the note in webtrees
6. Save the note as a new revision when needed

### Transkribus
1. Open a source with media
2. Create a transcription
3. Select provider: Transkribus
4. Link or upload the media externally
5. Import a text state into webtrees
6. Store it as a revision
7. Generate or update the current working note

## Current status

This project is currently in the design and initial development phase.

The first implementation goal is:

- schema installation and migration support
- manual provider
- revision storage
- note generation and note conflict detection

The Transkribus integration will follow on top of that generic foundation.

## Discussion points

The following points are still open for discussion:

- Should a source receive one generic tag note (`TAG: Transcription`) or provider-specific tag notes as well?
- Should the default note strategy be “always create new note” or “update if unchanged”?
- Should multiple transcription types (transcription, translation, normalized text) already be visible in version 1?
- How should media selection be handled if a source has multiple media objects?

## License

GPL-3.0-or-later
