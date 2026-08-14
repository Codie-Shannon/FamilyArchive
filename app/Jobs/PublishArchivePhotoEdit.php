<?php

namespace App\Jobs;

use App\Domain\Archive\Services\ArchivePhotoEditBatchPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PublishArchivePhotoEdit implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 75;

    public bool $failOnTimeout = true;

    public function __construct(public int $batchItemId)
    {
        $this->onConnection('database')->onQueue('default');
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('archive-photo-edit:'.$this->batchItemId))->releaseAfter(5)->expireAfter(90)];
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(ArchivePhotoEditBatchPublisher $publisher): void
    {
        try {
            $publisher->publish($this->batchItemId, max(1, $this->attempts()));
        } catch (Throwable $exception) {
            if ($exception instanceof ValidationException || $this->attempts() >= $this->tries) {
                $publisher->fail($this->batchItemId, $exception);

                return;
            }

            $publisher->requeue($this->batchItemId, $exception);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(ArchivePhotoEditBatchPublisher::class)->fail(
            $this->batchItemId,
            $exception ?? new \RuntimeException('The queued photo edit stopped before completion.'),
        );
    }
}
