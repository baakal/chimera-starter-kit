<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\MapRequest;
use App\Jobs\ImportShapefileJob;
use App\Models\Area;
use App\Services\AreaTree;
use App\Services\ShapefileImporter;
use App\Services\Traits\Geospatial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MapController extends Controller
{
    use Geospatial;

    public function index()
    {
        $records = Area::orderBy('level')->orderBy('path')->paginate(config('chimera.records_per_page'));
        $levelCounts = Area::select('level', DB::raw('count(*) AS count'))->groupBy('level')->get();
        $hierarchies = (new AreaTree())->hierarchies;
        $summary = $levelCounts->map(function ($item) use ($hierarchies) {
            return $item->count . ' ' . str($hierarchies[$item->level] ?? 'unknown')->plural($item->count);
        })->join(', ', ' and ');
        return view('developer.map.index', compact('records', 'summary'));
    }

    public function create()
    {
        $levels = config('chimera.area.hierarchies', []);
        return view('developer.map.create', compact('levels'));
    }

    private function validateShapefile(array $features)
    {
        // Check for empty shapefiles?
        if (empty($features)) {
            throw ValidationException::withMessages([
                'shapefile' => ['The shapefile does not contain any valid features.'],
            ]);
        }

        // Check that shapefile has 'name' and 'code' columns in the attribute table
        $firstFeatureAttributes = $features[0]['attribs'];
        if (! (array_key_exists('name', $firstFeatureAttributes) && array_key_exists('code', $firstFeatureAttributes))) {
            throw ValidationException::withMessages([
                'shapefile' => ["The shapefile needs to have 'name' and 'code' among its attributes"],
            ]);
        }

        // Check that all areas have valid value for 'code'
        $featuresWithInvalidCode = array_filter($features, function ($feature) {
            $codeValidator = Validator::make(
                $feature['attribs'],
                ['code' => ['required', 'max:255', 'regex:/[A-Za-z0-9_]+/i', 'unique:areas,code']]
            );
            if ($codeValidator->fails()) {
                logger('Shapefile validation error', ['Error' => $codeValidator->errors()->all()]);
            }
            return $codeValidator->fails();
        });
        if (! empty($featuresWithInvalidCode)) {
            throw ValidationException::withMessages([
                'shapefile' => [count($featuresWithInvalidCode) . " area(s) with invalid value for 'code' attribute found."],
            ]);
        }
    }

    public function store(MapRequest $request)
    {
        $level = $request->integer('level', null);
        $files = $request->file('shapefile');
        $filename = Str::random(40);
        foreach ($files as $file) {
            $filenameWithExt = collect([$filename, $file->getClientOriginalExtension()])->join('.');
            $file->storeAs('/shapefiles', $filenameWithExt, 'imports');
        }
        $shpFile = collect([$filename, 'shp'])->join('.');
        $importer = new ShapefileImporter();
        $features = $importer->import(Storage::disk('imports')->path('shapefiles/' . $shpFile));

        $this->validateShapefile($features);

        ImportShapefileJob::dispatch($features, $level, auth()->user());

        return redirect()->route('developer.area.index')
            ->withMessage("Importing is in progress. You will be notified when it is complete.");
    }

    public function edit(Area $area)
    {
        return view('developer.map.edit', compact('area'));
    }

    public function update(Area $area, Request $request)
    {
        $area->update($request->only(['name', 'code']));
        return redirect()->route('developer.area.index')
            ->withMessage("The area has been updated");
    }

    public function destroy(Area $area)
    {
        $area->delete();
        return redirect()->route('developer.area.index')
            ->withMessage("The area has been deleted");
    }
}