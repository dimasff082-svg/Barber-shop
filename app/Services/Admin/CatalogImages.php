<?php

namespace App\Services\Admin;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class CatalogImages
{
    /**
     * @template T
     *
     * @param  callable(?string): T  $operation
     * @return T
     */
    public function save(Request $request, string $directory, callable $operation): mixed
    {
        $image = $request->file('image');
        $path = null;
        if ($image instanceof UploadedFile) {
            $stored = $image->store($directory, 'public');
            if ($stored === false) {
                throw ValidationException::withMessages(['image' => 'Foto belum dapat disimpan. Silakan coba kembali.']);
            }
            $path = $stored;
        }
        try {
            return DB::transaction(fn () => $operation($path), 5);
        } catch (Throwable $exception) {
            // Only the new upload from this failed operation is removed. Historic assets are retained.
            if ($path !== null) {
                Storage::disk('public')->delete($path);
            }
            if ($exception instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['queue' => 'Data unik sudah dipakai oleh perubahan lain. Muat ulang daftar dan periksa kembali data yang digunakan.']);
            }
            throw $exception;
        }
    }
}
