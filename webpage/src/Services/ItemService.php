<?php
declare(strict_types=1);

namespace Moro\Services;

use Moro\Repositories\ItemsRepository;
use InvalidArgumentException;

final class ItemService
{
    public function __construct(private ItemsRepository $items) {}

    public function addItemToHome(int $homeId, array $input): int
    {
        $name     = trim((string)($input['name'] ?? ''));
        $category = trim((string)($input['category'] ?? ''));

        $brand   = trim((string)($input['brand'] ?? ''));
        $model   = trim((string)($input['model'] ?? ''));
        $serial  = trim((string)($input['serial_number'] ?? ''));
        $notes   = trim((string)($input['notes'] ?? ''));

        $purchaseDate = $input['purchase_date'] ?? null;
        $purchaseDate = is_string($purchaseDate) ? trim($purchaseDate) : null;
        if ($purchaseDate === '') $purchaseDate = null;

        $cost = $input['cost'] ?? null;
        if ($cost === '' || $cost === null) $cost = null;
        else $cost = (float)$cost;

        if ($name === '' || $category === '') {
            throw new InvalidArgumentException('item_required');
        }

        if ($purchaseDate !== null && !DueDateCalculator::isValidYmd($purchaseDate)) {
            throw new InvalidArgumentException('item_bad_date');
        }

        return $this->items->insertItem($homeId, [
            'name'          => $name,
            'category'      => $category,
            'brand'         => ($brand !== '' ? $brand : null),
            'model'         => ($model !== '' ? $model : null),
            'serial_number' => ($serial !== '' ? $serial : null),
            'purchase_date' => $purchaseDate,
            'cost'          => $cost,
            'notes'         => ($notes !== '' ? $notes : null),
        ]);
    }

    /**
     * Updates an item in a home.
     * Returns 'updated' or 'no_change' for UI messaging.
     */
    public function updateItemInHome(int $homeId, int $itemId, array $input): string
    {
        if ($itemId <= 0) throw new InvalidArgumentException('item_required');

        $name     = trim((string)($input['name'] ?? ''));
        $category = trim((string)($input['category'] ?? ''));

        $brand   = trim((string)($input['brand'] ?? ''));
        $model   = trim((string)($input['model'] ?? ''));
        $serial  = trim((string)($input['serial_number'] ?? ''));
        $notes   = trim((string)($input['notes'] ?? ''));

        $purchaseDate = $input['purchase_date'] ?? null;
        $purchaseDate = is_string($purchaseDate) ? trim($purchaseDate) : null;
        if ($purchaseDate === '') $purchaseDate = null;

        $cost = $input['cost'] ?? null;
        if ($cost === '' || $cost === null) $cost = null;
        else $cost = (float)$cost;

        if ($name === '' || $category === '') throw new InvalidArgumentException('item_required');

        if ($purchaseDate !== null && !DueDateCalculator::isValidYmd($purchaseDate)) {
            throw new InvalidArgumentException('item_bad_date');
        }

        // IMPORTANT: updateItemInHome should return 'updated' | 'no_change' | 'not_found'
        $status = $this->items->updateItemInHome($homeId, $itemId, [
            'name'          => $name,
            'category'      => $category,
            'brand'         => ($brand !== '' ? $brand : null),
            'model'         => ($model !== '' ? $model : null),
            'serial_number' => ($serial !== '' ? $serial : null),
            'purchase_date' => $purchaseDate,
            'cost'          => $cost,
            'notes'         => ($notes !== '' ? $notes : null),
        ]);

        if ($status === 'not_found') {
            throw new InvalidArgumentException('unauthorized');
        }

        return $status; // 'updated' | 'no_change'
    }

    public function deleteItemInHome(int $homeId, int $itemId): void
    {
        if ($itemId <= 0) throw new InvalidArgumentException('delete_invalid');

        $ok = $this->items->deleteItemInHome($homeId, $itemId);
        if (!$ok) throw new InvalidArgumentException('unauthorized');
    }
}
        