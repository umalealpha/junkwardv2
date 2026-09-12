<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\Trip;
use Illuminate\Http\Request;
use DataTables;

class TripController extends Controller
{
    /**
     * Display a listing of trips.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.trips.index');
    }

    /**
     * Get trips data for DataTables
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getData(Request $request)
    {
        if ($request->ajax()) {
            $trips = Trip::with('webfleetObject')
                ->select('trips.*')
                ->orderBy('start_time', 'desc');

            return DataTables::of($trips)
                ->addIndexColumn()
                ->addColumn('duration', function ($trip) {
                    $minutes = floor($trip->duration_s / 60);
                    $seconds = $trip->duration_s % 60;
                    return $minutes . 'm ' . $seconds . 's';
                })
                ->addColumn('distance', function ($trip) {
                    return number_format($trip->distance_m / 1000, 2) . ' km';
                })
                ->addColumn('idle_duration', function ($trip) {
                    if ($trip->idle_time) {
                        $minutes = floor($trip->idle_time / 60);
                        $seconds = $trip->idle_time % 60;
                        return $minutes . 'm ' . $seconds . 's';
                    }
                    return 'N/A';
                })
                ->addColumn('speed_info', function ($trip) {
                    return 'Avg: ' . ($trip->avg_speed ?? 0) . ' km/h<br>Max: ' . ($trip->max_speed ?? 0) . ' km/h';
                })
                ->addColumn('performance', function ($trip) {
                    $html = '';
                    if ($trip->optidrive_indicator) {
                        $html .= '<span class="badge badge-primary" title="OptiDrive">OD: ' . number_format($trip->optidrive_indicator, 2) . '</span> ';
                    }
                    if ($trip->speeding_indicator) {
                        $html .= '<span class="badge badge-warning" title="Speeding">SP: ' . number_format($trip->speeding_indicator, 2) . '</span> ';
                    }
                    if ($trip->idling_indicator) {
                        $html .= '<span class="badge badge-info" title="Idling">ID: ' . number_format($trip->idling_indicator, 2) . '</span>';
                    }
                    return $html ?: 'N/A';
                })
                ->addColumn('actions', function ($trip) {
                    return '<button class="btn btn-sm btn-info view-trip" data-id="' . $trip->id . '" title="View Details"><i class="fa fa-eye"></i></button>';
                })
                ->rawColumns(['speed_info', 'performance', 'actions'])
                ->make(true);
        }
    }

    /**
     * Display the specified trip details.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $trip = Trip::with('webfleetObject')->findOrFail($id);
        return response()->json($trip);
    }
}

