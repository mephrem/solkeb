<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Support\Collection;

/**
 * Turns a CSV file, text pasted from Excel, or a number range into a checked list of rooms.
 *
 * Columns: number, floor, type, price, notes (header row optional; floor and price may be blank).
 * Every row comes back with a status: new, update, skip or error, plus a message.
 */
class RoomImport
{
    public const MAX_ROWS = 500;

    private const ALIASES = [
        'number' => ['number', 'room', 'room number', 'room no', 'room no.', 'no', 'no.', '#'],
        'floor' => ['floor', 'level'],
        'type' => ['type', 'room type', 'category'],
        'price' => ['price', 'rate', 'price per night', 'rate per night', 'own price', 'etb'],
        'notes' => ['notes', 'note', 'description', 'remarks'],
    ];

    /** @var Collection<int, RoomType> keyed by lowercase name */
    private $types;

    public function __construct(private bool $updateExisting = false)
    {
        $this->types = RoomType::all()->keyBy(fn ($t) => mb_strtolower(trim($t->name)));
    }

    /** Rows from CSV text or text pasted from Excel. */
    public function fromText(string $text): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text); // Excel's UTF-8 marker
        $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', $text), fn ($l) => trim($l, " \t,;") !== ''));
        if (! $lines) {
            return [];
        }

        $delimiter = $this->delimiter($lines[0]);
        $cells = array_map(fn ($l) => array_map('trim', str_getcsv($l, $delimiter, '"', '')), $lines);

        // Header row? Map columns by name, otherwise use the standard order.
        $map = $this->headerMap($cells[0]);
        if ($map) {
            array_shift($cells);
        } else {
            $map = ['number' => 0, 'floor' => 1, 'type' => 2, 'price' => 3, 'notes' => 4];
        }

        $raw = [];
        foreach (array_slice($cells, 0, self::MAX_ROWS) as $i => $c) {
            $raw[] = [
                'line' => $i + 1,
                'number' => $c[$map['number']] ?? '',
                'floor' => isset($map['floor']) ? ($c[$map['floor']] ?? '') : '',
                'type' => isset($map['type']) ? ($c[$map['type']] ?? '') : '',
                'price' => isset($map['price']) ? ($c[$map['price']] ?? '') : '',
                'notes' => isset($map['notes']) ? ($c[$map['notes']] ?? '') : '',
            ];
        }

        $rows = $this->check($raw);
        if (count($cells) > self::MAX_ROWS) {
            $rows[] = ['line' => self::MAX_ROWS + 1, 'number' => '…', 'floor' => null, 'type' => '', 'type_id' => null, 'price' => null, 'notes' => '',
                'status' => 'error', 'message' => 'Only the first '.self::MAX_ROWS.' rows are read. Import the rest in a second file.'];
        }

        return $rows;
    }

    /** Rows for a range such as 401 to 410. */
    public function fromRange(int $from, int $to, ?int $floor, string $type, ?string $price, string $prefix = ''): array
    {
        $raw = [];
        foreach (range($from, $to) as $i => $n) {
            $raw[] = ['line' => $i + 1, 'number' => $prefix.$n, 'floor' => $floor === null ? '' : (string) $floor, 'type' => $type, 'price' => (string) $price, 'notes' => ''];
        }

        return $this->check($raw);
    }

    /** Re-check rows from an earlier preview against the rooms as they are now. */
    public function recheck(array $previewRows): array
    {
        $raw = [];
        foreach ($previewRows as $r) {
            $raw[] = ['line' => $r['line'], 'number' => $r['number'], 'floor' => (string) $r['floor'], 'type' => $r['type'],
                'price' => $r['price_set'] ? (string) ($r['price'] ?? $this->types->get(mb_strtolower($r['type']))?->base_rate) : '', 'notes' => $r['notes']];
        }

        return $this->check($raw);
    }

    /** Validate every row and decide what will happen to it. */
    private function check(array $raw): array
    {
        $existing = Room::with('roomType')->get()->keyBy(fn ($r) => mb_strtolower($r->number));
        $seen = [];
        $rows = [];

        foreach ($raw as $r) {
            $number = trim((string) $r['number']);
            $row = ['line' => $r['line'], 'number' => $number, 'floor' => null, 'type' => trim((string) $r['type']), 'type_id' => null,
                'price' => null, 'price_set' => false, 'notes' => mb_substr(trim((string) $r['notes']), 0, 500), 'status' => 'new', 'message' => 'New room'];
            $errors = [];

            if ($number === '') {
                $errors[] = 'Room number is missing';
            } elseif (mb_strlen($number) > 10) {
                $errors[] = 'Room number is longer than 10 characters';
            } elseif (isset($seen[mb_strtolower($number)])) {
                $errors[] = 'Room '.$number.' appears twice in this list (row '.$seen[mb_strtolower($number)].')';
            }
            if ($number !== '') {
                $seen[mb_strtolower($number)] ??= $r['line'];
            }

            // Floor: given, or worked out from the number (101 → 1, 1203 → 12)
            $floor = trim((string) $r['floor']);
            if ($floor !== '') {
                if (! ctype_digit($floor) || (int) $floor > 50) {
                    $errors[] = 'Floor "'.$floor.'" is not a number from 0 to 50';
                } else {
                    $row['floor'] = (int) $floor;
                }
            } elseif (ctype_digit($number) && (int) $number >= 100) {
                $row['floor'] = intdiv((int) $number, 100);
            }

            // Type must match one of the hotel's room types (not case sensitive)
            $type = $this->types->get(mb_strtolower($row['type']));
            $old = $existing->get(mb_strtolower($number));
            if ($row['type'] === '' && ! $old) {
                $errors[] = 'Room type is missing';
            } elseif ($row['type'] !== '' && ! $type) {
                $errors[] = 'Unknown room type "'.$row['type'].'". Use one of: '.$this->types->pluck('name')->join(', ');
            } elseif ($type) {
                $row['type'] = $type->name;
                $row['type_id'] = $type->id;
            }

            // Own price: only kept when it differs from the type's rate
            $price = str_replace([',', 'ETB', 'etb', ' '], '', (string) $r['price']);
            if ($price !== '') {
                $row['price_set'] = true;
                if (! is_numeric($price) || (float) $price < 0) {
                    $errors[] = 'Price "'.$r['price'].'" is not a number';
                } elseif (! $type || abs((float) $price - (float) $type->base_rate) > 0.009) {
                    $row['price'] = round((float) $price, 2);
                }
            }

            if ($errors) {
                $row['status'] = 'error';
                $row['message'] = implode('. ', $errors).'.';
            } elseif ($old) {
                if ($this->updateExisting) {
                    $row['status'] = 'update';
                    $row['message'] = 'Already exists: will be updated';
                    $row['floor'] ??= $old->floor;
                    $row['type_id'] ??= $old->room_type_id;
                    $row['type'] = $row['type'] ?: $old->roomType->name;
                } else {
                    $row['status'] = 'skip';
                    $row['message'] = 'Already exists: left as it is';
                }
            } elseif ($row['floor'] === null) {
                $row['status'] = 'error';
                $row['message'] = 'Floor is missing.';
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /** Save the new and update rows. Returns [created, updated]. */
    public static function save(array $rows): array
    {
        $created = $updated = 0;
        foreach ($rows as $r) {
            if ($r['status'] === 'new') {
                Room::create(['number' => $r['number'], 'floor' => $r['floor'], 'room_type_id' => $r['type_id'],
                    'rate_override' => $r['price'], 'notes' => $r['notes'] ?: null]);
                $created++;
            } elseif ($r['status'] === 'update') {
                $room = Room::whereRaw('lower(number) = ?', [mb_strtolower($r['number'])])->first();
                if ($room) {
                    // Blank price or notes in the file keep what the room already has
                    $room->update(['floor' => $r['floor'], 'room_type_id' => $r['type_id'],
                        'rate_override' => $r['price_set'] ? $r['price'] : $room->rate_override,
                        'notes' => $r['notes'] !== '' ? $r['notes'] : $room->notes]);
                    $updated++;
                }
            }
        }

        return [$created, $updated];
    }

    private function delimiter(string $line): string
    {
        $counts = ["\t" => substr_count($line, "\t"), ';' => substr_count($line, ';'), ',' => substr_count($line, ',')];
        arsort($counts);

        return reset($counts) > 0 ? array_key_first($counts) : ',';
    }

    private function headerMap(array $first): ?array
    {
        $map = [];
        foreach ($first as $i => $cell) {
            $name = mb_strtolower(trim($cell));
            foreach (self::ALIASES as $field => $aliases) {
                if (in_array($name, $aliases, true) && ! isset($map[$field])) {
                    $map[$field] = $i;
                }
            }
        }

        return isset($map['number']) ? $map : null;
    }
}
