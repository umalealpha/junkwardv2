<?php

namespace AlphaDirect\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;


class OTPNotification extends Notification implements ShouldQueue
{
use Queueable;
public $via;
public $OTP;
/**
 * Create a new notification instance.
 *
 * @return void
 */
public function __construct($via , $OTP)
{
    $this->via = $via;
    $this->OTP = $OTP;
 }
 public function via($notifiable)
 {
     return $this->via == 'sms' ? [KarixChannel::class] : ['mail'];
 }

/**
 * Get the notification's delivery channels.
 *
 * @param  mixed  $notifiable
 * @return array
 */

public function toKarix($notifiable)
{
    /* return KarixMessage::create()
                    ->from('36452')
                    ->content("Alpha Direct's OTP : {$this->OTP}"); */

                    return ;
}
/**
 * Get the mail representation of the notification.
 *
 * @param  mixed  $notifiable
 * @return \Illuminate\Notifications\Messages\MailMessage
 */
public function toMail($notifiable)
{
    return (new MailMessage)
                ->markdown('OTP', ['OTP' => $this->OTP]);
}
/**
 * Get the array representation of the notification.
 *
 * @param  mixed  $notifiable
 * @return array
 */
public function toArray($notifiable)
{
    return [
        //
    ];
}
}

