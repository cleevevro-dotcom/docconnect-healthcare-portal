<?php
/**
 * Image upload helpers.
 *
 * This file was missing from the original project; it is reconstructed locally
 * so the profile / chat image upload features work. It exposes the three
 * functions used elsewhere in the app:
 *   - handleImageUpload($file, $uploadDir, $prefix)
 *   - deleteImage($path)
 *   - getDefaultProfilePicture($role)
 */

if (!function_exists('getDefaultProfilePicture')) {
    /**
     * Web path to the default profile image for a given role.
     */
    function getDefaultProfilePicture($role = 'patient')
    {
        if ($role === 'doctor') {
            return '/Healthcare_portal/Pages/Doctors/uploads/default_profile.png';
        }
        if ($role === 'admin') {
            return '/Healthcare_portal/Pages/Admins/uploads/default_profile.png';
        }
        return '/Healthcare_portal/Pages/Patients/uploads/default_profile.png';
    }
}

if (!function_exists('handleImageUpload')) {
    /**
     * Validate and store an uploaded image.
     *
     * @param  array  $file      A single entry from $_FILES.
     * @param  string $uploadDir Directory (relative to the document root) to store the file in.
     * @param  string $prefix    Filename prefix.
     * @return array{success: bool, path?: string, error?: string}
     */
    function handleImageUpload($file, $uploadDir, $prefix = 'img')
    {
        if (!is_array($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'No file was uploaded.'];
        }

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            return ['success' => false, 'error' => 'Invalid file type. Only JPG, JPEG, PNG, GIF and WEBP are allowed.'];
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            return ['success' => false, 'error' => 'File is too large (maximum 5 MB).'];
        }

        $subDir = str_replace('/', DIRECTORY_SEPARATOR, trim($uploadDir, '/\\'));
        $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
        $absDir  = $docRoot . DIRECTORY_SEPARATOR . $subDir;

        if (!is_dir($absDir)) {
            @mkdir($absDir, 0777, true);
        }
        if (!is_dir($absDir) || !is_writable($absDir)) {
            return ['success' => false, 'error' => 'Upload directory is not writable: ' . $uploadDir];
        }

        $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target   = $absDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return ['success' => false, 'error' => 'Failed to save the uploaded file.'];
        }

        return ['success' => true, 'path' => trim($uploadDir, '/\\') . '/' . $filename];
    }
}

if (!function_exists('deleteImage')) {
    /**
     * Delete a previously uploaded image (no error if it does not exist).
     */
    function deleteImage($path)
    {
        if (empty($path)) {
            return false;
        }
        $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
        $rel     = str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
        $abs     = $docRoot . DIRECTORY_SEPARATOR . $rel;

        if (is_file($abs)) {
            return @unlink($abs);
        }
        return false;
    }
}
