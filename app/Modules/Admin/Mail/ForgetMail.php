<?php

namespace App\Modules\Admin\Mail;

use Illuminate\{
    Bus\Queueable,
    Mail\Mailable,
    Queue\SerializesModels
};

class ForgetMail extends Mailable {

    use Queueable,
        SerializesModels;

    public $user;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct($user) {
        $this->user = $user;
    }

    /**
     * 构建邮件内容
     * @return text
     */
    public function build() {
        $subject = '忘记密码';
        $this->subject($subject);
        return $this->view("mail.forget");
    }

}
