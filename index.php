<?php
/*
    - BiT Media Tools
    - Developed by ArkanPardaz (Siavash Lashkarboluki)
    - Project started: March 05, 2023
    - Current Version: v2.2.0 (September 2025)

    - Description:
        BiT Media Tools is a **stand-alone PHP tool** for secure uploading, processing,
        and serving of media files (images, videos, audio, and documents).
        It provides **indirect access** to content (real paths are hidden) and supports
        **CDN integration**, **hashed non-guessable filenames**, and optional **authentication layers**
        for extra security.

    - Key Features:
        * Stand-alone tool (no dependency on frameworks)
        * Secure indirect access to media files
        * CDN support for global delivery
        * Hashed filenames for non-guessable URLs
        * Supports authentication and token-based access layers
        * Image editing and resizing (GD2-based)
        * Video uploading, caching, and streaming (HTTP Range)
        * Audio and PDF upload support
        * Hides real server file paths

    - Supported Formats:
        * Images: PNG, JPEG
        * Videos: MP4, WebM, MOV (QuickTime)
        * Audio: MP3, AAC, WAV, M4A
        * Documents: PDF

    - Changelog:
        v2.2.0:
            + Added support limited category upload (e.g., only images or only videos)
            + Improved error handling and messages
        v2.1.0:
            + Added support for audio (MP3, AAC, WAV, M4A)
            + Added support for PDF documents
        v2.0.0:
            + Video upload functionality
            + File caching system
            + Streaming support (HTTP Range)
        v1.x:
            + Initial release
            + Image upload and resizing
*/

// ---------------- HEADERS ---------------- //
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");

// ---------------- CONFIG ---------------- //
define("NOT_FOUND_IMAGE", "not_found.png");
define("FFMPEG_IS_AVAILABLE", false);
$upload_folder = __DIR__ . "/content/";

$max_size = [
    "image" => 10485760,
    "video" => 104857600,
    "audio" => 52428800,
    "pdf"   => 20971520
];

$allowed_types = [
    "image/png",
    "image/jpeg",
    "video/mp4",
    "video/webm",
    "video/quicktime",
    "application/pdf",
    "audio/mpeg",
    "audio/aac",
    "audio/wav",
    "audio/x-wav",
    "audio/mp4",
    "audio/x-m4a"
];

$file_name_prefix = null;

// ---------------- ROUTER ---------------- //
$route = $_GET["route"] ?? null;
if (!$route) sendError("Bad query", -10);

$parts = explode("/", $route);
$main = $parts[0] ?? null;
$sub = $parts[1] ?? null;
$value = $parts[2] ?? null;

switch ($main) {
    case "upload":
        if ($_SERVER['REQUEST_METHOD'] === "POST") {
            upload($sub); // ✅ دسته‌بندی به صورت optional
        } else {
            sendError("Only POST allowed for upload", -11);
        }
        break;

    case "i":
        if (!empty($sub)) {
            getImage($sub);
        } else {
            sendError("Bad query", -10);
        }
        break;

    case "sw":
        if (is_numeric($sub) && $sub > 100 && $sub < 4192 && !empty($value)) {
            resizeScaleWidth((int)$sub, $value);
        } else {
            sendError("Bad query", -10);
        }
        break;

    case "v":
        if (!empty($sub)) {
            getVideo($sub);
        } else {
            sendError("Bad query", -10);
        }
        break;

    case "a":
        if (!empty($sub)) {
            getAudio($sub);
        } else {
            sendError("Bad query", -10);
        }
        break;

    case "pdf":
        if (!empty($sub)) {
            getPDF($sub);
        } else {
            sendError("Bad query", -10);
        }
        break;

    default:
        sendError("Bad query", -10);
}

// ---------------- FUNCTIONS ---------------- //

function upload($expected_category = null)
{
    global $allowed_types, $max_size, $upload_folder;

    if (!isset($_FILES["file"])) {
        sendError("File not uploaded correctly", 0);
    }

    $file = $_FILES["file"];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        sendError("File upload error: {$file['error']}", -1);
    }

    if ($file['size'] <= 0) {
        sendError("Uploaded file has no content", -3);
    }

    $fi = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($fi, $file['tmp_name']);
    finfo_close($fi);

    if (!in_array($mime, $allowed_types)) {
        sendError("The file format is not allowed", -4);
    }

    // تشخیص دسته فایل
    if (strpos($mime, "image/") === 0) {
        $category = "image";
    } elseif (strpos($mime, "video/") === 0) {
        $category = "video";
    } elseif (strpos($mime, "audio/") === 0) {
        $category = "audio";
    } elseif ($mime === "application/pdf") {
        $category = "pdf";
    } else {
        sendError("Unsupported file type", -5);
    }

    // ✅ بررسی اینکه دسته فایل با URL مطابقت دارد یا نه
    if ($expected_category !== null && $expected_category !== $category) {
        sendError("Only {$expected_category} files are allowed in this route", -12);
    }

    if ($file['size'] > $max_size[$category]) {
        sendError("Max file size for {$category} is {$max_size[$category]} bytes", -2);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $base_name = fileNameFormatter(pathinfo($file['name'], PATHINFO_FILENAME));
    $final_file_name = $base_name . "." . $ext;

    if (!is_dir($upload_folder)) {
        mkdir($upload_folder, 0755, true);
    }

    $final_path = $upload_folder . $final_file_name;

    if (move_uploaded_file($file['tmp_name'], $final_path)) {

        $response = [
            "state" => "success",
            "url" => $final_file_name,
            "type" => $category
        ];

        if ($category === "video" && FFMPEG_IS_AVAILABLE) {
            $thumb_file = $base_name . "_thumb.jpg";
            $thumb_path = $upload_folder . $thumb_file;

            $cmd = "ffmpeg -i " . escapeshellarg($final_path) . " -ss 00:00:02.000 -vframes 1 " . escapeshellarg($thumb_path) . " -y";
            exec($cmd, $output, $return_var);

            if ($return_var === 0 && file_exists($thumb_path)) {
                $response["thumbnail"] = $thumb_file;
            } else {
                $response["thumbnail_error"] = "Could not generate thumbnail";
            }
        }

        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } else {
        sendError("Move uploaded file error", -6);
    }
}

