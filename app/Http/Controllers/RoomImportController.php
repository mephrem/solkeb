<?php

namespace App\Http\Controllers;

use App\Models\RoomType;
use App\Services\RoomImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Setup → Rooms → Import: add or update many rooms from a spreadsheet (preview first, then save).
 */
class RoomImportController extends Controller
{
    public function create()
    {
        return view('setup.room-import', ['types' => RoomType::orderBy('base_rate')->get(), 'rows' => null]);
    }

    /** Read the file, pasted text or range and show what would happen. Nothing is saved yet. */
    public function preview(Request $request)
    {
        $update = $request->boolean('update_existing');
        $importer = new RoomImport($update);

        if ($request->input('mode') === 'range') {
            $data = $request->validate([
                'from' => ['required', 'integer', 'min:0', 'max:99999'],
                'to' => ['required', 'integer', 'gte:from', 'max:99999'],
                'floor' => ['nullable', 'integer', 'min:0', 'max:50'],
                'type' => ['required', 'string'],
                'price' => ['nullable', 'numeric', 'min:0'],
                'prefix' => ['nullable', 'string', 'max:4'],
            ], ['to.gte' => '"To" must be the same as or bigger than "From".']);
            if ($data['to'] - $data['from'] >= 200) {
                return back()->withInput()->with('error', 'A range can add at most 200 rooms at a time.');
            }
            $rows = $importer->fromRange((int) $data['from'], (int) $data['to'], isset($data['floor']) ? (int) $data['floor'] : null,
                $data['type'], isset($data['price']) ? (string) $data['price'] : null, (string) ($data['prefix'] ?? ''));
            $source = 'Rooms '.($data['prefix'] ?? '').$data['from'].' to '.($data['prefix'] ?? '').$data['to'];
        } else {
            $request->validate([
                'file' => ['nullable', 'file', 'max:1024', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel,text/x-csv,text/tab-separated-values'],
                'pasted' => ['nullable', 'string', 'max:200000'],
            ], ['file.mimetypes' => 'Upload a CSV file. In Excel use File > Save As > "CSV UTF-8".', 'file.max' => 'The file is too big (max 1 MB).']);

            if ($request->hasFile('file')) {
                $text = file_get_contents($request->file('file')->getRealPath());
                $source = $request->file('file')->getClientOriginalName();
            } elseif (trim((string) $request->input('pasted')) !== '') {
                $text = (string) $request->input('pasted');
                $source = 'Pasted rows';
            } else {
                return back()->withInput()->with('error', 'Choose a CSV file or paste rows from Excel.');
            }
            if (! mb_check_encoding($text, 'UTF-8')) {
                $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252'); // older Excel "CSV" files
            }
            $rows = $importer->fromText($text);
            if (! $rows) {
                return back()->withInput()->with('error', 'No rooms were found in that file.');
            }
        }

        $request->session()->put('room_import', ['rows' => $rows, 'source' => $source, 'update' => $update]);

        return view('setup.room-import', [
            'types' => RoomType::orderBy('base_rate')->get(),
            'rows' => $rows,
            'source' => $source,
            'counts' => collect($rows)->countBy('status')->all(),
        ]);
    }

    public function store(Request $request)
    {
        $import = $request->session()->pull('room_import');
        if (! $import) {
            return redirect()->route('room-import.create')->with('error', 'The preview has expired. Please load the rooms again.');
        }

        // Check again against the rooms as they are now, in case someone added rooms since the preview
        $rows = (new RoomImport($import['update']))->recheck(collect($import['rows'])->whereIn('status', ['new', 'update'])->all());
        [$created, $updated] = DB::transaction(fn () => RoomImport::save($rows));

        if ($created + $updated === 0) {
            return redirect()->route('room-import.create')->with('error', 'Nothing was imported: every row was skipped or had an error.');
        }

        return redirect()->route('setup.rooms')->with('success', trim(
            ($created ? "{$created} room(s) added. " : '').($updated ? "{$updated} room(s) updated." : '')
        ));
    }

    public function template()
    {
        $types = RoomType::orderBy('base_rate')->pluck('name');
        $a = $types->get(0, 'Standard');
        $b = $types->get(1, $a);
        $csv = "\xEF\xBB\xBF"."number,floor,type,price,notes\r\n"
            ."401,4,{$a},,\r\n402,4,{$a},,Near the lift\r\n403,4,{$b},,\r\n404,4,{$b},2800,Balcony - own price\r\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="solkeb-rooms-template.csv"',
        ]);
    }
}
