<?php

namespace AlphaDirect\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ScratchCodeActivated extends Notification
{
  protected $policy;
  protected $userID;

  public function __construct($policy,$userID)
  {
      $this->userID = $userID;
      $this->policy = $policy;
  }

  public function via($notifiable)
  {
      return ['database'];
  }

  public function toDatabase($notifiable)
  {
     return [
             
            'user_id' => $this->userID,
        ];
  }
}
