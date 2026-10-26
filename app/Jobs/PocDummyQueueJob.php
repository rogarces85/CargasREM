<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PocDummyQueueJob implements ShouldQueue
{
    use Queueable;

    /**
     * Retornos/estados para la POC T-009 (worker Windows).
     */
    public function __construct(public int $payload)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // marcador en disco: prueba tangible de que el worker procesó el job
        $marcador = storage_path('rem/poc/queue_marker.txt');
        if (! is_dir(dirname($marcador))) {
            mkdir(dirname($marcador), 0777, true);
        }
        file_put_contents(
            $marcador,
            now()->toDateTimeString()
                . ' | job=PocDummyQueueJob'
                . ' | payload=' . $this->payload
                . ' | host=' . gethostname()
                . PHP_EOL,
            FILE_APPEND
        );

        logger()->info('POC T-009: job Procesado', ['payload' => $this->payload]);
    }
}
