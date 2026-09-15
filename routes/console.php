<?php

use Illuminate\Support\Facades\Schedule;

// Monthly housekeeping: batch files past CERT_RETENTION_DAYS, temp files, stale logs.
Schedule::command('certificates:cleanup')
    ->monthlyOn(1, '04:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cleanup.log'));

// Queue bookkeeping tables.
Schedule::command('queue:prune-batches --hours=48 --unfinished=72 --cancelled=72')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
