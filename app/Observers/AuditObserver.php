<?php

namespace App\Observers;

use App\AuditLogService;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    /**
     * Create a new observer instance.
     */
    public function __construct(protected AuditLogService $auditLogService) {}

    /**
     * Handle the Model "created" event.
     */
    public function created(Model $model): void
    {
        $this->auditLogService->logCreated($model, [
            'observer' => static::class,
        ]);
    }

    /**
     * Handle the Model "updated" event.
     */
    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        $original = $model->getOriginal();

        if (! empty($changes)) {
            $this->auditLogService->logUpdated($model, $original, $changes, [
                'observer' => static::class,
                'changed_attributes' => array_keys($changes),
            ]);
        }
    }

    /**
     * Handle the Model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        $this->auditLogService->logDeleted($model, $model->getOriginal(), [
            'observer' => static::class,
        ]);
    }

    /**
     * Handle the Model "restored" event.
     */
    public function restored(Model $model): void
    {
        $this->auditLogService->log('restored', $model, [], [], [
            'observer' => static::class,
        ]);
    }

    /**
     * Handle the Model "force deleted" event.
     */
    public function forceDeleted(Model $model): void
    {
        $this->auditLogService->log('force_deleted', $model, [], [], [
            'observer' => static::class,
        ]);
    }
}
