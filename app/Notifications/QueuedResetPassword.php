<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * 密码重置邮件的队列版本：SMTP 慢时不阻塞找回请求。
 * QUEUE_CONNECTION=sync 时与同步行为一致；生产建议 database/redis 队列。
 */
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
