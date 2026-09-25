# Spec 012: Voice & Video Reviews

**Status:** Draft · **Phase:** 2 · **Depends on:** 003, 006
**Client requirements:** E) Voice / video reviews

## 1. Goal

Some people explain an experience better by talking than by typing, and hearing a real customer is persuasive. Let reviewers attach a short voice note or video to a review. Transcripts make it accessible and searchable, and moderation and privacy protection keep it safe.

## 2. User Scenarios

1. **Record a voice note.** While writing a review on their phone, a consumer taps "Add voice note", records 45 seconds, previews it, and submits. After processing, the review shows an audio player with a transcript underneath.
2. **Upload a video.** A consumer uploads a 60-second video showing a damaged suitcase. The video is transcoded, captioned automatically, screened, and published with the review. The consumer can edit the captions before publishing.
3. **Media fails screening.** A video shows another passenger's face clearly. The system detects the face and holds the media for moderation. The moderator blurs the face and approves the media, or asks the author to re-upload. The text review can publish without waiting for the media.
4. **Reader plays media.** A reader filters a profile by "Has video" and watches videos with captions on. Media loads only when the reader presses play.
5. **Author removes media.** The author deletes the video from their review, and it disappears. The original file is deleted within 30 days.

## 3. Functional Requirements

- **FR-012-01** A review (or a lifecycle update) may include **at most one** media item: a voice note **or** a video. Media is allowed **only on reviews with an active Verified Experience attestation** (004). If the attestation is revoked, the media is hidden.
- **FR-012-02** Voice note limits: 5–120 seconds, ≤ 20 MB. Accepted input formats: common audio (AAC/M4A, MP3, Opus/WebM, WAV).
- **FR-012-03** Video limits: 5–90 seconds, ≤ 200 MB, up to 1080p. Accepted input formats: MP4/H.264, MOV, WebM. Output is transcoded to adaptive streaming renditions.
- **FR-012-04** In-browser/in-app recording and upload of existing files must both be supported. Uploads must be resumable for files > 20 MB.
- **FR-012-05** All media must be scanned for malware and its format checked, and all metadata (EXIF/GPS, device) must be removed before storage.
- **FR-012-06** A **transcript** (audio) or **captions** (video) must be generated automatically. The author can edit them before publishing and later. **Media cannot be published without a transcript or captions** (P8). The transcript is screened as text (006) and counts as review content for search and AI (011), but does **not** replace the required written text of the review (003).
- **FR-012-07** Media screening (006) must include: nudity/sexual content, violence/gore, hate symbols, spoken or visible personal data (phone numbers, emails on screen), **identifiable faces of people other than the author**, and copyrighted music detection. Detected issues put the media on **hold**. Staff can approve, blur/mute a segment, or reject.
- **FR-012-08** The text part of a review publishes on its own schedule. The media appears when approved. The media status (`processing`, `held`, `published`, `rejected`) is visible to the author.
- **FR-012-09** Playback: media must not autoplay, must load only after the user presses play (or a poster image for video), must have keyboard-accessible controls, and must show captions on by default where the viewer's accessibility preference says so.
- **FR-012-10** Review lists support the filters "Has voice note" and "Has video" (extends 003 FR-003-28).
- **FR-012-11** Businesses may **not** download media files. They see the same public playback as consumers, and media can't be embedded in widgets (016) without the author's opt-in consent (default off).
- **FR-012-12** When media is deleted by the author, or the review is removed or deleted, the media must stop being served within 60 seconds (CDN purge), and originals and renditions must be deleted within 30 days (constitution §5.3).
- **FR-012-13** Media reviews carry no extra weight in any score (008).

## 4. Edge Cases & Rules

| Case | Rule |
|------|------|
| Empty/silent audio or black video | Reject (silence/black detection over > 90% of the duration). |
| Duration below or above limits, or file above size | Reject before upload completes (client check) and again on the server. |
| Corrupt file or unsupported codec | Reject with the list of supported formats. |
| Upload interrupted | Resumable. Incomplete uploads are deleted after 24 hours. |
| Two media items on one review | Reject the second. The author must remove the first. |
| Media added to an unverified review | Reject: "Verify your experience to add a voice note or video". |
| Video contains the author's own face | Allowed. |
| Children identifiable in video | Hold. Publish only with blur. |
| Language not supported by transcription | Hold for manual captioning. The author can type the transcript themselves. |
| Transcript edited to say something different from the audio | Edits are screened. Big differences (automatic similarity check) go to moderation. |
| Background copyrighted music | Hold. The staff option "mute audio" is available. |
| Unauthorized: a business requests the original file URL | 403. Only signed, short-lived streaming URLs are given, to viewers. |

## 5. Out of Scope

- Photo attachments (possible separate spec).
- Live streaming or video longer than 90 seconds.
- Media on business replies or case messages (case attachments in 010 are files, not published media).
- Voice-only reviews without written text.
- Businesses paying to feature video reviews.

## 6. Acceptance Criteria

- [ ] Recording and upload work on current mobile and desktop browsers. Limits are enforced on client and server.
- [ ] Metadata is stripped (test checks a GPS-tagged fixture).
- [ ] No media publishes without a transcript or captions (test).
- [ ] The screening hold path works for each category in FR-012-07, and staff blur/mute tools work.
- [ ] Deletion purges the CDN within 60 s and storage within 30 days (time-travel test).
- [ ] The player passes accessibility checks (keyboard, captions, no autoplay).
- [ ] Businesses cannot download originals (authorization test).

## 7. Dependencies & Open Questions

- **Q1:** Media processing and transcription providers (decided in plan.md, and included in the DPIA because voice is personal data).
- **Decided (2026-09-24):** media is only allowed on Verified Experience reviews (FR-012-01).
