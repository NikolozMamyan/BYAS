<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

class AvatarManager
{
    private AsciiSlugger $slugger;

    public function __construct(
        private readonly string $avatarUploadDir,
    ) {
        $this->slugger = new AsciiSlugger();
    }

    public function upload(User $user, UploadedFile $file): string
    {
        $this->assertValidAvatar($file);

        if (!is_dir($this->avatarUploadDir)) {
            mkdir($concurrentDirectory = $this->avatarUploadDir, 0775, true);
            if (!is_dir($concurrentDirectory)) {
                throw new \RuntimeException('Avatar upload directory could not be created.');
            }
        }

        $baseName = strtolower((string) $this->slugger->slug($user->getDisplayName() ?? 'fan'));
        $baseName = $baseName !== '' ? $baseName : 'fan';
        $filename = sprintf('%s-%s.%s', $baseName, bin2hex(random_bytes(6)), $file->guessExtension() ?: 'bin');

        $file->move($this->avatarUploadDir, $filename);

        $this->deleteCurrentAvatarFile($user);

        return '/uploads/avatars/' . $filename;
    }

    public function deleteCurrentAvatarFile(User $user): void
    {
        $avatarUrl = $user->getAvatarUrl();

        if (!is_string($avatarUrl) || !str_starts_with($avatarUrl, '/uploads/avatars/')) {
            return;
        }

        $filePath = $this->avatarUploadDir . DIRECTORY_SEPARATOR . basename($avatarUrl);

        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }

    private function assertValidAvatar(UploadedFile $file): void
    {
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $mimeType = $file->getMimeType();

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new \RuntimeException('Avatar format not supported. Use JPG, PNG, WEBP or GIF.');
        }

        if ($file->getSize() !== null && $file->getSize() > 5 * 1024 * 1024) {
            throw new \RuntimeException('Avatar file is too large. Maximum size is 5 MB.');
        }
    }
}
