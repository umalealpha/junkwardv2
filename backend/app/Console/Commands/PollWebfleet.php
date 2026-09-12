<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\Trip;
use AlphaDirect\Services\WebfleetClient;
use Illuminate\Console\Command;

class PollWebfleet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webfleet:poll {objectno?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll recent trips from Webfleet';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $objectno = $this->argument('objectno') ?? config('webfleet.objectno');

        if (!$objectno) {
            $this->error('No objectno specified. Please provide objectno as argument or set WEBFLEET_OBJECTNO in .env');
            return Command::FAILURE;
        }

        $wf = new WebfleetClient();

        $this->info("Polling trips for object: {$objectno}");

        $to   = now()->toISOString();
        $from = now()->subDays(20)->toISOString();

        try {
            $data = $wf->showTripReport($objectno, $from, $to);

            if (empty($data) || !is_array($data)) {
                $this->info('No trips found in the specified time range.');
                return Command::SUCCESS;
            }

            $count = 0;
            foreach ($data as $row) {
                // Generate unique ext_trip_id from row data
                $ext_trip_id = $row['tripid'] ?? md5(json_encode($row));

                Trip::updateOrCreate(
                    ['ext_trip_id' => $ext_trip_id],
                    [
                        // Basic trip info
                        'objectno'     => $row['objectno'] ?? $objectno,
                        'objectname'   => $row['objectname'] ?? null,
                        'objectuid'    => $row['objectuid'] ?? null,
                        'tripmode'     => $row['tripmode'] ?? null,
                        
                        // Time data - handle ISO format from payload
                        'start_time'   => isset($row['start_time']) ? date('Y-m-d H:i:s', strtotime($row['start_time'])) : (isset($row['startdatetime']) ? date('Y-m-d H:i:s', strtotime($row['startdatetime'])) : null),
                        'end_time'     => isset($row['end_time']) ? date('Y-m-d H:i:s', strtotime($row['end_time'])) : (isset($row['enddatetime']) ? date('Y-m-d H:i:s', strtotime($row['enddatetime'])) : null),
                        'duration_s'   => $row['duration'] ?? $row['duration_s'] ?? null,
                        'idle_time'    => $row['idle_time'] ?? null,
                        
                        // Location coordinates (microdegrees)
                        'start_lat'    => $row['start_latitude'] ?? $row['startlatitude_mdeg'] ?? null,
                        'start_lon'    => $row['start_longitude'] ?? $row['startlongitude_mdeg'] ?? null,
                        'end_lat'      => $row['end_latitude'] ?? $row['endlatitude_mdeg'] ?? null,
                        'end_lon'      => $row['end_longitude'] ?? $row['endlongitude_mdeg'] ?? null,
                        'start_postext' => $row['start_postext'] ?? null,
                        'end_postext'  => $row['end_postext'] ?? null,
                        
                        // Distance and speed
                        'start_odometer' => $row['start_odometer'] ?? null,
                        'end_odometer'   => $row['end_odometer'] ?? null,
                        'distance_m'     => $row['distance'] ?? $row['distance_m'] ?? null,
                        'avg_speed'      => $row['avg_speed'] ?? null,
                        'max_speed'      => $row['max_speed'] ?? null,
                        
                        // Driver info
                        'driverno'     => $row['driverno'] ?? null,
                        'drivername'   => $row['drivername'] ?? $row['driver_name'] ?? null,
                        'driveruid'    => $row['driveruid'] ?? null,
                        
                        // Vehicle info
                        'fueltype'     => $row['fueltype'] ?? null,
                        
                        // Performance indicators
                        'optidrive_indicator'        => $row['optidrive_indicator'] ?? null,
                        'speeding_indicator'         => $row['speeding_indicator'] ?? null,
                        'drivingevents_indicator'    => $row['drivingevents_indicator'] ?? null,
                        'idling_indicator'           => $row['idling_indicator'] ?? null,
                        'constant_speed_indicator'   => $row['constant_speed_indicator'] ?? null,
                        'high_revving_indicator'     => $row['high_revving_indicator'] ?? null,
                        
                        // Store complete raw payload
                        'raw_payload'  => $row,
                    ]
                );
                $count++;
            }

            $this->info("Successfully processed {$count} trip(s).");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error polling Webfleet: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}

