<?php
declare(strict_types=1);

final class ItemImageController
{
    public function show(string $name): void
    {
        ItemImage::send($name);
    }

    public function upload(): void
    {
        Auth::requireUser();
        $bucket = 'item-img:' . substr(hash('sha256', session_id()), 0, 16);
        if (RateLimit::tooMany($bucket, 60, 600)) {
            json_response(['ok' => false, 'error' => 'Zu viele Uploads. Bitte kurz warten.'], 429);
        }
        RateLimit::hit($bucket);
        try {
            if (empty($_FILES['image'])) {
                throw new RuntimeException('Keine Datei empfangen.');
            }
            $name = ItemImage::store($_FILES['image']);
        } catch (RuntimeException $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        json_response(['ok' => true, 'name' => $name, 'url' => ItemImage::url($name)]);
    }
}
