# Revision concurrency

Revision numbers are scoped to one transcription. The module must therefore prevent two concurrent save requests for the same transcription from assigning the same `revision_no`.

## Risk

The unsafe pattern is:

```text
next = MAX(revision_no) + 1
INSERT revision(next)
```

If two requests run this sequence concurrently without locking or a uniqueness constraint, both can observe the same maximum and insert duplicate revision numbers.

## Current protection

Revision creation now uses two safeguards:

- `RevisionRepository::lockTranscriptionForRevisionAllocation()` locks the parent transcription row with `lockForUpdate()` before the latest revision and next revision number are evaluated.
- Schema version 6 adds a unique index on `(transcription_id, revision_no)`.

The lock serializes revision allocation for one transcription while allowing unrelated transcriptions to proceed independently. The unique index provides a database-level safety net and prevents silent duplicate numbers even if a database backend cannot enforce row locks in the same way.

Before the unique index is added, migration 5 repairs already existing duplicate revision numbers deterministically. Existing unique numbers are preserved; later duplicates are moved to the next free number within the same transcription.

## Manual test idea

This is difficult to reproduce reliably without artificial delays. A practical test is:

1. Open the same transcription in two browser sessions.
2. Change the current NOTE so a new revision will be created.
3. Trigger "save current NOTE as revision" in both sessions as closely together as possible.
4. Verify that the revision list has no duplicate `revision_no` values for the transcription.
5. If both requests save identical content, verify that only one new revision is created.

For a stronger test, add a temporary delay between the parent-row lock and the insert in a local development copy, then repeat the two concurrent requests. This delay must not be committed.
