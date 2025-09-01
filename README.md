# BiT Media Tools

**Developed by:** ArkanPardaz DevTeam  
**Project started:** March 05, 2023  
**Current Version:** v2.1.0 (September 2025)  
**PHP Requirement:** PHP 8.0 or higher  
**Required Extensions:** `fileinfo` must be enabled  
**Optional Requirements:** `FFMPEG` installed if video thumbnails are to be generated

---

## 📌 Overview
**BiT Media Tools** is a **stand-alone PHP tool** for secure uploading, processing, and serving of media files (images, videos, audio, and documents).

It provides **indirect access** to content (real paths are hidden) and supports **CDN integration**, **hashed non-guessable filenames**, and optional **authentication layers** for extra security.

---

## ✨ Key Features
- Stand-alone tool (no dependency on frameworks)
- Secure indirect access to media files
- CDN support for global delivery
- Hashed filenames for non-guessable URLs
- Optional authentication/token-based access layers
- Image editing and resizing (GD2-based)
- Video uploading, caching, and streaming (HTTP Range support)
- Audio and PDF upload support
- Hides real server file paths

---

## 📂 Supported Formats
- **Images:** PNG, JPEG
- **Videos:** MP4, WebM, MOV (QuickTime)
- **Audio:** MP3, AAC, WAV, M4A
- **Documents:** PDF

---

## 🛠 Routes Guide

| Route | Method | Description | Allowed Formats | Max Size | Parameters                          | Example URL | Response Example |
|-------|--------|-------------|----------------|----------|-------------------------------------|-------------|----------------|
| `/upload` | POST | Upload a media file | Image: PNG, JPEG<br>Video: MP4, WebM, MOV<br>Audio: MP3, AAC, WAV, M4A<br>PDF: PDF | Image: 10 MB<br>Video: 100 MB<br>Audio: 50 MB<br>PDF: 20 MB | `file` – file to upload<br>multipart/form-data         | `/upload` | ```json { "state": "success", "url": "filename.ext", "type": "image/video/audio/pdf", "thumbnail": "thumbnail.jpg" } ``` |
| `/i/{file_name}` | GET | Fetch an image | PNG, JPEG | Depends on uploaded file | `{file_name}`                       | `/i/sample_image.png` | Image file output |
| `/sw/{width}/{file_name}` | GET | Resize image proportionally by width | PNG, JPEG | Depends on uploaded file | `{width}` (100–4192), `{file_name}` | `/sw/800/sample_image.jpg` | Resized image output |
| `/v/{file_name}` | GET | Fetch video with streaming support | MP4, WebM, MOV | 100 MB | `{file_name}`                       | `/v/sample_video.mp4` | Video file output with HTTP Range support |
| `/a/{file_name}` | GET | Fetch audio with streaming support | MP3, AAC, WAV, M4A | 50 MB | `{file_name}`                       | `/a/sample_audio.mp3` | Audio file output with HTTP Range support |
| `/pdf/{file_name}` | GET | Fetch PDF document | PDF | 20 MB | `{file_name}`                       | `/pdf/sample_document.pdf` | PDF file output |

---

## 📤 File Upload Endpoint

### `POST /upload`  
Upload any supported file type (`image`, `video`, `audio`, `pdf`).

### `POST /upload/{category}`  
Restrict upload to a specific category.

#### Available Categories:
- `image` → only image files (`.png`, `.jpg`, `.jpeg`)
- `video` → only video files (`.mp4`, `.webm`, `.mov`)
- `audio` → only audio files (`.mp3`, `.aac`, `.wav`, `.m4a`)
- `pdf` → only PDF documents (`.pdf`)

If the uploaded file does **not** match the category, an error will be returned.

---

### ✅ Examples

#### Upload any supported file:
```bash
curl -F "file=@myphoto.jpg" http://yourdomain.com/api.php?route=upload
```

#### Upload only an image:
```bash
curl -F "file=@myphoto.jpg" http://yourdomain.com/api.php?route=upload/image
```

#### Upload only an video:
```bash
curl -F "file=@myphoto.jpg" http://yourdomain.com/api.php?route=upload/video
```

---

## ⚠️ Error Codes

| Error Code | Meaning | Description |
|------------|---------|-------------|
| -1 | `UPLOAD_ERR` | General upload error. Check PHP upload error code in `$_FILES['file']['error']`. |
| -2 | `MAX_SIZE_EXCEEDED` | The uploaded file exceeds the maximum allowed size for its type. |
| -3 | `EMPTY_FILE` | Uploaded file has no content. |
| -4 | `INVALID_FORMAT` | The file format is not allowed. |
| -6 | `MOVE_UPLOAD_ERROR` | Failed to move uploaded file to the destination folder. |
| -8 | `AUDIO_NOT_FOUND` | Requested audio file does not exist on the server. |
| -9 | `PDF_NOT_FOUND` | Requested pdf file does not exist on the server. |
| -7 | `VIDEO_NOT_FOUND` | Requested video file does not exist on the server. |
| -10 | `BAD_QUERY` | Bad or missing query parameter in the route. |
| -11 | `METHOD_NOT_ALLOWED` | HTTP method not allowed for this route (e.g., GET on upload). |
| 0  | `FILE_NOT_UPLOADED` | No file was uploaded or `file` parameter missing in POST request. |

### 🔹 Notes
- All error responses are returned as JSON:
```json
{
  "state": "error",
  "error_code": -4,
  "error_message": "The file format is not allowed"
}
```
- state is always "error" for error responses.
- error_code is a unique integer to identify the type of error.
- error_message is a human-readable explanation of the problem.