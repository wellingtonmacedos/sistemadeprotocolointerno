<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Armazenamento privado e seguro de anexos, com validação de MIME/extensão e hash.
 */
class AttachmentService
{
    /** @var array<int,string> */
    public const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    /** @var array<int,string> */
    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    /**
     * @return array{original_name:string,file_path:string,mime_type:string,size:int,hash_sha256:string}
     */
    public function store(UploadedFile $file, string $directory): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('Extensão de arquivo não permitida.');
        }

        $mime = $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException('Tipo de arquivo não permitido.');
        }

        $maxKb = (int) config('protocolo.max_upload_kb', 10240);
        if ($file->getSize() > $maxKb * 1024) {
            throw new \InvalidArgumentException('Arquivo excede o limite de '.($maxKb / 1024).' MB.');
        }

        $path = $file->store($directory, 'local');
        $fullPath = Storage::disk('local')->path($path);

        return [
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'hash_sha256' => hash_file('sha256', $fullPath),
        ];
    }

    public function delete(?string $path): void
    {
        if ($path && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }
}
