<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Jobs\readCSV;
use DB;
use Log;

class CSVController extends Controller
{
    /**
     *  reads  csv file
     *  */
    public function getCSV()
    {
        try {
            $path = public_path() . '/5000 Sales Records.csv';
            $csvfile = fopen($path, 'r'); //get the file

            if ($csvfile) {
                /*fgetcsv use read the csv line by line to preseve memory space so it does not become slow,only one row at a time will be loaded to memory.
                Then, you can process it, save to the database, and overwrite it with the next one.*/

                while ($line = fgetcsv($csvfile)) {
                    // Process this line
                    Log::info($line);
                    // return response()->json($line);
                }
            }
        } catch (\Exception $ex) {
            //throw $th;
            return response($ex->getMessage());
        }

    }

    /**
     *  runs a queed Job to read te csv file
     */
    public function readCSVQueueJOB()
    {
        $readcsvjob = new readCSV();
        dispatch($readcsvjob);
    }

    /**
     * Import CSV file into Database using LOAD DATA LOCAL INFILE function
     *
     * NOTE: PDO settings must have attribute PDO::MYSQL_ATTR_LOCAL_INFILE => true
     *
     * @param $file_path
     * @return mixed Will return number of lines imported by the query
     */
    private function importFileContents($file_path)
    {
        $query = sprintf("LOAD DATA LOCAL INFILE '%s' INTO TABLE bitrix_agents
            FIELDS TERMINATED BY ',' 
            LINES TERMINATED BY '\n'
            IGNORE 1 LINES", addslashes($file_path));

        return DB::connection()->getpdo()->exec($query);
    }

    public function saveCSVToDB()
    { 
        try {
            //code... 
            $start_time = microtime(true);
            // Import our csv file
            $path = public_path() . '/imports/bitrixAgents.csv';
    
            //Load the file into a string
            $string = @file_get_contents($path);
    
            if (!$string) {
                return $path;
            }
    
            //Convert all line-endings using regular expression
            $fixed_string = preg_replace('~\r\n?~', "\n", $string);
    
            file_put_contents($path, $fixed_string);
    
            $result = $this->importFileContents($path);
            $end_time = microtime(true);
    
            $duration = number_format($start_time - $end_time, 3);
    
            return response()->json(['number of lines to db' => $result, 'duration' => $duration]);
        } catch (\Exception $ex) {
            //throw $th; 
            return response()->json($ex->getMessage());
        }
 

    }
    
    

}
