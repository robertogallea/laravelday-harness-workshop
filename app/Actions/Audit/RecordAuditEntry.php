<?php

namespace App\Actions\Audit;

use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class RecordAuditEntry
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(?User $actor, string $action, Model $subject, array $payload = []): AuditEntry
    {
        return AuditEntry::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'payload' => $payload,
        ]);
    }
}
