<?php
namespace AlphaDirect\Library;
use Illuminate\Support\Facades\Facade;  


class Helper extends Facade
{
	static function bytesToHuman($bytes)
	{
		$units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];

		for ($i = 0; $bytes > 1024; $i++) {
			$bytes /= 1024;
		}

		return round($bytes, 2) . ' ' . $units[$i];
	}
	
	public static function getCloudFrontURL($url){
		if(env('FILESYSTEM_DRIVER','s3')=='s3'){
			return env('AWS_CLOUDFRONT')."/".$url;
		}else{
			return \Storage::path($url);
		}
    }


}