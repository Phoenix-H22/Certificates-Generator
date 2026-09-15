<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('certificates:prune')->dailyAt('03:30');
Schedule::command('queue:prune-batches --hours=48 --unfinished=72 --cancelled=72')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
