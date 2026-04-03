<?php

namespace SwiftPHP\Upload;

use Exception;

class Upload
{
    protected $file = [];
    protected $error = '';
    protected $savePath;
    protected $allowedTypes = [];
    protected $maxSize = 0;
    protected $savedPath = '';

    public function __construct(string $field = '', array $config = [])
    {
        if (empty($field)) {
            return;
        }

        $this->savePath = $config['save_path'] ?? \SwiftPHP\Path\Path::getRootPath() . '/runtime/upload';
        $this->allowedTypes = $config['allowed_types'] ?? [];
        $this->maxSize = $config['max_size'] ?? 0;

        if (!isset($_FILES[$field])) {
            $this->error = 'File not found';
            return;
        }

        $this->file = $_FILES[$field];
    }

    public static function make(string $field = '', array $config = []): self
    {
        return new self($field, $config);
    }

    public function save(string $name = ''): bool
    {
        if (!$this->check()) {
            return false;
        }

        if (!is_dir($this->savePath)) {
            mkdir($this->savePath, 0755, true);
        }

        $extension = strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));

        if (empty($name)) {
            $name = md5(uniqid(mt_rand(), true)) . '.' . $extension;
        } else {
            $name = $name . '.' . $extension;
        }

        $fullPath = $this->savePath . '/' . $name;

        if (!move_uploaded_file($this->file['tmp_name'], $fullPath)) {
            $this->error = 'Failed to save file';
            return false;
        }

        $this->savedPath = $fullPath;
        return true;
    }

    public function check(): bool
    {
        if (empty($this->file)) {
            $this->error = 'No file uploaded';
            return false;
        }

        if ($this->file['error'] !== UPLOAD_ERR_OK) {
            $this->error = $this->getUploadErrorMessage($this->file['error']);
            return false;
        }

        if ($this->maxSize > 0 && $this->file['size'] > $this->maxSize) {
            $this->error = 'File size exceeds maximum allowed';
            return false;
        }

        if (!empty($this->allowedTypes)) {
            $extension = strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $this->allowedTypes)) {
                $this->error = 'File type not allowed';
                return false;
            }
        }

        return true;
    }

    protected function getUploadErrorMessage(int $errorCode): string
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
        ];

        return $errors[$errorCode] ?? 'Unknown upload error';
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getSavedPath(): string
    {
        return $this->savedPath;
    }

    public function getFileName(): string
    {
        return $this->file['name'] ?? '';
    }

    public function getFileSize(): int
    {
        return $this->file['size'] ?? 0;
    }

    public function getFileType(): string
    {
        return $this->file['type'] ?? '';
    }

    public function getExtension(): string
    {
        return strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
    }

    public function getOriginalName(): string
    {
        return $this->file['name'] ?? '';
    }

    public function isImage(): bool
    {
        $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
        return in_array($this->getExtension(), $imageTypes);
    }

    public static function multiple(string $field = '', array $config = []): array
    {
        $files = [];

        if (!isset($_FILES[$field])) {
            return $files;
        }

        $count = count($_FILES[$field]['name']);

        for ($i = 0; $i < $count; $i++) {
            $file = [
                'name' => $_FILES[$field]['name'][$i],
                'type' => $_FILES[$field]['type'][$i],
                'tmp_name' => $_FILES[$field]['tmp_name'][$i],
                'error' => $_FILES[$field]['error'][$i],
                'size' => $_FILES[$field]['size'][$i],
            ];

            $upload = new self('', $config);
            $upload->file = $file;

            $files[] = $upload;
        }

        return $files;
    }
}

class Image extends Upload
{
    protected $width = 0;
    protected $height = 0;
    protected $imageInfo = [];

    public function __construct(string $field = '', array $config = [])
    {
        parent::__construct($field, $config);
        $this->fetchImageInfo();
    }

    protected function fetchImageInfo(): void
    {
        if (empty($this->file) || $this->file['error'] !== UPLOAD_ERR_OK) {
            return;
        }

        $info = @getimagesize($this->file['tmp_name']);
        if ($info) {
            $this->width = $info[0];
            $this->height = $info[1];
            $this->imageInfo = $info;
        }
    }

    public function check(): bool
    {
        if (!parent::check()) {
            return false;
        }

        if (!$this->isImage()) {
            $this->error = 'File is not an image';
            return false;
        }

        return true;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getImageInfo(): array
    {
        return $this->imageInfo;
    }
}
