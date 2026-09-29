<?php

namespace Baracod\Larastarterkit\Core\Services;

use Illuminate\Validation\ValidationException;

class SignatureImageService
{
    public function decode(string $value): string
    {
        if (strlen($value) > 2800000) {
            $this->invalid();
        }

        if (str_starts_with($value, 'data:')) {
            if (! preg_match('#\Adata:image/(?:png|jpeg);base64,#', $value, $matches)) {
                $this->invalid();
            }
            $value = substr($value, strlen($matches[0]));
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false || strlen($decoded) > 2097152) {
            $this->invalid();
        }

        $size = @getimagesizefromstring($decoded);
        if ($size === false || ! in_array($size[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)
            || $size[0] < 1 || $size[1] < 1 || $size[0] > 4096 || $size[1] > 4096
            || $size[0] * $size[1] > 4194304) {
            $this->invalid();
        }

        $image = @imagecreatefromstring($decoded);
        if ($image === false) {
            $this->invalid();
        }

        imagesavealpha($image, true);
        ob_start();
        try {
            if (! imagepng($image)) {
                $this->invalid();
            }

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['signature' => 'La signature doit être une image PNG ou JPEG valide de 2 Mo maximum (4096 pixels par côté, 4 mégapixels maximum).']);
    }
}
