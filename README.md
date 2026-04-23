# webtrees module for Source Transcriptions (hh_source_transription)

[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](http://www.gnu.org/licenses/gpl-3.0)

![webtrees major version](https://img.shields.io/badge/webtrees-v2.2.x-green)

This [webtrees](https://www.webtrees.net) custom module manages transcriptions of sources and source media.

<a name="Contents"></a>
## Contents

This Readme contains the following main sections

* [Purpose](#Purpose)
* [Scope](#Scope)
* [Main ideas](#Main)
* [Data model](#Data)
* [Database schema](#Database)
* [Design principles](#Design)
* [Workflow](#Workflow)
* [Current status](#Status)
* [Discussion points](#Discussion)
* [Current status](#Current)
* [Discussion points](#Discussion)
* [Literature](#Literature)
* [Requirements](#Requirements)
* [Installation](#Installation)
* [Upgrade](#Upgrade)
* [Translation](#Translation)
* [Contact Support](#Support)
* [License](#License)

<a name="Purpose"></a>
## Purpose

Genealogical sources often contain handwritten or otherwise difficult-to-read texts.  
This module adds a structured workflow for creating, importing, managing, and revising transcriptions in webtrees.

External or internal text recognition tools can support the transcription; the module is intentionally provider-agnostic.

Version 1 starts with two transcription providers

- **Manual** – transcriptions entered and maintained by an editor directly in webtrees
- **Transkribus** – transcriptions created externally in Transkribus and imported into webtrees

The architecture is designed to support additional providers, like other AI tools, later.

<a name="Scope"></a>
## Scope

The module links transcriptions to

- a **source** (`SOUR`)
- optionally a specific **media object** (`OBJE`) attached to a source

The media object contains a media file with one or more pages of images (jpg, pdf, tiff, ...)

A transcription is not just a note.  
It is treated as a structured object with

- metadata
- a provider
- a status
- a revision history
- a current working note in webtrees (`SOUR:NOTE` or `SOUR:OBJE:NOTE`)

<a name="Main"></a>
## Main ideas
The goal is to link a Digital Humanities edition system with a genealogical data model. The long-term goal is therefore a structured data collection with TEI parsing and integrated GEDCOM generation. This means that in the advanced transcription process, webtrees objects such as people, places and events should be created or linked. That could be a first introduction to turn the result-oriented webtrees into a process- and evidence-based program.

### 1. Provider-based architecture

The module itself does not assume a single transcription workflow.

Instead, it defines a provider interface.  
Providers can support different workflows, such as

- manual transcription
- Transkribus import/synchronisation
- future OCR/HTR tools
- file import (TXT, TEI, PAGE XML)
- local AI-based recognition

### 2. Revision history

The actual transcription history is stored in module database tables.

Each revision contains

- origin/provider
- text content
- format
- hash
- timestamp
- optional origin reference (e.g. user, tool version, used transcription model, ...)

This means that revisions remain stable even if the associated webtrees note is edited later.

### 3. webtrees NOTE as working copy

The module can generate or update a shared NOTE linked to the source. This NOTE is the current working version shown and edited in webtrees. Only that NOTE is exported via GEDCOM.

Important

- the NOTE is **not** the authoritative revision history
- the revision history is stored separately in database tables
- the NOTE may be edited manually by users
- edited notes can optionally be saved as new manual revisions

#### Structure of NOTE
  `Transcription`
  `Source: Kirchenbuch Musterort 1780–1810`
  `Medium: Seite 23`
  `Revision: 3`
  `Provider: Transkribus`
  `Imported: 20260428/11:12:13`

  `--- Begin transcription ---`

  `...`

  `--- End transcription ---`

### 4. Tagging of sources

Sources with at least one transcription can be marked by an additional shared NOTE, such as:

`TAG: Transcription`

This supports genealogical workflow management and filtering.

<a name="Data"></a>
## Data model

### Transcription

A transcription is the main domain object.

Typical properties

- source or optional media object related to a source
- provider (manual, Transkribus, ...)
- title
- type (handwritten Sütterlin, ...)
- language (German, Latin, ...)
- status (in progress, finished, ...)
- current note (text enriched by Markdown)

### Revision

A revision is a specific text state of a transcription.

Possible origins in version 1

- `manual_entry`
- `manual_note_save`
- `transkribus_import`
- `transkribus_sync`

### Note link

The module tracks which NOTE was generated from which revision and whether that NOTE is currently active.

## Providers in version 1

### Manual provider

The manual provider supports

- creating a new transcription in webtrees
- creating an initial empty revision
- generating a working NOTE
- saving the current NOTE as a new manual revision

### Transkribus provider

The Transkribus provider supports

- creating a transcription associated with a source media object file
- importing transcription text from Transkribus
- creating a new revision from imported text
- updating or generating a current working NOTE

<a name="Database"></a>
## Database schema

Version 1 uses the following tables:

- `wt_transcription_metadata`
- `wt_transcriptions`
- `wt_transcription_revisions`
- `wt_transcription_note_links`

The module uses an explicit schema version to allow future migrations.

<a name="Design"></a>
## Design principles

- keep the module **provider-agnostic**
- keep revision history separate from editable webtrees notes
- avoid destructive overwrites of manually changed notes
- support both simple and advanced workflows
- make future providers easy to add

<a name="Workflow"></a>
## Workflow

### Manual
1. Open a source
2. Create a transcription
3. Select provider: Manual
4. Generate a working note
5. Edit the note in webtrees
6. Save the note as a new revision when needed

or select an already existing NOTE containing transcribed text as a new revision.

### Transkribus
1. Open a source with a media object
2. Create a transcription
3. Select provider: Transkribus
4. Link or upload the media externally
5. Present a link to Transkribus for the user
6. Import a text state into webtrees
7. Store it as a revision
8. Generate or update the current working note

<a name="Status"></a>
## Current status

This project is currently in the design and initial development phase.

The first implementation goal is

- schema installation and migration support
- manual provider
- revision storage
- note generation and note conflict detection

The Transkribus integration will follow on top of that generic foundation.

<a name="Discussion"></a>
## Discussion points

The following points are still open for discussion

- Do you know any other genealogy programs that support the transcription process well (best practices) and at the same time use genealogical data structures?
- How to store the position of a transcribed line as position in the image?
- Should a source receive one generic tag note (`TAG: Transcription`) or provider-specific tag notes as well?
- Should the default note strategy be “always create new note” or “update if unchanged”?
- Should multiple transcription types (transcription, translation, normalised, or modernised text) already be visible in version 1?
- How should media selection be handled if a source has multiple media objects?
- How to integrate named entities, links to persons and to locations in future versions?

<a name="Literature"></a>
## Literature

- Zedlitz, Jesper: "Gedbas4all - neues Datenmodell für die Genealogie", in COMPUTERGENEALOGIE 4/2009, S. 15-18.
- GENTECH Genealogical Data Model, May 29, 2000, https://xml.coverpages.org/GENTECH-DataModelV11.pdf (checked 23.04.2026).

<a name="Requirements"></a>
## Requirements

This module requires **webtrees** version 2.2 or later.
This module has the same requirements as [webtrees#system-requirements](https://github.com/fisharebest/webtrees#system-requirements).

This module was tested with **webtrees** 2.2.5 version
and all available themes and all other custom modules.

<a name="Installation"></a>
## Installation

Install and use [Custom Module Manager](https://github.com/Jefferson49/CustomModuleManager) for an easy and convenient installation of webtrees custom modules.
+ Open the Custom Module Manager view in webtrees, scroll to "Source Transcription", and click on the "Install Module" button.

**Manual installation**:

1. Make database backup
1. Download the [latest release](https://github.com/hartenthaler/hh_source_transcription/releases/latest)
1. Unzip the package into your `webtrees/modules_v4` directory of your web server
1. Rename the folder to `hh_source_transcription`
1. Login to **webtrees** as administrator, go to <span class="pointer">Control Panel/Modules/Individual page/Tabs</span>, and find the module. It will be called "Source Transcription". Check if it has a tick for "Enabled".
1. Finally, click SAVE, to complete the installation.

<a name="Upgrade"></a>
## Upgrade

To update, simply replace the `hh_source_transcription` files with the new ones from the latest release.

<a name="Translation"></a>
## Translation

You can help to translate this module.
The language information is stored in the folder "resources/lang/".
You can edit those files and return them to me.
You can do this via a pull request (if you know how) or by e-mail.
Updated translations will be included in the next release of this module.

There are, besides English and German, no other translations available.

<a name="Support"></a>
## Support

<span style="font-weight: bold;">Issues: </span> You can report errors by raising an issue in this GitHub repository.

<span style="font-weight: bold;">Forum: </span>General webtrees support can be found at the [webtrees forum](http://www.webtrees.net/).

<a name="License"></a>
## License

This module uses GPL-3.0-or-later as a license.

* Copyright (C) 2026 Hermann Hartenthaler
* Derived from **webtrees** - Copyright 2026 webtrees development team.

This program is free software: you can redistribute it and/or modify it
under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
