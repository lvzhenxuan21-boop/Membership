<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * 邮箱验证邮件的队列版本：SMTP 慢时不阻塞注册请求。
 * QUEUE_CONNECTION=sync 时与同步行为一致；生产建议 database/redis 队列。
 */
class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;
}
