<?php

namespace AlphaDirect\Http\Controllers;

use Auth;
use Illuminate\Http\Request;
use Log;
use Infobip\InfobipClient;
use Infobip\Resources\WhatsApp\WhatsAppTextMessageResource;
use Infobip\Resources\WhatsApp\Models\TextContent;
use AlphaDirect\Models\whatsAppModel;
use Yajra\DataTables\DataTables;


class WhatsAppController extends Controller
{
    public $lastId;

    public function __construct()
    {
        //$this->middleware('auth');
        $lastId=0;
    }

    public function sendMessageTest(Request $request, InfobipClient $infobipClient)
    {
        $resource = new WhatsAppTextMessageResource(
            //$request->input('from'),
            //$request->input('to'),
            "2673702700",
            "2674613975",
            new TextContent("Hi satyajeet")
        );
        
        $response = $infobipClient
            ->whatsApp()
            ->sendWhatsAppTextMessage($resource);
        
        return $response;
    }
    public function whatsapp(Request $request)
    {
        return view("admin.whatsApp.tamplate");
    }
    public function sendMessage( $data)
    {
        try{
          $url =env('WHATSAPP_URL');

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $headers = array(
           "Authorization: Bearer EAAT14FGd9Y0BOZB2Q9qc2UtTp0affGpWL92xtsGKdYaHsubhcbZCip1CvvWhxVAtaIWZAkx5sS8m34KqjBDlhnMo7Ax0Pyj0uYYO5xZAOpTO7aLhVKwl4F6svgpoLeRyGaYTLlIfqBProVg9BzHPgOeN2TL2ZCGPQoJDLCgYHM93glcKifH5JVaGQXfNksPlP9z9zzZBBhcqasN1hV",
           "Content-Type: application/json",
        );

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $dataSet='{
            "messaging_product": "whatsapp",
            "recipient_type": "individual",
            "to": "'.$data['mobileNumber'].'",
           "type": "'.$data['type'].'",
            "template": {
                "name": "'.$data['subType'].'",
                "language":{
                "code":"en_US"
                },
                 "components": [
                       {';
              if($data['subType'] == "policy_create_otp") {

                        $dataSet.='
                          "type": "button",
                          "sub_type": "url",
                          "index": 0,
                          "parameters": [
                            {
                              "type": "text",
                              "text": "'.$data['policyOtp'].'"
                            }
                          ]
                        ';

              }else{

                 $dataSet.='"type": "header",';
                if ($data['subType']!='create_policy_document' && $data['subType']!='send_quote_msg_agent') {
                    $dataSet.= '"parameters": [
                        {
                          "type": "image",
                          "image": {
                            "link": "https://graphite.alphadirect.co.bw/Logo.png"
                          }
                        }
                      ]';
                }else{
                    $dataSet.= '"parameters": [
                        {
                          "type": "document",
                          "document": {
                            "link": "'.$data['file'].'",
                            "filename":"'.$data['fileName'].'",
                          }
                        }
                      ]';
                }
              }
              $dataSet.=  '},{
                "type": "body",';
                switch ($data['subType']) {
                    case "policy_create_otp":
                        $dataSet.= ' "parameters": [
                            {
                              "type": "text",
                              "text": "'.$data['policyOtp'].'"
                            } ]';
                      break;
                      case "create_policy":
                        $dataSet.= ' "parameters": [
                            {
                              "type": "text",
                              "text": "'.$data['firstName'].'"
                            },
                            {
                              "type": "text",
                              "text": "'.$data['lastName'].'"
                            },
                            {
                              "type": "text",
                              "text":"'.$data['policyNumber'].'"
                            } ]';
                      break;  
                      case "policy_cancelled":
                        $dataSet.= ' "parameters": [
                            {
                              "type": "text",
                              "text":"'.$data['policyNumber'].'"
                            } ]';
                      break; 
                      case "cover_note_customer_wrong":
                        $dataSet.= ' "parameters": [
                         ]';
                       break; 
                       case "cover_note49_customer_wrong":
                        $dataSet.= ' "parameters": [
                         ]';
                       break; 
                      case "reject_policy_document":
                        $dataSet.= ' "parameters": [
                            {
                              "type": "text",
                              "text": "'.$data['firstName'].'"
                            },
                            {
                              "type": "text",
                              "text": "'.$data['lastName'].'"
                            },
                            {
                              "type": "text",
                              "text":"'.$data['policyNumber'].'"
                            },{
                              "type": "text",
                              "text":"'.$data['docList'].'"
                            } ]';
                      break;  
                      case "motor_policy_kyc_cron":
                        $dataSet.= ' "parameters": [
                            {
                              "type": "text",
                              "text": "'.$data['firstName'].'"
                            },
                            {
                              "type": "text",
                              "text": "'.$data['lastName'].'"
                            },
                            {
                              "type": "text",
                              "text":"'.$data['policyNumber'].'"
                            } ]';
                      break;  
                      case "instant_policy_kyc_cron":
                        $dataSet.= ' "parameters": [
                            {
                              "type": "text",
                              "text": "'.$data['firstName'].'"
                            },
                            {
                              "type": "text",
                              "text": "'.$data['lastName'].'"
                            },
                            {
                              "type": "text",
                              "text":"'.$data['policyNumber'].'"
                            } ]';
                      break;  
                      case "motor_policy_except_kyc_cron":
                        $dataSet.= ' "parameters": [
                            {
                              "type": "text",
                              "text": "'.$data['firstName'].'"
                            },
                            {
                              "type": "text",
                              "text": "'.$data['lastName'].'"
                            },
                            {
                              "type": "text",
                              "text":"'.$data['policyNumber'].'"
                            } ]';
                      break;  
                      case "vehicle_preinspection_pending_cron":
                      $dataSet.= ' "parameters": [
                          {
                            "type": "text",
                            "text": "'.$data['firstName'].'"
                          },
                          {
                            "type": "text",
                            "text": "'.$data['lastName'].'"
                          },
                          {
                            "type": "text",
                            "text":"'.$data['policyNumber'].'"
                          },{
                            "type": "text",
                            "text":"'.$data['make'].'"
                          },{
                            "type": "text",
                            "text":"'.$data['model'].'"
                          } ]';
                    break;  
                    case "device_preinspection_pending_cron":
                      $dataSet.= ' "parameters": [
                          {
                            "type": "text",
                            "text": "'.$data['firstName'].'"
                          },
                          {
                            "type": "text",
                            "text": "'.$data['lastName'].'"
                          },
                          {
                            "type": "text",
                            "text":"'.$data['policyNumber'].'"
                          },{
                            "type": "text",
                            "text":"'.$data['make'].'"
                          },{
                            "type": "text",
                            "text":"'.$data['model'].'"
                          } ]';
                    break;  
                      case "preinspection_reject":
                        $dataSet.= ' "parameters": [
                            {
                              "type": "text",
                              "text": "'.$data['customer_name'].'"
                            },
                            {
                              "type": "text",
                              "text":"'.$data['policyNumber'].'"
                            } ]';
                      break;  
                      case "vehicle_preinspection_reject":
                        
                      $dataSet.= ' "parameters": [
                          {
                            "type": "text",
                            "text": "'.$data['customer_name'].'"
                          },
                          {
                            "type": "text",
                            "text":"'.$data['policyNumber'].'"
                          } ]';
                    break; 
                      case "create_policy_document":
                        $dataSet.= ' "parameters": [
                                                    ]';
                      break;  
                      case "send_quote_msg_agent":
                        $dataSet.= ' "parameters": [
                          {
                            "type": "text",
                            "text":"'.$data['quoteCode'].'"
                          }
                            ]';
                      break;  
                    case "hello_world":
                        $dataSet.= ' "parameters": [
                            {
                              "type": "text",
                              "text": "ok"
                            } ]';
                      break;
                    case "rekyc_verification":
                        $dataSet.= ' "parameters": [
                            {
                              "type": "text",
                              "text": "'.$data['firstName'].'"
                            },
                            {
                              "type": "text",
                              "text": "'.$data['accessUrl'].'"
                            },
                            {
                              "type": "text",
                              "text": "'.$data['otpCode'].'"
                            },
                            {
                              "type": "text",
                              "text": "'.$data['expiryDays'].'"
                            } ]';
                      break;
                    case "rekyc_verification_text":
                        // For simple text message without template - return early  //
                        $dataSet = '{
                            "messaging_product": "whatsapp",
                            "recipient_type": "individual",
                            "to": "'.$data['mobileNumber'].'",           
                            "type": "text",
                            "text": {
                                "body": "'.$data['message'].'"
                            }
                        }';
                        break;
                    default:
                      dd('test');
                  }
                $dataSet.='}
                 ]
            }
            
        }';

         curl_setopt($curl, CURLOPT_POSTFIELDS, $dataSet);
        //fodr debug only!
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
      
        $resp = curl_exec($curl);
        $whatsAppModel= whatsAppModel::create([
            'url'    =>  $url, 
            'method'  =>  'POST', 
            'input' =>  json_encode($dataSet),
            'output' =>json_encode($resp),
            'template_type' =>  $data['subType'],
            'policyNumber' =>  $data['policyNumber'],
            'customer_id' =>  $data['customer_id'],
            'WA_cellphone'=>$data['mobileNumber'],
            'start_time' => microtime(true),
            'end_time' =>  microtime(true),
            'created_at'=> \Carbon\Carbon::now(),
        
        ]);

        $this->lastId=$whatsAppModel->id;
        Log::info(json_encode( $this->lastId));

        curl_close($curl);

    }catch(\Exception $e){
        return response()->json(['Status' => 'Failed','Description'=>$e->getMessage()], 401);
    }
      
    }

    public function sendMetaData($data)
    {
      $payload = [
        "messaging_product" => "whatsapp",
        "to" => "267" . preg_replace('/\D+/', '', $data['mobileNumber']),
        "type" => "template",
        "template" => [
          "name" =>  env("APP_STATUS") == "Production" ? "rekyc_s" : "rekyc_new",   // must be the approved template name that has a URL button
          "language" => ["code" => "en_US"],
          "components" => [
            [
              "type" => "body",
              "parameters" => [
                ["type" => "text", "text" => $data['firstName']],
                ["type" => "text", "text" => "follow link"],
                ["type" => "text", "text" => (string)$data['expiryDays']]
              ]
            ],
            [
              "type" => "button",
              "sub_type" => "url",
              "index" => "0",
              "parameters" => [
                ["type" => "text", "text" => $data['accessUrl']]   // button will open this URL
              ]
            ]
          ]
        ]
      ];
      
      $dataSet= json_encode($payload, JSON_UNESCAPED_SLASHES);
      
      //"'.$data['mobileNumber'].'",
        
              $url =env('WHATSAPP_URL');

                $curl = curl_init($url);
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

                $headers = array(
                  "Authorization: Bearer EAAT14FGd9Y0BOZB2Q9qc2UtTp0affGpWL92xtsGKdYaHsubhcbZCip1CvvWhxVAtaIWZAkx5sS8m34KqjBDlhnMo7Ax0Pyj0uYYO5xZAOpTO7aLhVKwl4F6svgpoLeRyGaYTLlIfqBProVg9BzHPgOeN2TL2ZCGPQoJDLCgYHM93glcKifH5JVaGQXfNksPlP9z9zzZBBhcqasN1hV",
                  "Content-Type: application/json",
                );

                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            
            curl_setopt($curl, CURLOPT_POSTFIELDS, $dataSet);
            //fodr debug only!
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
          
            $resp = curl_exec($curl);
            $whatsAppModel= whatsAppModel::create([
                'url'    =>  $url, 
                'method'  =>  'POST', 
                'input' =>  json_encode($dataSet),
                'output' =>json_encode($resp),
                'template_type' =>  'rekyc_new',
                'policyNumber' =>  '',
                'customer_id' =>  $data['customer_id'],
                'WA_cellphone'=>$data['mobileNumber'],
                'start_time' => microtime(true),
                'end_time' =>  microtime(true),
                'created_at'=> \Carbon\Carbon::now(),
            
            ]);

            $this->lastId=$whatsAppModel->id;
            Log::info(json_encode( $this->lastId));

            curl_close($curl);
            return true;
          // }catch(\Exception $e){
          //   return false;
          // }
    }
  
    public function webhook(Request $request)   // for verify  remove webhook2 => webhook
    {
       try{
     //Log::info(json_encode($request->all()));
     //dd($request->all());
        if($request->hub_mode == "subscribe" && $request->hub_verify_token == "token"){
            $value = $request->hub_challenge;
            return  response()->json(intval($value),200);
        
        }else{

          $data = json_decode($request->getContent());

          if (isset($data->entry[0]) && isset($data->entry[0]->changes[0]->value->statuses)) {
              $statuses = $data->entry[0]->changes[0]->value->statuses;
      
              foreach ($statuses as $status) {
                  $recipientId = $status->recipient_id;
                  $newStatus = $status->status;
      
                
                  $record = whatsAppModel::where('WA_cellphone', $recipientId)
                      ->orderBy('id', 'desc')
                      ->first();
      
                  if ($record) {
                     
                    whatsAppModel::where('id', $record->id)
                          ->update(['status' => $newStatus]);
                  }
              }
          }

            return response()->json(["success" => "true" ],200);
        }
      }catch(\Exception $e){
        return  response()->json(["success" => "true" ],200);
      }
        //return "hi";
        //response()->json(["success" => "true" ],200);
    }
    public function webhook2(Request $request)   // for verify  remove webhook => webhook2
    {
        $payload = json_decode($request->getContent());

        // Loop through the entry array, which may contain multiple events
        foreach ($payload->entry as $entry) {
            // Check if the event is related to a WhatsApp Business Account
            if ($entry->object === 'whatsapp_business_account') {
                foreach ($entry->changes as $change) {
                    // Check the field that changed (in this case, 'messages')
                    if ($change->field === 'messages') {
                        // Process the message data
                        foreach ($change->value->messages as $message) {
                            // Extract relevant message details
                            $from = $message->from;
                            $id = $message->id;
                            $timestamp = $message->timestamp;
                            $type = $message->type;
                            // Depending on the message type, you can handle it differently
                            // if ($type === 'image') {
                            //     $imageMimeType = $message->image->mime_type;
                            //     $imageSha256 = $message->image->sha256;
                            //     $imageId = $message->image->id;
                            //     // Handle the image message (e.g., save it, respond, etc.)
                            // }
                            if ($type === 'text') {
                                $textBody = $message->text->body;
                                // Handle the text message (e.g., respond to it)
                                $this->handleTextMessage($from, $textBody);
                            }
                            if ($type === 'document') {
                                $documentFilename = $message->document->filename;
                                $documentMimeType = $message->document->mime_type;
                                $documentSha256 = $message->document->sha256;
                                $documentId = $message->document->id;
                                // Handle the document message (e.g., save it, respond, etc.)
                                $this->handleDocumentMessage($from, $documentFilename);
                            }
                            if ($type === 'audio') {
                                $audioDetails = $message->audio;
                                $mimeType = $audioDetails->mime_type;
                                $sha256 = $audioDetails->sha256;
                                $audioId = $audioDetails->id;
                                $isVoice = $audioDetails->voice;
            
                                // Implement your logic to handle the audio message here
                                // For example, save the audio, respond to it, or perform other actions
                                $this->handleAudioMessage($from, $mimeType, $sha256, $audioId, $isVoice);
                            }
                            if ($type === 'video') {
                                $videoDetails = $message->video;
                                $mimeType = $videoDetails->mime_type;
                                $sha256 = $videoDetails->sha256;
                                $videoId = $videoDetails->id;
            
                                // Implement your logic to handle the video message here
                                // For example, save the video, respond to it, or perform other actions
                                $this->handleVideoMessage($from, $mimeType, $sha256, $videoId);
                            }
                            if ($type === 'contacts') {
                                $contacts = $message->contacts;
                                
                                // Loop through the contacts (there can be multiple)
                                foreach ($contacts as $contact) {
                                    $formattedName = $contact->name->formatted_name;
                                    $phones = $contact->phones;
                                    
                                    // Loop through the phones (there can be multiple)
                                    foreach ($phones as $phone) {
                                        $phoneNumber = $phone->phone;
                                        $phoneType = $phone->type;
                                        
                                        // Implement your logic to handle the contacts message here
                                        // For example, save the contact details, respond to it, or perform other actions
                                        $this->handleContactsMessage($from, $formattedName, $phoneNumber, $phoneType);
                                    }
                                }
                            }
                            if ($type === 'image') {
                                $imageDetails = $message->image;
                                $caption = $imageDetails->caption;
                                $mimeType = $imageDetails->mime_type;
                                $sha256 = $imageDetails->sha256;
                                $imageId = $imageDetails->id;
            
                                // Implement your logic to handle the image message here
                                // For example, save the image, respond to it, or perform other actions
                                $this->handleImageMessage($from, $caption, $mimeType, $sha256, $imageId);
                            }
                            // Add logic for other message types as needed
                        }
                    }
                }
            }
        }

        return response()->json(['status' => 'success']);
    }

    private function handleIncomingMessage($payload)
    {
        // Process the incoming message, e.g., send a response
    }

    private function handleDeliveryReport($payload)
    {
        // Handle delivery report, e.g., update message status in your database
    }
    private function handleTextMessage($from, $textBody)
    {
        // Implement your logic to respond to the text message here
        // You can send a reply or perform any other action based on the message content
        // For example, you can use a messaging API to send a response back to the user
    }
    private function handleDocumentMessage($from, $filename)
    {
        // Implement your logic to handle the document message here
        // You can save the document, respond to it, or perform any other actions
    }
    private function handleAudioMessage($from, $mimeType, $sha256, $audioId, $isVoice)
    {
        // Implement your logic to handle the audio message here
        // You can save the audio, respond to it, or perform other actions
        // You can also check if it's a voice message using $isVoice
    }
    private function handleVideoMessage($from, $mimeType, $sha256, $videoId)
    {
        // Implement your logic to handle the video message here
        // You can save the video, respond to it, or perform other actions
    }
    private function handleContactsMessage($from, $formattedName, $phoneNumber, $phoneType)
    {
        // Implement your logic to handle the contacts message here
        // You can save the contact details, respond to it, or perform other actions
    }
    private function handleImageMessage($from, $caption, $mimeType, $sha256, $imageId)
    {
        $image = DB::table('images')->where('sha256', $sha256)->first();

        if ($image) {
            // You have a match; $image->image_data contains the original image data
            // You can handle the image as needed, for example, return it as a response
            return response($image->image_data)->header('Content-Type', 'image/jpeg');
        } else {
            // No match found; handle the case where the image data is not available
            return response()->json(['error' => 'Image not found'], 404);
        }
        // Implement your logic to handle the image message here
        // You can save the image, respond to it, or perform other actions
        // You can also use the $caption for additional context or processing
    }
    public function webhook1(Request $request)
    {
        //Log::info(json_encode($request->all()));
        if($request->hub_mode == "subscribe" && $request->hub_verify_token == "token"){
            $value = $request->hub_challenge;
            return  response()->json(intval($value),200);
        
        }else{
            return  response()->json(["error"=>"error" ],403);
        }
        //return "hi";
        //response()->json(["success" => "true" ],200);
    }

    /**
     * Show a list of all Activities.
     *
     * @return View
     */
    public function index()
    {
        if(Auth::user()->hasPermissionTo('activity-list'))
        {
            return view('admin.activityLog.whats_app_log');
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /*
     * Pass data through ajax call
     */
    /**
     * @return mixed
     */
    public function data()
    {
        return DataTables::of(whatsAppModel::query())

            ->editColumn('input', function ($activity) {
                if ($activity->input != null) {
                    return  $activity->input;
                }
            })

            
            ->editColumn('created_at', function ($activity) {
                if ($activity->created_at != null) {
                    return   \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $activity->created_at)->format('Y-m-d H:i') ;
                }
            })

            ->make(true);
    }
  
}
