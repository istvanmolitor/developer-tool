<?php

namespace App\Enums;

enum ProjectProvisionStatus: string
{
    /** The project directory exists and is ready to use. */
    case Ready = 'ready';

    /** Queued for creation (mkdir or git clone), not started yet. */
    case Pending = 'pending';

    /** The creation job is currently running. */
    case Provisioning = 'provisioning';

    /** Directory creation or cloning failed; see provision_error. */
    case Failed = 'failed';

    public function isReady(): bool
    {
        return $this === self::Ready;
    }

    public function isInProgress(): bool
    {
        return in_array($this, [self::Pending, self::Provisioning], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Kész',
            self::Pending => 'Előkészítve',
            self::Provisioning => 'Létrehozás folyamatban',
            self::Failed => 'Sikertelen',
        };
    }
}
