<?php

namespace App\Support;

class DocumentFilename
{
    public static function make(string $title, string $extension): string
    {
        $extension = strtolower($extension);
        $name = trim(preg_replace('/[<>:"\/\\\\|?*\x00-\x1F\x7F]/u', ' ', $title));
        $name = preg_replace('/\s+/u', ' ', $name);
        if (str_ends_with(strtolower($name), '.'.$extension)) {
            $name = substr($name, 0, -strlen($extension) - 1);
        }
        $name = trim(mb_strcut($name, 0, 180, 'UTF-8'), " .\t\n\r\0\x0B");
        if ($name === '') {
            $name = 'Documento';
        }
        if (preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i', $name)) {
            $name = 'Documento '.$name;
        }

        return $name.'.'.$extension;
    }
}
