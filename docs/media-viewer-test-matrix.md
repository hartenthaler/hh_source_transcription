# Media Viewer Test Matrix

Use this checklist to verify the media viewer on the transcription detail page.
The media files are stored in media/test. The ID is the media object title.

## Status Symbols

Copy these symbols into the `Result` column:

- Success: `✅`
- Failed: `🛑`
- Blocked / not applicable: `-`

## Local Files

| ID | Case | GEDCOM media file | Expected viewer                                  | Result | Notes               |
| --- | --- | --- |--------------------------------------------------| --- |---------------------|
| MV-01 | Local JPEG | `FILE mv-01-local-test.jpg`, `FORM jpg` | Image viewer with zoom, pan, reset, open         | ✅ | 11 MB               |
| MV-02 | Local PNG | `FILE mv-02-local-test.png`, `FORM png` | Image viewer with zoom, pan, reset, open         | ✅ |                     |
| MV-03 | Local PDF | `FILE mv-03-local-test.pdf`, `FORM pdf` | PDF iframe preview plus open link                | ✅ |                     |
| MV-04 | Local MP3 | `FILE mv-04-local-test.mp3`, `FORM mp3` | Audio controls plus stop button, speed control   | ✅ | no free positioning |
| MV-05 | Local MP4 | `FILE mv-05-local-test.mp4`, `FORM mp4` | Video controls plus stop button, speed control   | ✅ | no free positioning |
| MV-06 | Local unsupported | `FILE mv-06-local-test.docx`, `FORM docx` | Warning plus clearly labeled download link       | ✅ |                     |
| MV-07 | Local TXT | `FILE mv-07-local-test.txt`, `FORM txt` | Scrollable text preview                          | ✅ |                     |
| MV-08 | Local RTF | `FILE mv-08-local-test.rtf`, `FORM rtf` | Plain-text preview; RTF artifacts are acceptable | ✅ |                     |
| MV-08a | Local Markdown | `FILE mv-08a-local-test.md`, `FORM md` | Scrollable plain-text preview                    | - |                     |
| MV-09 | Missing local file | `FILE mv-09-missing-test.jpg`, `FORM jpg` | webtrees 404 replacement image, no PHP error     | ✅ | system-conform      |
| MV-10 | Multiple files | one `OBJE` with JPEG, PDF, MP3 | File selector switches previews correctly        | ✅ |                     |

## External URLs

External URLs are loaded directly by the browser. Remote servers may block embedding, range requests, or hotlinking.

| ID | Case                           | GEDCOM media file                                     | Expected viewer | Result | Notes |
| --- |--------------------------------|-------------------------------------------------------| --- |--------| --- |
| MV-11 | External JPEG                  | `FILE https://example.org/mv-11-test.jpg`, `FORM jpg` | Image viewer with zoom if browser can load it | ✅      |  |
| MV-12 | External PNG                   | `FILE https://example.org/mv-12-test.png`, `FORM png` | Image viewer with zoom if browser can load it | ✅      |  |
| MV-13 | External PDF                   | `FILE https://example.org/mv-13-test.pdf`, `FORM pdf` | PDF iframe or browser-blocked fallback/open link | ✅      |  |
| MV-14 | External MP3                   | `FILE https://example.org/mv-14-test.mp3`, `FORM mp3` | Audio controls if browser can load it | ✅      |  |
| MV-15 | External MP4                   | `FILE https://example.org/mv-15-test.mp4`, `FORM mp4` | Video controls if browser can load it | ✅      |  |
| MV-16 | External TXT                   | `FILE https://example.org/mv-16-test.txt`, `FORM txt` | Scrollable text preview | ✅     |  |
| MV-17 | External unsupported scheme    | `FILE ftp://example.org/mv-17-test.jpg`, `FORM jpg`   | Not embedded; fallback link | ✅      |  |
| MV-18 | External URL without extension | `FILE https://example.org/media?id=mv-18`, `FORM jpg` | Depends on webtrees MIME detection; likely fallback unless extension is known | - |  |

## Metadata And Format Edge Cases

| ID | Case | GEDCOM media file                   | Expected viewer | Result | Notes           |
| --- | --- |-------------------------------------| --- | --- |-----------------|
| MV-19 | Wrong FORM, image extension | `FILE mv-19-test.jpg`, `FORM pdf`   | Image viewer, because filename/MIME extension wins | ✅ | FORM is ignored |
| MV-20 | Wrong FORM, PDF extension | `FILE mv-20-test.pdf`, `FORM jpg`   | PDF viewer, because filename/MIME extension wins | ✅ | FORM is ignored |
| MV-21 | Uppercase extension | `FILE MV-21-TEST.JPEG`, `FORM jpg`  | Image viewer | ✅ |                 |
| MV-22 | TIFF | `FILE mv-22-test.tif`, `FORM tif`   | Fallback or browser-dependent behavior; no PHP error | ✅ | download is ok  |
| MV-23 | WebP | `FILE mv-23-test.webp`, `FORM webp` | Image viewer if browser supports WebP | ✅ |                 |
| MV-24 | MOV | `FILE mv-24-test.mov`, `FORM mov`   | Warning plus clearly labeled download link       | ✅ |                 |

## Acceptance Criteria

- The detail page renders without PHP errors for every case.
- The media file selector switches previews without layout breakage.
- Image `+`, `-`, and reset controls work.
- Image pan works after zooming in.
- TXT/Markdown/RTF previews show readable text without PHP errors.
- Audio/video play, pause, and stop controls work where the browser supports the format.
- Unsupported files show a usable, clearly labeled download link.
- External media failures do not break the transcription editor.