// بقیه توابع بدون تغییر:
function getImage($file_name)
{
    global $upload_folder;
    $path = $upload_folder . $file_name;
    if (!file_exists($path)) {
        $path = NOT_FOUND_IMAGE;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === "png") {
        header("Content-type: image/png");
    } elseif (in_array($ext, ["jpg", "jpeg"])) {
        header("Content-type: image/jpeg");
    } else {
        header("Content-type: image/png");
        $path = NOT_FOUND_IMAGE;
    }
    setCacheHeaders();
    readfile($path);
    exit;
}

function getVideo($file_name)
{
    global $upload_folder;
    $path = $upload_folder . $file_name;
    if (!file_exists($path)) {
        sendError("Video not found", -7);
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = ($ext === "mov") ? "video/quicktime" : "video/" . $ext;
    header("Content-Type: {$mime}");
    header("Content-Length: " . filesize($path));
    setCacheHeaders();
    if (isset($_SERVER['HTTP_RANGE'])) {
        rangeDownload($path);
    } else {
        readfile($path);
    }
    exit;
}

function getAudio($file_name)
{
    global $upload_folder;
    $path = $upload_folder . $file_name;
    if (!file_exists($path)) {
        sendError("Audio not found", -8);
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime_map = [
        "mp3" => "audio/mpeg",
        "aac" => "audio/aac",
        "wav" => "audio/wav",
        "m4a" => "audio/mp4"
    ];
    $mime = $mime_map[$ext] ?? "application/octet-stream";
    header("Content-Type: {$mime}");
    header("Content-Length: " . filesize($path));
    setCacheHeaders();
    if (isset($_SERVER['HTTP_RANGE'])) {
        rangeDownload($path);
    } else {
        readfile($path);
    }
    exit;
}

function getPDF($file_name)
{
    global $upload_folder;
    $path = $upload_folder . $file_name;
    if (!file_exists($path)) {
        sendError("PDF not found", -9);
    }
    header("Content-Type: application/pdf");
    header("Content-Length: " . filesize($path));
    setCacheHeaders();
    readfile($path);
    exit;
}

function resizeScaleWidth($width, $file_name)
{
    global $upload_folder;
    $path = $upload_folder . $file_name;
    if (!file_exists($path)) {
        $path = NOT_FOUND_IMAGE;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === "png") {
        $image = imagecreatefrompng($path);
        $img = imagescale($image, $width, -1);
        header("Content-type: image/png");
        setCacheHeaders();
        imagepng($img);
    } elseif (in_array($ext, ["jpg", "jpeg"])) {
        $image = imagecreatefromjpeg($path);
        $img = imagescale($image, $width, -1);
        header("Content-type: image/jpeg");
        setCacheHeaders();
        imagejpeg($img);
    } else {
        header("Content-type: image/png");
        setCacheHeaders();
        readfile(NOT_FOUND_IMAGE);
    }
    exit;
}

// ---------------- HELPERS ---------------- //
function fileNameFormatter($file_name, $mode = 3)
{
    global $file_name_prefix;
    switch ($mode) {
        case 3:
            return (empty($file_name_prefix))
                ? bin2hex(random_bytes(16))
                : $file_name_prefix . bin2hex(random_bytes(16));
        default:
            return pathinfo($file_name, PATHINFO_FILENAME);
    }
}

function setCacheHeaders($seconds = 31536000)
{
    header("Cache-Control: public, max-age={$seconds}, immutable");
    header("Expires: " . gmdate("D, d M Y H:i:s", time() + $seconds) . " GMT");
}

function sendError($message, $code)
{
    $response = [
        'state' => "error",
        'error_code' => $code,
        'error_message' => $message
    ];
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function rangeDownload($file)
{
    $fp = @fopen($file, 'rb');
    $size = filesize($file);
    $length = $size;
    $start = 0;
    $end = $size - 1;

    if (isset($_SERVER['HTTP_RANGE'])) {
        $c_start = $start;
        $c_end = $end;
        list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
        if (strpos($range, ',') !== false) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            exit;
        }
        if ($range == '-') {
            $c_start = $size - substr($range, 1);
        } else {
            $range = explode('-', $range);
            $c_start = $range[0];
            $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
        }
        $c_end = ($c_end > $end) ? $end : $c_end;
        if ($c_start > $c_end || $c_start > $size - 1) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            exit;
        }
        $start = $c_start;
        $end = $c_end;
        $length = $end - $start + 1;
        fseek($fp, $start);
        header('HTTP/1.1 206 Partial Content');
    }

    header("Content-Range: bytes $start-$end/$size");
    header("Accept-Ranges: bytes");
    header("Content-Length: " . $length);

    $buffer = 1024 * 8;
    while (!feof($fp) && ($p = ftell($fp)) <= $end) {
        if ($p + $buffer > $end) {
            $buffer = $end - $p + 1;
        }
        set_time_limit(0);
        echo fread($fp, $buffer);
        flush();
    }
    fclose($fp);
    exit;
}
